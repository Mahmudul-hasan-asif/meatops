<?php
header('Content-Type: application/json');

require_once __DIR__ . '/_db_bootstrap.php'; // uses $pdo OR pdo_conn()
if (!isset($pdo) || !($pdo instanceof PDO)) {
  if (function_exists('pdo_conn')) { $pdo = pdo_conn(); }
}
if (!($pdo instanceof PDO)) {
  echo json_encode(['ok'=>false,'error'=>'No PDO handle from db.php (define $pdo or pdo_conn()).']); exit;
}

try {
  $stmt = $pdo->query("SELECT animal_id, species, category, origin_farm, arrival_date
                         FROM animals
                        ORDER BY animal_id DESC");
  $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true, 'data'=>$data]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
