<?php
// api/_db_bootstrap.php
// Loads db.php and returns a PDO instance in $pdo.
// Works whether your db.php exposes $pdo itself or a function like pdo_conn() / db_connect().

require_once __DIR__ . '/db.php';   // your existing file

// If db.php already set $pdo, keep it.
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Try common helpers, but don't require them
    if (function_exists('pdo_conn')) {
        $pdo = pdo_conn();
    } elseif (function_exists('db_connect')) {
        // If your db.php exposes db_connect() returning PDO, this will work too.
        $pdo = db_connect();
    }
}

// Final check
if (!($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No PDO handle from db.php (define $pdo or pdo_conn()).']);
    exit;
}

// Always JSON
header('Content-Type: application/json');
