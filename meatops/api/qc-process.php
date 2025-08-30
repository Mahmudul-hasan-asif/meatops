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

/**
 * Bulk insert sales for a batch_code:
 * INSERT IGNORE INTO sale (product_id, batch_code, product_name, weight, price)
 * SELECT pl.product_id, pl.batch_code, pl.product_name, pl.weight_kg, p.price
 * FROM product_list pl
 * JOIN product p ON p.product_id = pl.product_package_id
 * WHERE pl.batch_code = :bc
 * Returns ['inserted'=>N, 'expire_date'=>..., 'product_package_name'=>...]
 */
function add_sales_for_batch(PDO|mysqli $db, string $batch_code): array {
  $inserted = 0; $expire_date = null; $pkg_name = null;

  // expire_date from batch table (change to `batches` if that’s your name)
  if ($db instanceof PDO) {
    $s1 = $db->prepare("SELECT expire_date FROM batch WHERE batch_code = :bc LIMIT 1");
    $s1->execute([':bc'=>$batch_code]);
    $expire_date = ($row = $s1->fetch(PDO::FETCH_ASSOC)) ? ($row['expire_date'] ?? null) : null;

    $s2 = $db->prepare("SELECT product_package_name FROM product_list WHERE batch_code = :bc AND product_package_name IS NOT NULL AND product_package_name<>'' LIMIT 1");
    $s2->execute([':bc'=>$batch_code]);
    $pkg_name = ($r = $s2->fetch(PDO::FETCH_ASSOC)) ? ($r['product_package_name'] ?? null) : null;

    $sql = "INSERT IGNORE INTO sale (product_id, batch_code, product_name, weight, price)
            SELECT pl.product_id, pl.batch_code, pl.product_name, pl.weight_kg, p.price
            FROM product_list pl
            JOIN product p ON p.product_id = pl.product_package_id
            WHERE pl.batch_code = :bc";
    $ins = $db->prepare($sql);
    $ins->execute([':bc'=>$batch_code]);
    $inserted = $ins->rowCount();
  } else {
    $s1 = $db->prepare("SELECT expire_date FROM batch WHERE batch_code = ? LIMIT 1");
    $s1->bind_param('s', $batch_code); $s1->execute();
    $res1 = $s1->get_result(); if ($r1 = $res1->fetch_assoc()) $expire_date = $r1['expire_date'] ?? null;

    $s2 = $db->prepare("SELECT product_package_name FROM product_list WHERE batch_code = ? AND product_package_name IS NOT NULL AND product_package_name<>'' LIMIT 1");
    $s2->bind_param('s', $batch_code); $s2->execute();
    $res2 = $s2->get_result(); if ($r2 = $res2->fetch_assoc()) $pkg_name = $r2['product_package_name'] ?? null;

    $sql = "INSERT IGNORE INTO sale (product_id, batch_code, product_name, weight, price)
            SELECT pl.product_id, pl.batch_code, pl.product_name, pl.weight_kg, p.price
            FROM product_list pl
            JOIN product p ON p.product_id = pl.product_package_id
            WHERE pl.batch_code = ?";
    $ins = $db->prepare($sql);
    $ins->bind_param('s', $batch_code);
    $ins->execute();
    $inserted = $ins->affected_rows;
  }

  return ['inserted'=>$inserted, 'expire_date'=>$expire_date, 'product_package_name'=>$pkg_name];
}

try {
  require_once __DIR__ . '/db.php';
  $pdo    = $GLOBALS['pdo']    ?? null;
  $mysqli = $GLOBALS['mysqli'] ?? ($GLOBALS['conn'] ?? null);

  $in = read_json_body();
  $id      = isset($in['id']) ? (int)$in['id'] : 0;        // quality_control.id
  $wait_id = isset($in['wait_id']) ? (int)$in['wait_id'] : 0; // qc_waiting_review.id
  $quality = trim($in['quality'] ?? 'pause');

  if (!$id && !$wait_id) throw new Exception('id or wait_id is required.');

  $handler = $pdo instanceof PDO ? $pdo : ($mysqli instanceof mysqli ? $mysqli : null);
  if (!$handler) throw new Exception('db.php loaded, but no PDO/mysqli connection found.');

  // 1) from QC queue
  if ($id) {
    if ($pdo instanceof PDO) {
      $s = $pdo->prepare("SELECT * FROM quality_control WHERE id = :id LIMIT 1");
      $s->execute([':id'=>$id]);
      $row = $s->fetch(PDO::FETCH_ASSOC);
    } else {
      $s = $mysqli->prepare("SELECT * FROM quality_control WHERE id = ? LIMIT 1");
      $s->bind_param('i', $id); $s->execute(); $res=$s->get_result(); $row = $res->fetch_assoc();
    }
    if (!$row) throw new Exception('QC row not found.');
    $batch_code = $row['batch_code'];

    if ($quality === 'pause') {
      // move to waiting
      if ($pdo instanceof PDO) {
        $w = $pdo->prepare("INSERT INTO qc_waiting_review (batch_code, time, inspector, quality, created_at) VALUES (:b,:t,:i,'passed',NOW())");
        $w->execute([':b'=>$batch_code, ':t'=>$row['time'] ?? null, ':i'=>$row['inspector'] ?? null]);
        $d = $pdo->prepare("DELETE FROM quality_control WHERE id = :id");
        $d->execute([':id'=>$id]);
      } else {
        $w = $mysqli->prepare("INSERT INTO qc_waiting_review (batch_code, time, inspector, quality, created_at) VALUES (?,?,?,'passed',NOW())");
        $t = $row['time'] ?? null; $i = $row['inspector'] ?? null;
        $w->bind_param('sss', $batch_code, $t, $i); $w->execute();
        $d = $mysqli->prepare("DELETE FROM quality_control WHERE id = ?");
        $d->bind_param('i', $id); $d->execute();
      }
      echo json_encode(['ok'=>true,'moved_to_waiting'=>true], JSON_UNESCAPED_UNICODE); exit;
    }

    if ($quality === 'passed') {
      $result = add_sales_for_batch($handler, $batch_code);
      // remove qc row
      if ($pdo instanceof PDO) {
        $d = $pdo->prepare("DELETE FROM quality_control WHERE id = :id");
        $d->execute([':id'=>$id]);
      } else {
        $d = $mysqli->prepare("DELETE FROM quality_control WHERE id = ?");
        $d->bind_param('i', $id); $d->execute();
      }
      echo json_encode(['ok'=>true,'batch_code'=>$batch_code] + $result, JSON_UNESCAPED_UNICODE); exit;
    }

    // quality === 'fail' (just delete)
    if ($pdo instanceof PDO) {
      $d = $pdo->prepare("DELETE FROM quality_control WHERE id = :id");
      $d->execute([':id'=>$id]);
    } else {
      $d = $mysqli->prepare("DELETE FROM quality_control WHERE id = ?");
      $d->bind_param('i', $id); $d->execute();
    }
    echo json_encode(['ok'=>true,'deleted'=>true], JSON_UNESCAPED_UNICODE); exit;
  }

  // 2) from Waiting list
  if ($wait_id) {
    if ($pdo instanceof PDO) {
      $s = $pdo->prepare("SELECT * FROM qc_waiting_review WHERE id = :id LIMIT 1");
      $s->execute([':id'=>$wait_id]);
      $row = $s->fetch(PDO::FETCH_ASSOC);
    } else {
      $s = $mysqli->prepare("SELECT * FROM qc_waiting_review WHERE id = ? LIMIT 1");
      $s->bind_param('i', $wait_id); $s->execute(); $res=$s->get_result(); $row = $res->fetch_assoc();
    }
    if (!$row) throw new Exception('Waiting row not found.');
    $batch_code = $row['batch_code'];

    if ($quality === 'passed') {
      $result = add_sales_for_batch($handler, $batch_code);
      // remove waiting row
      if ($pdo instanceof PDO) {
        $d = $pdo->prepare("DELETE FROM qc_waiting_review WHERE id = :id");
        $d->execute([':id'=>$wait_id]);
      } else {
        $d = $mysqli->prepare("DELETE FROM qc_waiting_review WHERE id = ?");
        $d->bind_param('i', $wait_id); $d->execute();
      }
      echo json_encode(['ok'=>true,'batch_code'=>$batch_code] + $result, JSON_UNESCAPED_UNICODE); exit;
    }

    // fail => remove from waiting
    if ($pdo instanceof PDO) {
      $d = $pdo->prepare("DELETE FROM qc_waiting_review WHERE id = :id");
      $d->execute([':id'=>$wait_id]);
    } else {
      $d = $mysqli->prepare("DELETE FROM qc_waiting_review WHERE id = ?");
      $d->bind_param('i', $wait_id); $d->execute();
    }
    echo json_encode(['ok'=>true,'deleted'=>true], JSON_UNESCAPED_UNICODE); exit;
  }

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}