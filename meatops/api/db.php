<?php
// api/db.php
// Central DB connection file — always defines $pdo

$DB_HOST = '127.0.0.1';     // adjust if needed
$DB_NAME = 'meat_inventory';
$DB_USER = 'root';
$DB_PASS = '';
$DB_CHARSET = 'utf8mb4';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'DB connection failed: '.$e->getMessage()]);
    exit;
}

// Optional helper for old code that calls pdo_conn()
if (!function_exists('pdo_conn')) {
    function pdo_conn() {
        return $GLOBALS['pdo'];
    }
}
