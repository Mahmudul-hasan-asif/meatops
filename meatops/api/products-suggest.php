<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

try {
  require_once __DIR__ . '/db.php';
  $pdo    = $GLOBALS['pdo']    ?? (isset($pdo) ? $pdo : null);
  $mysqli = $GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? (isset($mysqli) ? $mysqli : (isset($conn) ? $conn : null)));

  if ($pdo instanceof PDO) {
    $st = $pdo->query("SELECT product_id, product_name FROM product ORDER BY product_id ASC LIMIT 250");
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } elseif ($mysqli instanceof mysqli) {
    $res = $mysqli->query("SELECT product_id, product_name FROM product ORDER BY product_id ASC LIMIT 250");
    if (!$res) throw new Exception($mysqli->error);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = $r;
  } else {
    throw new Exception('db.php loaded, but no PDO or mysqli connection found.');
  }

  echo json_encode(['ok'=>true,'data'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
