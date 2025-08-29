<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

$in = json_decode(file_get_contents('php://input'), true);
if(!$in) $in = $_POST;

$batch_code    = trim($in['batch_code'] ?? '');
$processing_id = intval($in['processing_id'] ?? 0);
$qty_kg        = (float)($in['qty_kg'] ?? 0);
$expire_date   = $in['expire_date'] ?? null;
$facility      = $in['facility'] ?? null;

if ($batch_code==='' || !$processing_id || $qty_kg<=0 || !$expire_date || !$facility) {
  fail('Missing or invalid fields.');
}

try{
  // Look up processing unit
  $st = $pdo->prepare("SELECT cut_type, yield_kg, processing_date FROM processing_units WHERE id=?");
  $st->execute([$processing_id]);
  $pu = $st->fetch(PDO::FETCH_ASSOC);
  if(!$pu) fail('Processing unit not found.');
  if($qty_kg > (float)$pu['yield_kg']) fail('Qty cannot exceed processing unit yield.');
  if($expire_date < $pu['processing_date']) fail('Expire date cannot be earlier than processing date.');

  // Unique batch_code
  $st = $pdo->prepare("SELECT COUNT(*) FROM batch WHERE batch_code=?");
  $st->execute([$batch_code]);
  if($st->fetchColumn() > 0) fail('Batch code already exists.');

  // Insert
  $st = $pdo->prepare("INSERT INTO batch (batch_code, processing_id, product, qty_kg, expire_date, facility, pushed)
                       VALUES (?,?,?,?,?,?,0)");
  $st->execute([$batch_code, $processing_id, $pu['cut_type'], $qty_kg, $expire_date, $facility]);
  $id = $pdo->lastInsertId();

  $st = $pdo->prepare("SELECT id, batch_code, processing_id, product, qty_kg, expire_date, facility, pushed, created_at, updated_at FROM batch WHERE id=?");
  $st->execute([$id]);
  ok($st->fetch(PDO::FETCH_ASSOC));

}catch(Throwable $e){ fail($e->getMessage(),500); }
