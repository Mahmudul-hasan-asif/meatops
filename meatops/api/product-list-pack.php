<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

$in=json_decode(file_get_contents('php://input'),true) ?: $_POST;
$batch_code=trim($in['batch_code']??'');
$size=floatval($in['size_kg']??0);
$replace=(int)($in['replace_existing']??0);
if($batch_code==='' || $size<=0) fail('batch_code and size_kg required');

try{
  $pdo->beginTransaction();

  // find batch
  $st=$pdo->prepare("SELECT id,batch_code,product,qty_kg FROM batch WHERE batch_code=?");
  $st->execute([$batch_code]); $b=$st->fetch(PDO::FETCH_ASSOC);
  if(!$b){ $pdo->rollBack(); fail('Batch not found'); }

  // optionally clear existing items for this batch
  if($replace){
    $pdo->prepare("DELETE FROM product_list WHERE batch_code=?")->execute([$batch_code]);
  }

  // determine suffix start
  $st=$pdo->prepare("SELECT MAX(CAST(SUBSTRING(product_id, LOCATE('-PC',product_id)+3) AS UNSIGNED)) AS mx
                     FROM product_list WHERE batch_code=?");
  $st->execute([$batch_code]); $mx=(int)($st->fetchColumn() ?: 0);
  $next=$mx+1;

  // split into packages
  $remaining=(float)$b['qty_kg'];
  $rows=[];
  while($remaining > 0.0001){
    $w   = $remaining >= $size ? $size : round($remaining, 2);
    $pid = $batch_code . '-PC' . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
    $pdo->prepare("INSERT INTO product_list (product_id,batch_id,batch_code,product_name,weight_kg,package_size_kg)
                   VALUES (?,?,?,?,?,?)")
        ->execute([$pid,$b['id'],$batch_code,$b['product'],$w,$size]);
    $rows[]=['product_id'=>$pid,'batch_code'=>$batch_code,'product_name'=>$b['product'],'weight_kg'=>$w,'package_size_kg'=>$size];
    $remaining = round($remaining - $w, 2);
    $next++;
  }

  // mark pushed batch as packed (optional)
  $pdo->prepare("UPDATE pushed_batches SET packed=1 WHERE batch_id=?")->execute([$b['id']]);

  $pdo->commit();
  ok($rows);

}catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); fail($e->getMessage(),500); }
