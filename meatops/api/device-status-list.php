<?php
// api/device-status-list.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
  // Use the same bootstrap pattern as your other endpoints
  require_once __DIR__ . '/../db.php';

  // Detect PDO or mysqli from db.php
  $pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
  $mysqli = null;
  foreach (['mysqli','conn','con','db'] as $h) {
    if (isset($$h) && $$h instanceof mysqli) { $mysqli = $$h; break; }
  }
  if (!$pdo && function_exists('pdo_conn')) { $pdo = pdo_conn(); }   // if your db.php exposes this

  // Guard
  if (!$pdo && !$mysqli) {
    throw new Exception('No DB connection from db.php');
  }

  // Optional filter: last X hours
  $hours  = max(0, (int)($_GET['hours'] ?? 0));
  $where  = '';
  $params = [];

  if ($hours > 0) {
    $where   = "WHERE last_seen >= ?";
    $params[] = date('Y-m-d H:i:s', time() - $hours * 3600);
  }

  // Note: `signal` is a reserved word -> backtick it
  $sql = "
    SELECT
      id, facility, device, type, humidity, temp, `signal` AS signal, status, last_seen
    FROM device_status
    $where
    ORDER BY last_seen DESC, id DESC
    LIMIT 500
  ";

  // Run (PDO or mysqli)
  if ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $st = $mysqli->prepare($sql);
    if (!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);

    if ($params) {
      // Build types string for bind_param
      $types = '';
      $vals  = [];
      foreach ($params as $v) {
        $types .= (is_int($v) ? 'i' : (is_float($v) ? 'd' : 's'));
        $vals[] = $v;
      }
      // bind_param needs references
      $bind = []; $bind[] = &$types;
      foreach ($vals as $i => $v) { $bind[] = &$vals[$i]; }
      call_user_func_array([$st, 'bind_param'], $bind);
    }

    if (!$st->execute()) {
      $err = $st->error; $st->close();
      throw new Exception('MySQLi execute failed: '.$err);
    }

    if (method_exists($st, 'get_result')) {
      $res  = $st->get_result();
      $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } else {
      // Fallback if mysqlnd isn’t available
      $rows = [];
      $meta = $st->result_metadata();
      if ($meta) {
        $row = []; $fields = [];
        while ($f = $meta->fetch_field()) { $fields[] = &$row[$f->name]; }
        call_user_func_array([$st,'bind_result'], $fields);
        while ($st->fetch()) { $rows[] = array_map(fn($x)=>$x, $row); }
      }
    }
    $st->close();
  }

  echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
