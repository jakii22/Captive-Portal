<?php
/**
 * RouterOS API PHP Client
 * Based on the community RouterOS API library
 * For communicating with MikroTik RouterOS devices
 */

class RouterosAPI
{
    private $socket;
    private bool $connected = false;
    private bool $debug = false;
    private int $timeout = 3;
    private int $port = 8728;
    private int $attempts = 5;
    private int $delay = 3;
    private string $errorStr = '';

    /**
     * Set debug mode
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * Set connection timeout
     */
    public function setTimeout(int $timeout): void
    {
        $this->timeout = $timeout;
    }

    /**
     * Set port
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * Set connection attempts
     */
    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    /**
     * Get last error
     */
    public function getError(): string
    {
        return $this->errorStr;
    }

    /**
     * Check if connected
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Connect to RouterOS
     */
    public function connect(string $ip, string $login, string $password): bool
    {
        for ($attempt = 1; $attempt <= $this->attempts; $attempt++) {
            $this->connected = false;

            $this->socket = @fsockopen($ip, $this->port, $errno, $errstr, $this->timeout);

            if ($this->socket === false) {
                $this->errorStr = "Connection attempt {$attempt} failed: {$errstr} ({$errno})";
                if ($this->debug) {
                    echo $this->errorStr . PHP_EOL;
                }
                sleep($this->delay);
                continue;
            }

            stream_set_timeout($this->socket, $this->timeout);

            // Try login
            $this->write('/login', false);
            $this->write('=name=' . $login, false);
            $this->write('=password=' . $password);

            $response = $this->read(false);

            if (isset($response[0]) && $response[0] === '!done') {
                $this->connected = true;
                return true;
            }

            // Old login method (pre 6.43)
            if (isset($response[1])) {
                $matches = [];
                if (preg_match('/=ret=(.+)/', $response[1], $matches)) {
                    $challenge = hex2bin($matches[1]);
                    $this->write('/login', false);
                    $this->write('=name=' . $login, false);
                    $this->write('=response=00' . bin2hex(md5(chr(0) . $password . $challenge, true)));
                    $response = $this->read(false);

                    if (isset($response[0]) && $response[0] === '!done') {
                        $this->connected = true;
                        return true;
                    }
                }
            }

            $this->errorStr = 'Login failed for user: ' . $login;
            fclose($this->socket);
        }

        return false;
    }

    /**
     * Disconnect from RouterOS
     */
    public function disconnect(): void
    {
        if ($this->connected) {
            fclose($this->socket);
            $this->connected = false;
        }
    }

    /**
     * Send a command to RouterOS
     */
    public function comm(string $command, array $params = []): array
    {
        $this->write($command, count($params) === 0);

        foreach ($params as $key => $value) {
            $this->write('=' . $key . '=' . $value, false);
        }

        if (count($params) > 0) {
            $this->write('');
        }

        return $this->parseResponse($this->read());
    }

    /**
     * Write a word to the socket
     */
    private function write(string $command, bool $endSentence = true): void
    {
        $this->writeWord($command);
        if ($endSentence) {
            $this->writeWord('');
        }
    }

    /**
     * Write a single word
     */
    private function writeWord(string $word): void
    {
        $len = strlen($word);

        if ($len < 0x80) {
            fwrite($this->socket, chr($len));
        } elseif ($len < 0x4000) {
            $len |= 0x8000;
            fwrite($this->socket, chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x200000) {
            $len |= 0xC00000;
            fwrite($this->socket, chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } elseif ($len < 0x10000000) {
            $len |= 0xE0000000;
            fwrite($this->socket, chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        } else {
            fwrite($this->socket, chr(0xF0) . chr(($len >> 24) & 0xFF) . chr(($len >> 16) & 0xFF) . chr(($len >> 8) & 0xFF) . chr($len & 0xFF));
        }

        fwrite($this->socket, $word);

        if ($this->debug) {
            echo '>>> ' . $word . PHP_EOL;
        }
    }

    /**
     * Read response from socket
     */
    private function read(bool $parse = true): array
    {
        $response = [];
        $receivedDone = false;

        while (!$receivedDone) {
            $word = $this->readWord();

            if ($word === '') {
                if (count($response) > 0) {
                    // End of sentence
                    if ($response[count($response) - 1] === '!done' || 
                        $response[count($response) - 1] === '!fatal') {
                        $receivedDone = true;
                    }
                }
            } else {
                $response[] = $word;
                if ($this->debug) {
                    echo '<<< ' . $word . PHP_EOL;
                }
            }

            // Safety: check for stream timeout
            $status = stream_get_meta_data($this->socket);
            if ($status['timed_out']) {
                $this->errorStr = 'Read timeout';
                break;
            }
        }

        return $response;
    }

    /**
     * Read a single word from the socket
     */
    private function readWord(): string
    {
        $byte = ord(fread($this->socket, 1));

        if ($byte < 0x80) {
            $len = $byte;
        } elseif ($byte < 0xC0) {
            $len = (($byte & ~0x80) << 8) + ord(fread($this->socket, 1));
        } elseif ($byte < 0xE0) {
            $len = (($byte & ~0xC0) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } elseif ($byte < 0xF0) {
            $len = (($byte & ~0xE0) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        } else {
            $len = (ord(fread($this->socket, 1)) << 24) + (ord(fread($this->socket, 1)) << 16) + (ord(fread($this->socket, 1)) << 8) + ord(fread($this->socket, 1));
        }

        if ($len === 0) {
            return '';
        }

        $word = '';
        $remaining = $len;
        while ($remaining > 0) {
            $chunk = fread($this->socket, $remaining);
            $word .= $chunk;
            $remaining -= strlen($chunk);
        }

        return $word;
    }

    /**
     * Parse RouterOS response into associative arrays
     */
    private function parseResponse(array $response): array
    {
        $parsed = [];
        $current = [];

        foreach ($response as $word) {
            if (in_array($word, ['!re', '!done', '!trap', '!fatal'])) {
                if (!empty($current)) {
                    $parsed[] = $current;
                }
                $current = [];
                if ($word === '!trap' || $word === '!fatal') {
                    $current['!type'] = $word;
                }
                continue;
            }

            if (preg_match('/^=(.+?)=(.*)$/', $word, $matches)) {
                $current[$matches[1]] = $matches[2];
            }
        }

        if (!empty($current)) {
            $parsed[] = $current;
        }

        return $parsed;
    }

    /**
     * Destructor - ensure socket is closed
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
