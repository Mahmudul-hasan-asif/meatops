<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php'; // your db.php is in /api

function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

try{
  $sql = "SELECT id, batch_code, processing_id, product, qty_kg, expire_date, facility, pushed, created_at, updated_at
          FROM batch ORDER BY id DESC";
  $st = $pdo->query($sql);
  ok($st->fetchAll(PDO::FETCH_ASSOC));
}catch(Throwable $e){ fail($e->getMessage(),500); }