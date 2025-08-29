<?php
header('Content-Type: application/json');
ini_set('display_errors',1); error_reporting(E_ALL);

function get_pdo(){ static $pdo=null; if($pdo) return $pdo;
  $host='127.0.0.1'; $db='meat_inventory'; $user='root'; $pass=''; $charset='utf8mb4';
  $cfg=__DIR__ . '/config.php';
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
function table_exists(PDO $pdo, $name){
  $q=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $q->execute([$name]); return (bool)$q->fetchColumn();
}
function pick_table(PDO $pdo, array $c){ foreach($c as $t){ if(table_exists($pdo,$t)) return $t; } return $c[0]; }
function col_exists(PDO $pdo,$t,$c){
  $q=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $q->execute([$t,$c]); return (bool)$q->fetchColumn();
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
  $batch_id   = (int)($d['batch_id']??0);
  $product_id = (int)($d['product_id']??0);
  $facility_id= (int)($d['facility_id']??0);
  $location_bin = trim($d['location_bin']??'');
  $qty_on_hand_kg = (float)($d['qty_on_hand_kg']??0);
  $qty_reserved_kg= (float)($d['qty_reserved_kg']??0);
  $expiry_date = trim($d['expiry_date']??'');
  $status = trim($d['status']??'available');
  $lot_code = trim($d['lot_code']??'');

  if(!$batch_id || !$product_id || !$facility_id || !$expiry_date){
    http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Missing required fields']); exit;
  }
  if($lot_code===''){ $lot_code = 'LOT-' . $batch_id; }

  $pdo = get_pdo();
  $pdo->prepare("INSERT INTO batch_facility
    (batch_id, product_id, facility_id, lot_code, location_bin, qty_on_hand_kg, qty_reserved_kg, expiry_date, status)
    VALUES (?,?,?,?,?,?,?,?,?)")
      ->execute([$batch_id,$product_id,$facility_id,$lot_code,$location_bin,$qty_on_hand_kg,$qty_reserved_kg,$expiry_date,$status]);

  $id = (int)$pdo->lastInsertId();

  $productTable  = pick_table($pdo, ['product','products']);
  $facilityTable = pick_table($pdo, ['facility','storage_facility','facilities']);
  $productNameCol  = col_exists($pdo,$productTable,'product_name') ? 'product_name' :
                     (col_exists($pdo,$productTable,'name') ? 'name' : 'product_id');
  $facilityNameCol = col_exists($pdo,$facilityTable,'location') ? 'location' :
                     (col_exists($pdo,$facilityTable,'name') ? 'name' : 'facility_id');

  $q=$pdo->prepare("
    SELECT bf.id AS bf_id, bf.batch_id, bf.lot_code,
           p.`$productNameCol` AS product_name,
           f.`$facilityNameCol` AS facility_name,
           bf.location_bin, bf.qty_on_hand_kg, bf.qty_reserved_kg, bf.expiry_date, bf.status
    FROM batch_facility bf
    JOIN `$productTable`  p ON p.product_id=bf.product_id
    JOIN `$facilityTable` f ON f.facility_id=bf.facility_id
    WHERE bf.id=:id
  ");
  $q->execute([':id'=>$id]);
  echo json_encode(['ok'=>true,'data'=>$q->fetch()]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
