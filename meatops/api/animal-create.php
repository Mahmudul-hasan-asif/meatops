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
$species     = trim($body['species'] ?? '');
$category    = strtolower(trim($body['category'] ?? ''));
$origin_farm = trim($body['origin_farm'] ?? '');
$arrival     = trim($body['arrival_date'] ?? '');

$allowed = ['chicken','beef','mutton','turkey','duck'];
if (!$species) { echo json_encode(['ok'=>false,'error'=>'species is required']); exit; }
if (!in_array($category, $allowed, true)) { echo json_encode(['ok'=>false,'error'=>'invalid category']); exit; }

try {
  $stmt = $pdo->prepare("INSERT INTO animals (species, category, origin_farm, arrival_date)
                         VALUES (?, ?, ?, ?)");
  $stmt->execute([$species, $category, $origin_farm, $arrival]);
  $id = $pdo->lastInsertId();

  $get = $pdo->prepare("SELECT animal_id, species, category, origin_farm, arrival_date
                          FROM animals WHERE animal_id = ?");
  $get->execute([$id]);
  $row = $get->fetch(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'data'=>$row]);
} catch (Throwable $e) {
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
