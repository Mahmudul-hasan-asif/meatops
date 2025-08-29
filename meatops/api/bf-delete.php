<?php
header('Content-Type: application/json');
ini_set('display_errors',1); error_reporting(E_ALL);

function get_pdo(){ static $pdo=null; if($pdo) return $pdo;
  $host='127.0.0.1'; $db='meat_inventory'; $user='root'; $pass=''; $charset='utf8mb4';
  $cfg=__DIR__.'/config.php';
  if(file_exists($cfg)){ require_once $cfg;
    $host=defined('DB_HOST')?DB_HOST:$host; $db=defined('DB_NAME')?DB_NAME:$db;
    $user=defined('DB_USER')?DB_USER:$user; $pass=defined('DB_PASS')?DB_PASS:$pass;
  }
  return $pdo=new PDO("mysql:host=$host;dbname=$db;charset=$charset",$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
}
function payload(){
  $ct=$_SERVER['CONTENT_TYPE']??'';
  if(stripos($ct,'application/json')!==false){
    $p=json_decode(file_get_contents('php://input'),true);
    if(is_array($p)) return $p;
  }
  return $_POST;
}

try{
  $d = payload();
  $id = (int)($d['id'] ?? 0);
  if($id<=0){ http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid id']); exit; }

  $pdo = get_pdo();
  $st = $pdo->prepare("DELETE FROM batch_facility WHERE id=:id");
  $st->execute([':id'=>$id]);

  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
