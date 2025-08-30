<?php
// /api/db_connect.php — robust DB bootstrap for meat_inventory
declare(strict_types=1);

if (!headers_sent()) {
  header('Content-Type: application/json');
  header('Access-Control-Allow-Origin: *');
}

// ===== ONE-TIME OVERRIDES (uncomment & fill if discovery fails) =====
// define('MEATOPS_DSN',  'mysql:host=localhost;dbname=meat_inventory;charset=utf8mb4');
// define('MEATOPS_USER', 'root');
// define('MEATOPS_PASS', '');

// If your db.php is at a known absolute path, set it here (Windows OK):
// define('MEATOPS_DB_FILE', 'G:\\dbms database\\installed\\htdocs\\meatops\\db.php');

// Prevent mysqli from throwing fatals on connect errors; we’ll surface JSON instead.
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

$TRIED = [];
function _try_load($p){ global $TRIED; if(!$p) return false; $TRIED[]=$p; if(is_file($p)){ require_once $p; return true; } return false; }

// 1) Include db.php if present (but do not depend on it to create a handle)
$loaded=false;
if (defined('MEATOPS_DB_FILE')) $loaded=_try_load(MEATOPS_DB_FILE);
if(!$loaded){
  $root=dirname(__DIR__);
  foreach ([$root.'/db.php', __DIR__.'/../db.php', __DIR__.'/db.php', $root.'/includes/db.php', $root.'/config/db.php'] as $p){
    if(_try_load($p)){ $loaded=true; break; }
  }
}

// 2) Reuse an existing handle if db.php created one
$pdo=null; $mysqli=null;
foreach(['pdo','dbh','db','db_pdo','pdo_conn','conn','con'] as $v){ if(isset($$v) && $$v instanceof PDO){ $pdo=$$v; break; } }
if(!$pdo) foreach(['mysqli','conn','con','db','link','connection'] as $v){ if(isset($$v) && $$v instanceof mysqli){ $mysqli=$$v; break; } }
if(!$pdo && !$mysqli){
  foreach($GLOBALS as $g){ if($g instanceof PDO){ $pdo=$g; break; } if($g instanceof mysqli){ $mysqli=$g; break; } }
}
foreach(['pdo_conn','getPDO','pdo','get_pdo','db','db_conn','get_connection','getConnection','connect','connect_db','open_db'] as $fn){
  if(!$pdo && !$mysqli && function_exists($fn)){
    try{ $h=$fn(); if($h instanceof PDO){ $pdo=$h; } elseif($h instanceof mysqli){ $mysqli=$h; } }catch(Throwable $e){}
  }
  if($pdo||$mysqli) break;
}

// Helpers to pick creds from many places
function pick_const(...$n){ foreach($n as $k){ if(defined($k)){ $v=constant($k); if($v!=='' && $v!==null) return $v; } } return null; }
function pick_var(...$n){ foreach($n as $k){ if(isset($GLOBALS[$k])){ $v=$GLOBALS[$k]; if($v!=='' && $v!==null) return $v; } } return null; }
function pick_env(...$n){ foreach($n as $k){ $v=getenv($k); if($v!==false && $v!=='') return $v; } return null; }

// 3) If still no handle, build one — prefer explicit overrides above
if(!$pdo && !$mysqli){
  $dsn  = defined('MEATOPS_DSN')  ? MEATOPS_DSN  : null;
  $user = defined('MEATOPS_USER') ? MEATOPS_USER : null;
  $pass = defined('MEATOPS_PASS') ? MEATOPS_PASS : null;

  // If overrides not set, discover from db.php constants/vars or environment
  if(!$dsn){
    $host = pick_const('DB_HOST','DBHOST','DB_SERVER','HOST')
         ?? pick_var ('servername','server','host','hostname','dbhost')
         ?? pick_env ('DB_HOST','MYSQL_HOST','CLEARDB_DATABASE_HOST','JAWSDB_HOST');
    $name = pick_const('DB_NAME','DB_DATABASE','DBNAME','NAME')
         ?? pick_var ('dbname','database','db')
         ?? pick_env ('DB_NAME','MYSQL_DATABASE','CLEARDB_DATABASE_DB','JAWSDB_DATABASE')
         ?? 'meat_inventory';
    if($host){
      $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    }
    if($user===null){
      $user = pick_const('DB_USER','DB_USERNAME','DBUSER','USER')
           ?? pick_var ('username','user','dbuser')
           ?? pick_env ('DB_USER','MYSQL_USER','CLEARDB_DATABASE_USER','JAWSDB_USERNAME');
    }
    if($pass===null){
      $pass = pick_const('DB_PASS','DB_PASSWORD','DBPASS','PASS')
           ?? pick_var ('password','pass','dbpass')
           ?? pick_env ('DB_PASS','MYSQL_PASSWORD','CLEARDB_DATABASE_PASSWORD','JAWSDB_PASSWORD')
           ?? '';
    }
  }

  // Guard: require DSN + NON-EMPTY username
  if(!$dsn || $user===null || $user===''){
    http_response_code(500);
    echo json_encode([
      'ok'=>false,
      'error'=>'DB credentials not found. Set MEATOPS_DSN/MEATOPS_USER/MEATOPS_PASS at top of api/db_connect.php or ensure db.php defines DB_HOST/DB_USER/DB_PASS/DB_NAME (or $servername/$username/$password/$dbname).',
      'tried'=>$GLOBALS['TRIED']
    ]);
    exit;
  }

  // Try PDO first; if driver missing, try mysqli; report clean JSON on failure
  try{
    $pdo = new PDO($dsn, (string)$user, (string)$pass, [
      PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
  }catch(Throwable $e){
    // Parse host & db from DSN for mysqli
    $host='localhost'; $name='meat_inventory';
    if (preg_match('~host=([^;]+)~i',$dsn,$m)) $host=$m[1];
    if (preg_match('~dbname=([^;]+)~i',$dsn,$m)) $name=$m[1];
    $mysqli = @new mysqli($host, (string)$user, (string)$pass, $name);
    if(!$mysqli || $mysqli->connect_errno){
      http_response_code(500);
      echo json_encode(['ok'=>false,'error'=>'DB connect failed (PDO+MySQLi). Please verify host/user/pass/db.']);
      exit;
    }
  }
}

// Ensure database
try{ if($pdo)    $pdo->query("USE `meat_inventory`"); }catch(Throwable $e){}
try{ if($mysqli) @$mysqli->select_db('meat_inventory'); }catch(Throwable $e){}

// Query helpers
function db_all(string $sql,array $p=[]):array{
  global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($sql); $st->execute($p); return $st->fetchAll(PDO::FETCH_ASSOC); }
  $st=$mysqli->prepare($sql); if(!$st){ throw new Exception('MySQLi prepare failed: '.$mysqli->error); }
  if($p){ $types=''; $vals=[]; foreach($p as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $vals[]=$v; }
    $bind=[]; $bind[]=&$types; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
  if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception('MySQLi execute failed: '.$err); }
  $res=method_exists($st,'get_result')?$st->get_result():null; $rows=$res?$res->fetch_all(MYSQLI_ASSOC):[]; $st->close(); return $rows;
}
function db_exec(string $sql,array $p=[]){
  global $pdo,$mysqli;
  if($pdo){ $st=$pdo->prepare($sql); $st->execute($p); return $pdo->lastInsertId(); }
  $st=$mysqli->prepare($sql); if(!$st){ throw new Exception('MySQLi prepare failed: '.$mysqli->error); }
  if($p){ $types=''; $vals=[]; foreach($p as $v){ $types.=is_int($v)?'i':(is_float($v)?'d':'s'); $vals[]=$v; }
    $bind=[]; $bind[]=&$types; foreach($vals as $i=>$v){ $bind[]=&$vals[$i]; } call_user_func_array([$st,'bind_param'],$bind); }
  if(!$st->execute()){ $err=$st->error; $st->close(); throw new Exception('MySQLi execute failed: '.$err); }
  $id=$mysqli->insert_id; $aff=$mysqli->affected_rows; $st->close(); return $id?:$aff;
}
