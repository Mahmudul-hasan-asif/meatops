<?php
header('Content-Type: application/json');

require_once __DIR__ . '/_db_bootstrap.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('pdo_conn')) { $pdo = pdo_conn(); }
}
if (!($pdo instanceof PDO)) {
  echo json_encode(['ok'=>false,'error'=>'No PDO handle from db.php (define $pdo or pdo_conn()).']); exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$id = $body['animal_id'] ?? null;
if (!$id) { echo json_encode(['ok'=>false,'error'=>'animal_id is required']); exit; }

try {
  $stmt = $pdo->prepare("DELETE FROM animals WHERE animal_id = ?");
  $stmt->execute([$id]);
  echo json_encode(['ok'=>true, 'data'=>['animal_id'=>$id]]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
