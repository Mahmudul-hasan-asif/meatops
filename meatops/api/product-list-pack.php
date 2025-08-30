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

  $batch_code   = trim($in['batch_code'] ?? '');
  $batch_pname  = trim($in['batch_product_name'] ?? '');
  $total_qty    = (float)($in['total_qty_kg'] ?? 0);
  $size_kg      = (float)($in['size_kg'] ?? 0);
  $pkg_id       = (int)($in['product_package_id'] ?? 0);
  $replace      = (int)($in['replace_existing'] ?? 0);

  if ($batch_code === '' || $size_kg <= 0 || $total_qty <= 0) throw new Exception('batch_code, total_qty_kg and size_kg are required.');
  if ($pkg_id <= 0) throw new Exception('Valid product_package_id is required.');

  // Lookup package name from product table
  $pkg_name = null;
  if ($pdo instanceof PDO) {
    $st = $pdo->prepare("SELECT product_name FROM product WHERE product_id = :pid LIMIT 1");
    $st->execute([':pid'=>$pkg_id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if ($row) $pkg_name = $row['product_name'];
  } elseif ($mysqli instanceof mysqli) {
    $st = $mysqli->prepare("SELECT product_name FROM product WHERE product_id = ? LIMIT 1");
    $st->bind_param('i', $pkg_id);
    $st->execute();
    $res = $st->get_result();
    if ($r = $res->fetch_assoc()) $pkg_name = $r['product_name'];
  } else { throw new Exception('db.php loaded, but no DB handle found.'); }

  if (!$pkg_name) throw new Exception('Product Package ID not found in product table.');

  $whole = (int)floor($total_qty / $size_kg);
  $rem   = round($total_qty - ($whole * $size_kg), 2);
  $parts = $whole + ($rem > 0 ? 1 : 0);
  if ($parts <= 0) throw new Exception('Nothing to pack with given size.');

  // Begin TX
  if ($pdo instanceof PDO) $pdo->beginTransaction(); elseif ($mysqli instanceof mysqli) $mysqli->begin_transaction();

  // Replace existing rows for this batch if requested
  if ($replace) {
    if ($pdo instanceof PDO) {
      $del = $pdo->prepare("DELETE FROM product_list WHERE batch_code = :bc");
      $del->execute([':bc'=>$batch_code]);
    } else {
      $del = $mysqli->prepare("DELETE FROM product_list WHERE batch_code = ?");
      $del->bind_param('s', $batch_code);
      $del->execute();
    }
  }

  // Find starting index (e.g., BCODE-001, -002, …)
  $startIdx = 0;
  if ($pdo instanceof PDO) {
    $q = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(product_id, '-', -1) AS UNSIGNED)) AS maxidx
                        FROM product_list WHERE product_id LIKE :pref");
    $q->execute([':pref'=>$batch_code.'-%']);
    $startIdx = (int)($q->fetchColumn() ?: 0);
  } else {
    $pref = $batch_code.'-%';
    $q = $mysqli->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(product_id, '-', -1) AS UNSIGNED)) AS maxidx
                           FROM product_list WHERE product_id LIKE ?");
    $q->bind_param('s', $pref);
    $q->execute();
    $res = $q->get_result();
    $startIdx = (int)(($r = $res->fetch_assoc()) ? $r['maxidx'] : 0);
  }

  // Prepare insert
  if ($pdo instanceof PDO) {
    $ins = $pdo->prepare(
      "INSERT INTO product_list
        (product_id, product_package_id, product_package_name, batch_code, product_name, weight_kg, created_at)
       VALUES
        (:product_id, :product_package_id, :product_package_name, :batch_code, :product_name, :weight_kg, NOW())"
    );
  } else {
    $ins = $mysqli->prepare(
      "INSERT INTO product_list
        (product_id, product_package_id, product_package_name, batch_code, product_name, weight_kg, created_at)
       VALUES
        (?, ?, ?, ?, ?, ?, NOW())"
    );
  }

  $created = 0;

  // Full-size packages
  for ($i = 1; $i <= $whole; $i++) {
    $idx = $startIdx + $i;
    $pid = $batch_code . '-' . str_pad((string)$idx, 3, '0', STR_PAD_LEFT);
    if ($pdo instanceof PDO) {
      $ins->execute([
        ':product_id'           => $pid,
        ':product_package_id'   => $pkg_id,
        ':product_package_name' => $pkg_name,
        ':batch_code'           => $batch_code,
        ':product_name'         => $batch_pname,
        ':weight_kg'            => $size_kg
      ]);
    } else {
      $ins->bind_param('sisssd', $pid, $pkg_id, $pkg_name, $batch_code, $batch_pname, $size_kg);
      $ins->execute();
    }
    $created++;
  }

  // Remainder (if any)
  if ($rem > 0) {
    $idx = $startIdx + $whole + 1;
    $pid = $batch_code . '-' . str_pad((string)$idx, 3, '0', STR_PAD_LEFT);
    if ($pdo instanceof PDO) {
      $ins->execute([
        ':product_id'           => $pid,
        ':product_package_id'   => $pkg_id,
        ':product_package_name' => $pkg_name,
        ':batch_code'           => $batch_code,
        ':product_name'         => $batch_pname,
        ':weight_kg'            => $rem
      ]);
    } else {
      $ins->bind_param('sisssd', $pid, $pkg_id, $pkg_name, $batch_code, $batch_pname, $rem);
      $ins->execute();
    }
    $created++;
  }

  if ($pdo instanceof PDO) $pdo->commit(); else $mysqli->commit();

  echo json_encode(['ok'=>true,'created'=>$created,'product_package_name'=>$pkg_name], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
  if (isset($mysqli) && $mysqli instanceof mysqli) { /* mysqli auto-rollback on close; nothing to do */ }
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
