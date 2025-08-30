<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$in = json_decode(file_get_contents('php://input'), true);
$order_id = $in['order_id'] ?? null;
$name     = $in['product_package_name'] ?? null;
$weight   = $in['weight_kg'] ?? null;
$customer = $in['customer_id'] ?? null;
$delivery = $in['delivery_id'] ?? null;

function genId(){ $c='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'; $s=''; for($i=0;$i<5;$i++) $s.=$c[random_int(0,strlen($c)-1)]; return $s; }
if(!$order_id) $order_id = genId();
if(!$name || !$weight || !$customer || !$delivery){ echo json_encode(['success'=>false,'message'=>'Missing required fields']); exit; }

try {
  $pdo->beginTransaction();

  // FIFO pick & lock
  $pick = $pdo->prepare("
    SELECT
      pl.id AS pl_id,
      pl.product_id,           -- code
      pl.product_package_id,   -- numeric FK to product
      pl.batch_code,
      b.expire_date,
      ROUND(COALESCE(p.price,0) * pl.weight_kg, 2) AS price_per_unit
    FROM product_list pl
    LEFT JOIN product p ON p.product_id = pl.product_package_id
    LEFT JOIN batch   b ON b.batch_code  = pl.batch_code
    WHERE pl.product_package_name = :name
      AND pl.weight_kg = :w
    ORDER BY pl.batch_code ASC, pl.product_package_id ASC, pl.id ASC
    LIMIT 1
    FOR UPDATE
  ");
  $pick->execute([':name'=>$name, ':w'=>$weight]);
  $row = $pick->fetch(PDO::FETCH_ASSOC);
  if(!$row){ $pdo->rollBack(); echo json_encode(['success'=>false,'message'=>'No stock for FIFO']); exit; }

  // Insert order (keeps your code string in orders.product_id)
  $ins = $pdo->prepare("
    INSERT INTO orders (
      order_id, product_package_name, weight_kg,
      product_id, batch_code, customer_id, delivery_id,
      price_per_unit, expire_date
    ) VALUES (
      :oid, :name, :w, :pid, :batch, :cid, :did, :ppu, :expd
    )
  ");
  $ins->execute([
    ':oid'=>$order_id, ':name'=>$name, ':w'=>$weight,
    ':pid'=>$row['product_id'], ':batch'=>$row['batch_code'],
    ':cid'=>$customer, ':did'=>$delivery,
    ':ppu'=>$row['price_per_unit'] ?? 0, ':expd'=>$row['expire_date'] ?? null
  ]);

  // Decrement inventory: delete the specific unit we sold
  $del = $pdo->prepare("DELETE FROM product_list WHERE id = :id LIMIT 1");
  $del->execute([':id'=>$row['pl_id']]);

  $pdo->commit();
  echo json_encode(['success'=>true, 'order_id'=>$order_id]);
} catch (Throwable $e) {
  if($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
