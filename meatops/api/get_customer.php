<?php
// api/get_customer.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$cid = $_GET['customer_id'] ?? '';
if ($cid === '') { echo json_encode(new stdClass()); exit; }

try {
  // Try singular table name first
  $stmt = $pdo->prepare("
      SELECT customer_id, name, address, phone, email
      FROM customer
      WHERE customer_id = :cid
      LIMIT 1
  ");
  $stmt->execute([':cid' => $cid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  // Fall back to plural if singular not found
  if (!$row) {
    $stmt2 = $pdo->prepare("
        SELECT customer_id, name, address, phone, email
        FROM customers
        WHERE customer_id = :cid
        LIMIT 1
    ");
    $stmt2->execute([':cid' => $cid]);
    $row = $stmt2->fetch(PDO::FETCH_ASSOC);
  }

  echo json_encode($row ?: new stdClass());
} catch (Throwable $e) {
  echo json_encode(new stdClass());
}
