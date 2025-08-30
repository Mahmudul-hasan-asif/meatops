<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// ---- safe include of db.php ----
$__paths = [
  __DIR__ . '/../db.php',
  dirname(__DIR__) . '/db.php',
  __DIR__ . '/db.php',
  dirname(__DIR__, 2) . '/db.php',
];
$__ok = false;
foreach ($__paths as $__p) { if (is_file($__p)) { require_once $__p; $__ok = true; break; } }
if (!$__ok) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$__paths]); exit; }

// ---- detect PDO / mysqli (same style as animals endpoints) ----
$pdo = (isset($pdo) && $pdo instanceof PDO) ? $pdo
     : (function_exists('getPDO') ? getPDO() : (function_exists('pdo_conn') ? pdo_conn() : null));

$mysqli = null;
foreach (['mysqli','conn','con','db','link','connection'] as $h) {
  if (isset($$h) && $$h instanceof mysqli) { $mysqli = $$h; break; }
}

function run_select($sql, $params = []) {
  global $pdo, $mysqli;

  if ($pdo) {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
  }

  if ($mysqli) {
    $st = $mysqli->prepare($sql);
    if (!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);

    if ($params) {
      $types=''; $vals=[];
      foreach ($params as $v) { $types .= is_int($v)?'i' : (is_float($v)?'d':'s'); $vals[]=$v; }
      $bind = [$types]; foreach ($vals as $i=>$v) { $bind[] = &$vals[$i]; }
      call_user_func_array([$st,'bind_param'],$bind);
    }

    $st->execute();
    $res  = method_exists($st,'get_result') ? $st->get_result() : null;
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $st->close();
    return $rows;
  }

  throw new Exception('db.php included but no PDO/mysqli connection found');
}

try {
  $hours  = max(0, (int)($_GET['hours'] ?? 0));
  $where  = ''; $params = [];
  if ($hours > 0) { $where = "WHERE `last_seen` >= ?"; $params[] = date('Y-m-d H:i:s', time() - $hours*3600); }

  // NOTE: backtick all identifiers that might be reserved; alias signal explicitly
  $rows = run_select(
    "SELECT `id`, `facility`, `device`, `type`, `humidity`, `temp`,
            `signal` AS `signal`, `status`, `last_seen`
       FROM `device_status`
       $where
     ORDER BY `last_seen` DESC, `id` DESC
     LIMIT 500",
    $params
  );

  echo json_encode(['ok'=>true,'data'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
