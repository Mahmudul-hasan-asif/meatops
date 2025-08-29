<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

function ok($d){echo json_encode(['ok'=>true,'data'=>$d]);exit;}
function fail($m,$c=400){http_response_code($c);echo json_encode(['ok'=>false,'error'=>$m]);exit;}

$in = json_decode(file_get_contents('php://input'), true);
if(!$in) $in = $_POST;

$id = intval($in['id'] ?? 0);
if(!$id) fail('Missing id.');

try{
  $st = $pdo->prepare("DELETE FROM batch WHERE id=?");
  $st->execute([$id]);
  ok(['id'=>$id]);
}catch(Throwable $e){ fail($e->getMessage(),500); }
