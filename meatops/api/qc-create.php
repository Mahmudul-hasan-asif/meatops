<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === '' || $raw === false) return [];
  $j = json_decode($raw, true);
  if (!is_array($j)) throw new Exception('Invalid JSON body.');
  return $j;
}

try {
  require_once __DIR__ . '/db.php';
  $pdo    = $GLOBALS['pdo']    ?? null;
  $mysqli = $GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? null);

  $in = read_json_body();
  $batch_code = trim($in['batch_code'] ?? '');
  $time       = trim($in['time'] ?? '');
  $inspector  = trim($in['inspector'] ?? '');
  $quality    = trim($in['quality'] ?? 'passed');

  if ($batch_code==='') throw new Exception('batch_code is required.');

  if ($pdo instanceof PDO) {
    $st = $pdo->prepare("INSERT INTO quality_control (batch_code, time, inspector, quality) VALUES (:b,:t,:i,:q)");
    $st->execute([':b'=>$batch_code, ':t'=>$time ?: null, ':i'=>$inspector, ':q'=>$quality]);
    $id = (int)$pdo->lastInsertId();
  } elseif ($mysqli instanceof mysqli) {
    $st = $mysqli->prepare("INSERT INTO quality_control (batch_code, time, inspector, quality) VALUES (?,?,?,?)");
    $st->bind_param('ssss', $batch_code, $time, $inspector, $quality);
    $st->execute(); $id = $st->insert_id;
  } else throw new Exception('db.php loaded, but no PDO/mysqli connection found.');

  echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}