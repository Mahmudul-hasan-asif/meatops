<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function ok($d){ echo json_encode(['ok'=>true,'data'=>$d]); exit; }
function fail($m,$c=400){ http_response_code($c); echo json_encode(['ok'=>false,'error'=>$m]); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!$in) $in = $_POST;

$batch_id = intval($in['batch_id'] ?? 0);
if (!$batch_id) fail('batch_id required');

try{
  // Pull source batch
  $st = $pdo->prepare("SELECT id,batch_code,product,qty_kg,expire_date,facility FROM batch WHERE id=?");
  $st->execute([$batch_id]);
  $b = $st->fetch(PDO::FETCH_ASSOC);
  if (!$b) fail('Batch not found');

  // Upsert into pushed_batches (assumes UNIQUE KEY on batch_id)
  $sql = "INSERT INTO pushed_batches (batch_id,batch_code,product,qty_kg,expire_date,facility,packed)
          VALUES (?,?,?,?,?,?,0)
          ON DUPLICATE KEY UPDATE
            batch_code=VALUES(batch_code),
            product=VALUES(product),
            qty_kg=VALUES(qty_kg),
            expire_date=VALUES(expire_date),
            facility=VALUES(facility)";
  $pdo->prepare($sql)->execute([
    $b['id'], $b['batch_code'], $b['product'], $b['qty_kg'], $b['expire_date'], $b['facility']
  ]);

  // Return upserted row
  $st = $pdo->prepare("SELECT * FROM pushed_batches WHERE batch_id=?");
  $st->execute([$batch_id]);
  ok($st->fetch(PDO::FETCH_ASSOC));

}catch(Throwable $e){
  fail($e->getMessage(), 500);
}