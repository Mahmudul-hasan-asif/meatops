<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/db.php';
if (!isset($pdo) && function_exists('pdo_conn')) { $pdo = pdo_conn(); }

function body_json(){
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '[]', true);
  return is_array($j) ? $j : [];
}

try{
  $in = body_json();
  $id = isset($in['id']) ? (int)$in['id'] : 0;
  if ($id <= 0) throw new Exception('id is required');

  $stmt = $pdo->prepare("DELETE FROM processing_units WHERE id=?");
  $stmt->execute([$id]);

  echo json_encode(['ok'=>true,'data'=>['id'=>$id]]);
}catch(Throwable $e){
  http_response_code(200);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
