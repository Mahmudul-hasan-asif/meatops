<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

try {
  $stmt = $pdo->query("SELECT DISTINCT product_package_name FROM product_list ORDER BY product_package_name ASC");
  $out = [];
  while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $out[] = $r['product_package_name'];
  echo json_encode($out);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([]);
}
