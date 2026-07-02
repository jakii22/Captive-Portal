<?php
/**
 * Dashboard - Export Users Report (CSV)
 * Menghasilkan laporan CSV yang rapi dengan ringkasan dan format data yang mudah dibaca.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// ── Helper Functions ──────────────────────────────────────────────────────────

/**
 * Format bytes ke KB / MB / GB
 */
function formatBytesExport(int $bytes): string
{
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow   = min((int) floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
}

/**
 * Format detik ke "X jam Y menit Z detik"
 */
function formatDurationExport(int $seconds): string
{
    if ($seconds <= 0) return '0 detik';
    $h = (int) floor($seconds / 3600);
    $m = (int) floor(($seconds % 3600) / 60);
    $s = $seconds % 60;
    $parts = [];
    if ($h > 0) $parts[] = "{$h} jam";
    if ($m > 0) $parts[] = "{$m} menit";
    if ($s > 0 && $h === 0) $parts[] = "{$s} detik";
    return implode(' ', $parts) ?: '0 detik';
}

/**
 * Format timestamp ke "dd/mm/yyyy HH:MM"
 */
function formatTimestampExport(?string $ts): string
{
    if (empty($ts)) return '-';
    return date('d/m/Y H:i', strtotime($ts));
}

/**
 * Bersihkan IP address (hapus "/32" atau prefix lain)
 */
function cleanIpExport(?string $ip): string
{
    if (empty($ip)) return '-';
    return explode('/', $ip)[0];
}

/**
 * Terjemahkan terminate cause ke Indonesia
 */
function translateCauseExport(?string $cause): string
{
    if (empty($cause)) return 'Masih Aktif';
    $map = [
        'Admin-Reset'       => 'Admin-Reset',
        'Lost-Service'      => 'Koneksi Terputus',
        'User-Request'      => 'User Disconnect',
        'Idle-Timeout'      => 'Idle Timeout',
        'Session-Timeout'   => 'Sesi Habis',
        'NAS-Reboot'        => 'Router Restart',
        'Port-Error'        => 'Error Port',
    ];
    return $map[$cause] ?? $cause;
}

// ── Query Data ────────────────────────────────────────────────────────────────

try {
    $db = Database::getInstance();

    $startOfMonth = date('Y-m-01 00:00:00');
    $endOfMonth   = date('Y-m-t 23:59:59');
    $bulanTahun   = date('F Y'); // e.g. "July 2026"

    // Nama bulan dalam bahasa Indonesia
    $namaBulan = [
        'January' => 'Januari', 'February' => 'Februari', 'March' => 'Maret',
        'April'   => 'April',   'May'       => 'Mei',      'June'  => 'Juni',
        'July'    => 'Juli',    'August'    => 'Agustus',  'September' => 'September',
        'October' => 'Oktober', 'November'  => 'November', 'December'  => 'Desember',
    ];
    $bulanTahunId = str_replace(array_keys($namaBulan), array_values($namaBulan), $bulanTahun);

    // Query radacct JOIN users untuk dapat nama & metode login
    $stmt = $db->prepare("
        SELECT
            ra.username,
            COALESCE(u.name, '-')         AS nama,
            COALESCE(u.login_method, '-') AS metode_login,
            ra.callingstationid           AS mac_address,
            ra.framedipaddress            AS ip_address,
            ra.acctstarttime,
            ra.acctstoptime,
            COALESCE(ra.acctsessiontime, 0)    AS acctsessiontime,
            COALESCE(ra.acctinputoctets, 0)    AS acctinputoctets,
            COALESCE(ra.acctoutputoctets, 0)   AS acctoutputoctets,
            ra.acctterminatecause
        FROM radacct ra
        LEFT JOIN users u ON u.username_identity = ra.username
        WHERE ra.acctstarttime >= :start_date
          AND ra.acctstarttime <= :end_date
        ORDER BY ra.acctstarttime DESC
    ");
    $stmt->execute([
        ':start_date' => $startOfMonth,
        ':end_date'   => $endOfMonth,
    ]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ── Hitung Ringkasan ──────────────────────────────────────────────────────
    $totalSessions  = count($records);
    $uniqueUsers    = count(array_unique(array_column($records, 'username')));
    $totalUpload    = (int) array_sum(array_column($records, 'acctinputoctets'));
    $totalDownload  = (int) array_sum(array_column($records, 'acctoutputoctets'));
    $totalDuration  = (int) array_sum(array_column($records, 'acctsessiontime'));

    // ── Set HTTP Headers untuk Download ──────────────────────────────────────
    $filename = 'Laporan_Hotspot_' . date('Y-m') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // UTF-8 BOM — wajib agar Excel baca karakter Indonesia dengan benar
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // ── BAGIAN 1: Judul & Metadata ────────────────────────────────────────────
    fputcsv($output, ['LAPORAN HOTSPOT - ' . strtoupper($bulanTahunId)]);
    fputcsv($output, ['Periode:', date('01/m/Y') . ' - ' . date('t/m/Y')]);
    fputcsv($output, ['Digenerate:', date('d/m/Y H:i')]);
    fputcsv($output, ['']); // baris kosong

    // ── BAGIAN 2: Ringkasan ───────────────────────────────────────────────────
    fputcsv($output, ['RINGKASAN BULAN INI']);
    fputcsv($output, ['Total Sesi:', $totalSessions]);
    fputcsv($output, ['Pengguna Unik:', $uniqueUsers]);
    fputcsv($output, ['Total Upload:', formatBytesExport($totalUpload)]);
    fputcsv($output, ['Total Download:', formatBytesExport($totalDownload)]);
    fputcsv($output, ['Total Durasi:', formatDurationExport($totalDuration)]);
    fputcsv($output, ['']); // baris kosong
    fputcsv($output, ['--- DETAIL SESI ---']);
    fputcsv($output, ['']); // baris kosong

    // ── BAGIAN 3: Header Tabel ────────────────────────────────────────────────
    fputcsv($output, [
        'No.',
        'Username',
        'Nama',
        'Metode Login',
        'MAC Address',
        'IP Address',
        'Waktu Mulai',
        'Waktu Selesai',
        'Durasi',
        'Upload',
        'Download',
        'Penyebab Putus',
    ]);

    // ── BAGIAN 4: Data Rows ───────────────────────────────────────────────────
    foreach ($records as $i => $row) {
        fputcsv($output, [
            $i + 1,
            $row['username'],
            $row['nama'],
            ucfirst($row['metode_login']),
            $row['mac_address'] ?: '-',
            cleanIpExport($row['ip_address']),
            formatTimestampExport($row['acctstarttime']),
            formatTimestampExport($row['acctstoptime']),
            formatDurationExport((int) $row['acctsessiontime']),
            formatBytesExport((int) $row['acctinputoctets']),   // Upload = input (user → NAS)
            formatBytesExport((int) $row['acctoutputoctets']),  // Download = output (NAS → user)
            translateCauseExport($row['acctterminatecause']),
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log('Export Report Error: ' . $e->getMessage());
    die('Terjadi kesalahan saat meng-export laporan.');
}
