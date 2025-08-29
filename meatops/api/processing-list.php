<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/db.php';
if (!isset($pdo) && function_exists('pdo_conn')) { $pdo = pdo_conn(); }

function ensure_processing_table($pdo){
  $pdo->exec("CREATE TABLE IF NOT EXISTS processing_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NULL,
    cut_type VARCHAR(100) NOT NULL,
    yield_kg DECIMAL(10,2) DEFAULT 0,
    loss_kg DECIMAL(10,2) DEFAULT 0,
    location VARCHAR(120) DEFAULT NULL,
    processing_date DATE DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
try{
  ensure_processing_table($pdo);
  $stmt = $pdo->query("SELECT id, animal_id, cut_type, yield_kg, loss_kg, location,
                              DATE_FORMAT(processing_date, '%Y-%m-%d') AS processing_date,
                              status
                       FROM processing_units
                       ORDER BY id DESC");
  echo json_encode(['ok'=>true,'data'=>$stmt->fetchAll()]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
