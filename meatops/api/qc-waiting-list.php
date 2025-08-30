<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__ . '/db.php';
  $pdo    = $GLOBALS['pdo']    ?? null;
  $mysqli = $GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? null);

  $sql = "SELECT id, batch_code, time, inspector, quality, created_at
          FROM qc_waiting_review
          ORDER BY id DESC";

  if ($pdo instanceof PDO) {
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  } elseif ($mysqli instanceof mysqli) {
    $res = $mysqli->query($sql);
    if (!$res) throw new Exception($mysqli->error);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
  } else throw new Exception('db.php loaded, but no PDO/mysqli connection found.');

  echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}