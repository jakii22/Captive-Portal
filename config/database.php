<?php
/**
 * Database Configuration - PDO PostgreSQL
 * Singleton pattern for database connection
 */

class Database
{
    private static ?PDO $instance = null;

    // Database credentials - UBAH SESUAI ENVIRONMENT
    private const DB_HOST = 'localhost';
    private const DB_PORT = '5432';
    private const DB_NAME = 'captive_portal';
    private const DB_USER = 'postgres';
    private const DB_PASS = 'your_password_here';

    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {}

    /**
     * Get database credentials (used by backup/restore utility)
     */
    public static function getCredentials(): array
    {
        return [
            'host' => self::DB_HOST,
            'port' => self::DB_PORT,
            'name' => self::DB_NAME,
            'user' => self::DB_USER,
            'pass' => self::DB_PASS,
        ];
    }


    /**
     * Get PDO instance (singleton)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $dsn = sprintf(
                    'pgsql:host=%s;port=%s;dbname=%s;options=\'--client_encoding=UTF8\'',
                    self::DB_HOST,
                    self::DB_PORT,
                    self::DB_NAME
                );

                self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_PERSISTENT         => false,
                ]);

                // Set timezone
                self::$instance->exec("SET timezone = 'Asia/Jakarta'");

            } catch (PDOException $e) {
                error_log('Database Connection Error: ' . $e->getMessage());
                throw new RuntimeException('Gagal terhubung ke database. Silakan cek konfigurasi.');
            }
        }

        return self::$instance;
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup()
    {
        throw new RuntimeException('Cannot unserialize singleton');
    }
}
