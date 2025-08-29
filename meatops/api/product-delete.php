<?php
// api/product-delete.php
require_once __DIR__ . '/_db_bootstrap.php';

try {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) throw new Exception('Invalid JSON body');
    $id = isset($body['product_id']) ? (int)$body['product_id'] : 0;
    if ($id <= 0) throw new Exception('Missing/invalid product_id');

    $stmt = $pdo->prepare('DELETE FROM product WHERE product_id = ?');
    $stmt->execute([$id]);

    echo json_encode(['ok' => true, 'data' => ['deleted' => $stmt->rowCount(), 'product_id' => $id]]);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
