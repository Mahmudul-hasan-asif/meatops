<?php
// api/_db_autoload.php  — final version
// Loads db.php, finds OR builds a PDO/mysqli handle, then USE `meat_inventory`.
// If discovery fails, you can hard-set the three MEATOPS_* overrides below.

if (!headers_sent()) {
  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');
}

/* ───── OPTIONAL: set these 3 lines once if discovery fails ───── */
// Example DSN for XAMPP/WAMP: host=localhost, db=meat_inventory, UTF-8
// define('MEATOPS_DSN',  'mysql:host=localhost;dbname=meat_inventory;charset=utf8mb4');
// define('MEATOPS_USER', 'root');
// define('MEATOPS_PASS', '');

/* Absolute path to db.php (keeps Windows spaces happy). Change if needed. */
if (!defined('MEATOPS_DB_FILE')) {
  $guess = 'G:\\dbms database\\installed\\htdocs\\meatops\\db.php';
  if (is_file($guess)) define('MEATOPS_DB_FILE', $guess);
}

$TRIED=[]; function _try_load($p){ global $TRIED; if(!$p) return false; $TRIED[]=$p; if(is_file($p)){ require_once $p; return true; } return false; }

/* 1) Load db.php */
$loaded = false;
if (defined('MEATOPS_DB_FILE')) $loaded = _try_load(MEATOPS_DB_FILE);
if (!$loaded) {
  $root = dirname(__DIR__);
  foreach ([
    $root.'/db.php', __DIR__.'/../db.php', __DIR__.'/db.php',
    $root.'/config/db.php', $root.'/includes/db.php', $root.'/database/db.php',
    (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'],'/\\').'/db.php' : null),
  ] as $p) { if (_try_load($p)) { $loaded = true; break; } }
}
if (!$loaded) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'db.php not found','tried'=>$TRIED]); exit; }

/* 2) Try to detect a handle your db.php already created */
$pdo = null; $mysqli = null;
foreach (['pdo','dbh','db','db_pdo','pdo_conn','conn','con'] as $v)
  if (isset($$v) && $$v instanceof PDO) { $pdo = $$v; break; }
if (!$pdo) foreach (['mysqli','conn','con','db','link','connection'] as $v)
  if (isset($$v) && $$v instanceof mysqli) { $mysqli = $$v; break; }

/* scan all globals (covers unknown names) */
if (!$pdo && !$mysqli) {
  foreach ($GLOBALS as $name=>$val) {
    if ($val instanceof PDO)    { $pdo    = $val; break; }
    if ($val instanceof mysqli) { $mysqli = $val; break; }
  }
}

/* 3) Try factories / classes if present */
$factories = ['pdo_conn','getPDO','pdo','get_pdo','db','db_conn','get_connection','getConnection','connect','connect_db','open_db','openConnection','connection'];
foreach ($factories as $fn) {
  if (!$pdo && !$mysqli && function_exists($fn)) {
    try { $h = $fn(); if ($h instanceof PDO) $pdo=$h; elseif ($h instanceof mysqli) $mysqli=$h; } catch(Throwable $e) {}
    if ($pdo || $mysqli) break;
  }
}
if (!$pdo && !$mysqli) {
  $classes = ['Database','DB','Db','Connection','Connector','Mysql','MySQL','PDOFactory'];
  $methods = ['getConnection','connect','open','pdo','getPDO','connection','getDb','db'];
  foreach ($classes as $cls) {
    if (!class_exists($cls)) continue;
    try {
      $obj = new $cls();
      // public properties holding a handle?
      foreach (get_object_vars($obj) as $prop=>$val) {
        if ($val instanceof PDO)    { $pdo=$val; break 2; }
        if ($val instanceof mysqli) { $mysqli=$val; break 2; }
      }
      // common methods that return a handle
      foreach ($methods as $m) if (method_exists($obj,$m)) {
        $h = $obj->$m();
        if ($h instanceof PDO)    { $pdo=$h; break 2; }
        if ($h instanceof mysqli) { $mysqli=$h; break 2; }
      }
    } catch(Throwable $e) {}
  }
}

/* 4) If still no handle, build one. Prefer overrides; then constants/vars. */
function pick_const(...$n){ foreach($n as $k){ if(defined($k) && constant($k)!=='') return constant($k); } return null; }
function pick_var(...$n){ foreach($n as $k){ if(isset($GLOBALS[$k]) && $GLOBALS[$k]!=='') return $GLOBALS[$k]; } return null; }

if (!$pdo && !$mysqli) {
  // If overrides are set, use them now.
  if (defined('MEATOPS_DSN')) {
    try {
      $pdo = new PDO(MEATOPS_DSN, (defined('MEATOPS_USER')?MEATOPS_USER:''), (defined('MEATOPS_PASS')?MEATOPS_PASS:''), [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
      ]);
    } catch(Throwable $e) { $pdo = null; }
  }
}

if (!$pdo && !$mysqli) {
  // Build from constants/vars your db.php might define
  $host = pick_const('DB_HOST','DBHOST','DB_SERVER','HOST')   ?? pick_var('dbhost','host','servername','server','hostname');
  $user = pick_const('DB_USER','DB_USERNAME','DBUSER','USER') ?? pick_var('dbuser','username','user');
  $pass = pick_const('DB_PASS','DB_PASSWORD','DBPASS','PASS') ?? pick_var('dbpass','password','pass');
  $name = pick_const('DB_NAME','DB_DATABASE','DBNAME','NAME') ?? pick_var('dbname','database','db');

  if ($host && $user) {
    // Try PDO first
    try {
      $dsn = 'mysql:host='.$host.';charset=utf8mb4'.($name?';dbname='.$name:'');
      $pdo = new PDO($dsn, (string)$user, (string)$pass, [
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
      ]);
    } catch(Throwable $e) {
      // Fallback to mysqli
      if (class_exists('mysqli')) {
        $mysqli = @new mysqli($host, (string)$user, (string)$pass, $name ?: null);
        if ($mysqli && $mysqli->connect_errno) $mysqli = null;
      }
    }
  }
}

/* 5) Final guard */
if (!$pdo && !$mysqli) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'db.php loaded but no PDO/mysqli connection created']); exit;
}

/* 6) Force database to meat_inventory (safe if already selected) */
try {
  if ($pdo)    { $pdo->query("USE `meat_inventory`"); }
  if ($mysqli) { @$mysqli->select_db('meat_inventory'); }
} catch(Throwable $e) { /* ignore */ }

/* 7) Helpers for endpoints */
function db_select(string $sql, array $params = []) : array {
  global $pdo, $mysqli;
  if ($pdo) { $st=$pdo->prepare($sql); $st->execute($params); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$mysqli->prepare($sql); if(!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);
  if ($params){
    $types=''; $vals=[]; foreach($params as $v){ $types .= is_int($v)?'i' : (is_float($v)?'d' : 's'); $vals[]=$v; }
    $bind=[]; $bind[]=&$types; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; }
    call_user_func_array([$st,'bind_param'],$bind);
  }
  if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception('MySQLi execute failed: '.$err); }
  if(method_exists($st,'get_result')){ $res=$st->get_result(); $rows=$res?$res->fetch_all(MYSQLI_ASSOC):[]; }
  else { $rows=[]; $meta=$st->result_metadata(); if($meta){ $row=[]; $fields=[]; while($f=$meta->fetch_field()){ $fields[]=&$row[$f->name]; } call_user_func_array([$st,'bind_result'],$fields); while($st->fetch()){ $rows[] = array_map(fn($x)=>$x,$row); } } }
  $st->close(); return $rows;
}
function db_exec(string $sql, array $params = []) {
  global $pdo, $mysqli;
  if ($pdo){ $st=$pdo->prepare($sql); $st->execute($params); return $pdo->lastInsertId(); }
  $st=$mysqli->prepare($sql); if(!$st) throw new Exception('MySQLi prepare failed: '.$mysqli->error);
  if ($params){
    $types=''; $vals=[]; foreach($params as $v){ $types .= is_int($v)?'i' : (is_float($v)?'d' : 's'); $vals[]=$v; }
    $bind=[]; $bind[]=&$types; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; }
    call_user_func_array([$st,'bind_param'],$bind);
  }
  if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception('MySQLi execute failed: '.$err); }
  $id=$mysqli->insert_id; $aff=$mysqli->affected_rows; $st->close(); return $id ?: $aff;
}
