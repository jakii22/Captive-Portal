<?php
/**
 * Dashboard - Export Users Report
 * Generates a CSV report of connected users from the radacct table for the current month.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin(); // Both 'full' and 'read' can export the report

$db = Database::getInstance();

try {
    // Get current month and year
    $startOfMonth = date('Y-m-01 00:00:00');
    $endOfMonth = date('Y-m-t 23:59:59');

    // Query radacct table
    $stmt = $db->prepare("
        SELECT 
            username, 
            callingstationid AS mac_address, 
            framedipaddress AS ip_address, 
            acctstarttime, 
            acctstoptime, 
            acctsessiontime, 
            acctinputoctets, 
            acctoutputoctets,
            acctterminatecause
        FROM radacct 
        WHERE acctstarttime >= :start_date 
          AND acctstarttime <= :end_date
        ORDER BY acctstarttime DESC
    ");
    $stmt->execute([
        ':start_date' => $startOfMonth,
        ':end_date' => $endOfMonth
    ]);
    
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers to trigger CSV download
    $filename = "Laporan_User_Hotspot_" . date('Y-m') . ".csv";
    header("Content-Type: text/csv");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    $output = fopen("php://output", "w");

    // Add CSV Headers
    fputcsv($output, [
        'Username',
        'MAC Address',
        'IP Address',
        'Start Time',
        'Stop Time',
        'Duration (Seconds)',
        'Download (Bytes)',
        'Upload (Bytes)',
        'Terminate Cause'
    ]);

    // Add Rows
    foreach ($records as $row) {
        fputcsv($output, [
            $row['username'],
            $row['mac_address'],
            $row['ip_address'],
            $row['acctstarttime'],
            $row['acctstoptime'],
            $row['acctsessiontime'],
            $row['acctoutputoctets'], // Download is from NAS to user (output)
            $row['acctinputoctets'],  // Upload is from user to NAS (input)
            $row['acctterminatecause']
        ]);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Export Report Error: " . $e->getMessage());
    die("Terjadi kesalahan saat meng-export laporan.");
}
