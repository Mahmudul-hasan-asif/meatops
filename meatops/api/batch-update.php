<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

$in = json_decode(file_get_contents('php://input'), true);
if(!$in) $in = $_POST;

$id            = intval($in['id'] ?? 0);
$processing_id = array_key_exists('processing_id',$in) ? intval($in['processing_id']) : null;
$qty_kg        = array_key_exists('qty_kg',$in)        ? (float)$in['qty_kg'] : null;
$expire_date   = $in['expire_date'] ?? null;
$facility      = $in['facility'] ?? null;
$pushed        = array_key_exists('pushed',$in) ? (int)!!$in['pushed'] : null;

if(!$id) fail('Missing id.');

try{
  // Existing row
  $st = $pdo->prepare("SELECT * FROM batch WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if(!$row) fail('Batch not found.');

  // Validate against processing unit if any related field changes
  $newPid = $processing_id ?? $row['processing_id'];
  $st = $pdo->prepare("SELECT cut_type, yield_kg, processing_date FROM processing_units WHERE id=?");
  $st->execute([$newPid]);
  $pu = $st->fetch(PDO::FETCH_ASSOC);
  if(!$pu) fail('Processing unit not found.');
  if($qty_kg !== null && $qty_kg > (float)$pu['yield_kg']) fail('Qty cannot exceed processing unit yield.');
  if($expire_date !== null && $expire_date < $pu['processing_date']) fail('Expire date cannot be earlier than processing date.');

  $sets=[]; $vals=[];
  if($processing_id !== null){ $sets[]="processing_id=?"; $vals[]=$processing_id; $sets[]="product=?"; $vals[]=$pu['cut_type']; }
  if($qty_kg !== null){       $sets[]="qty_kg=?";        $vals[]=$qty_kg; }
  if($expire_date !== null){  $sets[]="expire_date=?";   $vals[]=$expire_date; }
  if($facility !== null){     $sets[]="facility=?";      $vals[]=$facility; }
  if($pushed !== null){       $sets[]="pushed=?";        $vals[]=$pushed; }

  if($sets){
    $sql="UPDATE batch SET ".implode(',',$sets)." WHERE id=?";
    $vals[]=$id;
    $st=$pdo->prepare($sql); $st->execute($vals);
  }

  $st = $pdo->prepare("SELECT id, batch_code, processing_id, product, qty_kg, expire_date, facility, pushed, created_at, updated_at FROM batch WHERE id=?");
  $st->execute([$id]);
  ok($st->fetch(PDO::FETCH_ASSOC));

}catch(Throwable $e){ fail($e->getMessage(),500); }
