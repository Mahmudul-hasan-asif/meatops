<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php'; // must create $pdo (PDO)

$out = ['ok' => false, 'data' => []];
try {
    $sql = "SELECT id, product_id, batch_id, batch_code, product_name, weight_kg, package_size_kg, created_at
            FROM waste
            ORDER BY id DESC";
    $st = $pdo->query($sql);
    $out['ok'] = true;
    $out['data'] = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $out['error'] = $e->getMessage();
}
echo json_encode($out);
