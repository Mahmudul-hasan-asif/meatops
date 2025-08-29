<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

$in=json_decode(file_get_contents('php://input'),true) ?: $_POST;

if(!empty($in['product_id'])){
  $st=$pdo->prepare("DELETE FROM product_list WHERE product_id=?");
  $st->execute([$in['product_id']]);
  ok(['product_id'=>$in['product_id']]);
} elseif(!empty($in['id'])) {
  $st=$pdo->prepare("DELETE FROM product_list WHERE id=?");
  $st->execute([intval($in['id'])]);
  ok(['id'=>intval($in['id'])]);
} else {
  fail('product_id or id required');
}
