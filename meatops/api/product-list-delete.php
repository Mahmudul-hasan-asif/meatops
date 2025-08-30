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
  $pdo    = $GLOBALS['pdo']    ?? (isset($pdo) ? $pdo : null);
  $mysqli = $GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? (isset($mysqli) ? $mysqli : (isset($conn) ? $conn : null)));

  $in = read_json_body();
  $product_id = trim($in['product_id'] ?? '');
  if ($product_id === '') throw new Exception('product_id is required.');

  if ($pdo instanceof PDO) {
    $st = $pdo->prepare("DELETE FROM product_list WHERE product_id = :pid");
    $st->execute([':pid'=>$product_id]);
    $deleted = $st->rowCount();
  } elseif ($mysqli instanceof mysqli) {
    $st = $mysqli->prepare("DELETE FROM product_list WHERE product_id = ?");
    $st->bind_param('s', $product_id);
    $st->execute();
    $deleted = $st->affected_rows;
  } else {
    throw new Exception('db.php loaded, but no PDO or mysqli connection found.');
  }

  echo json_encode(['ok'=>true,'deleted'=>$deleted], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
