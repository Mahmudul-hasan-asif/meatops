<?php
header('Content-Type: application/json');

/* Make errors visible while you debug */
ini_set('display_errors', 1);
error_reporting(E_ALL);

function get_pdo() {
  static $pdo = null; if ($pdo) return $pdo;
  $host='127.0.0.1'; $db='meat_inventory'; $user='root'; $pass=''; $charset='utf8mb4';
  $cfg = __DIR__ . '/config.php';
  if (file_exists($cfg)) { require_once $cfg;
    $host = defined('DB_HOST')?DB_HOST:$host;
    $db   = defined('DB_NAME')?DB_NAME:$db;
    $user = defined('DB_USER')?DB_USER:$user;
    $pass = defined('DB_PASS')?DB_PASS:$pass;
  }
  $dsn="mysql:host=$host;dbname=$db;charset=$charset";
  return $pdo = new PDO($dsn,$user,$pass,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
}

function table_exists(PDO $pdo, $name){
  $q=$pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $q->execute([$name]); return (bool)$q->fetchColumn();
}
function pick_table(PDO $pdo, array $candidates){
  foreach($candidates as $t){ if(table_exists($pdo,$t)) return $t; }
  return $candidates[0];
}
function col_exists(PDO $pdo, $table, $col){
  $q=$pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $q->execute([$table,$col]); return (bool)$q->fetchColumn();
}

try{
  $pdo = get_pdo();

  // Detect actual table names present in your DB
  $productTable  = pick_table($pdo, ['product','products']);
  $facilityTable = pick_table($pdo, ['facility','storage_facility','facilities']);

  // Detect the right "name" columns
  $productNameCol  = col_exists($pdo,$productTable,'product_name') ? 'product_name' :
                     (col_exists($pdo,$productTable,'name') ? 'name' : 'product_id');

  $facilityNameCol = col_exists($pdo,$facilityTable,'location') ? 'location' :
                     (col_exists($pdo,$facilityTable,'name') ? 'name' : 'facility_id');

  // Build query safely with detected identifiers
  $sql = "
    SELECT
      bf.id AS bf_id,
      bf.batch_id,
      bf.lot_code,
      p.`$productNameCol` AS product_name,
      f.`$facilityNameCol` AS facility_name,
      bf.location_bin,
      bf.qty_on_hand_kg,
      bf.qty_reserved_kg,
      bf.expiry_date,
      bf.status
    FROM batch_facility bf
    JOIN `$productTable`  p ON p.product_id = bf.product_id
    JOIN `$facilityTable` f ON f.facility_id = bf.facility_id
    ORDER BY bf.expiry_date ASC, bf.id DESC
  ";

  $rows = $pdo->query($sql)->fetchAll();
  echo json_encode(['ok'=>true,'data'=>$rows]);
}catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
