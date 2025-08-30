<?php
// List delivery agents (mirrors your get_customer.php style)
header('Content-Type: application/json');

try {
    require_once __DIR__ . '/db.php';

    // Try to obtain a PDO instance from db.php (supports a few common patterns)
    if (!isset($pdo)) {
        if (function_exists('get_pdo'))       { $pdo = get_pdo(); }
        elseif (function_exists('db'))        { $pdo = db(); }
        elseif (function_exists('pdo'))       { $pdo = pdo(); }
        else throw new Exception('PDO instance $pdo not found in db.php');
    }

    $sql = "SELECT delivery_id, type, driver_name, driver_phone, car_number, created_at
            FROM delivery
            ORDER BY created_at DESC, delivery_id DESC";
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
