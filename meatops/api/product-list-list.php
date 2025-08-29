<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';
function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

try{
  $st=$pdo->query("SELECT id, product_id, batch_id, batch_code, product_name, weight_kg, package_size_kg, created_at
                   FROM product_list ORDER BY id DESC");
  ok($st->fetchAll(PDO::FETCH_ASSOC));
}catch(Throwable $e){ fail($e->getMessage(),500); }
