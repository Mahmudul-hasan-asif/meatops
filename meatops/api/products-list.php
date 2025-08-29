<?php
header('Content-Type: application/json');

function get_pdo() {
  static $pdo = null;
  if ($pdo) return $pdo;

  // Defaults for XAMPP
  $host = '127.0.0.1';
  $db   = 'meat_inventory';
  $user = 'root';
  $pass = '';
  $charset = 'utf8mb4';

  // If a config.php exists with DB_* constants, use them
  $cfg = __DIR__ . '/config.php';
  if (file_exists($cfg)) {
    require_once $cfg;
    if (defined('DB_HOST')) { $host = DB_HOST; }
    if (defined('DB_NAME')) { $db   = DB_NAME; }
    if (defined('DB_USER')) { $user = DB_USER; }
    if (defined('DB_PASS')) { $pass = DB_PASS; }
  }

  $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
  $opt = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
  ];
  return $pdo = new PDO($dsn, $user, $pass, $opt);
}

try {
  $pdo = get_pdo();
  // Include shelf_life so the UI can display it (replacing Stock column).
  $stmt = $pdo->query("
    SELECT 
      p.product_id,
      p.product_name,
      p.price,
      p.quantity,
      p.sale,
      p.shelf_life,
      p.start_date
    FROM product p
    ORDER BY p.product_id DESC
  ");
  $rows = $stmt->fetchAll();

  // Cast numeric fields so frontend doesn't choke
  foreach ($rows as &$r) {
    $r['product_id'] = (int)$r['product_id'];
    $r['price']      = $r['price'] !== null ? (float)$r['price'] : 0.0;
    $r['quantity']   = (int)$r['quantity'];
    $r['sale']       = (int)$r['sale'];
    $r['shelf_life'] = isset($r['shelf_life']) ? (int)$r['shelf_life'] : 0;
    // start_date stays as string (YYYY-MM-DD)
  }

  echo json_encode(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
