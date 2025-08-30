<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$oid = $_GET['order_id'] ?? '';
if ($oid === '') { echo json_encode(['error'=>true,'message'=>'Missing order_id']); exit; }

try {
  $sql = "
    SELECT 
      o.order_id, ot.current_lat, ot.current_lng, ot.dest_lat, ot.dest_lng,
      d.driver_name, d.driver_phone
    FROM orders o
    LEFT JOIN order_tracking ot ON ot.order_id = o.order_id
    LEFT JOIN delivery d ON d.delivery_id = o.delivery_id
    WHERE o.order_id = :oid
    LIMIT 1
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':oid'=>$oid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  echo json_encode($row ?: new stdClass());
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>true]);
}
