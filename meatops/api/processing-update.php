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

  $animal_id       = isset($in['animal_id']) ? (int)$in['animal_id'] : null;
  $cut_type        = trim($in['cut_type'] ?? '');
  $yield_kg        = isset($in['yield_kg']) ? (float)$in['yield_kg'] : 0;
  $loss_kg         = isset($in['loss_kg']) ? (float)$in['loss_kg'] : 0;
  $location        = trim($in['location'] ?? '');
  $processing_date = trim($in['processing_date'] ?? '');
  $status          = trim($in['status'] ?? 'pending');

  if ($cut_type === '')        throw new Exception('cut_type is required');
  if ($processing_date === '') throw new Exception('processing_date is required');

  $stmt = $pdo->prepare("UPDATE processing_units
    SET animal_id=?, cut_type=?, yield_kg=?, loss_kg=?, location=?, processing_date=?, status=?
    WHERE id=?");
  $stmt->execute([$animal_id, $cut_type, $yield_kg, $loss_kg, $location, $processing_date, $status, $id]);

  $row = $pdo->prepare("SELECT id, animal_id, cut_type, yield_kg, loss_kg, location,
                               DATE_FORMAT(processing_date, '%Y-%m-%d') AS processing_date,
                               status
                        FROM processing_units WHERE id=?");
  $row->execute([$id]);
  echo json_encode(['ok'=>true,'data'=>$row->fetch()]);
}catch(Throwable $e){
  http_response_code(200);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
