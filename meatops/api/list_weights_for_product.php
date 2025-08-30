<?php
// api/list_weights_for_product.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$name = $_GET['product_package_name'] ?? '';
if ($name === '') { echo json_encode([]); exit; }

try {
  $sql = "SELECT DISTINCT weight_kg
            FROM product_list
           WHERE product_package_name = :name
        ORDER BY weight_kg ASC";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':name' => $name]);

  $weights = [];
  foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    // return as numbers; the UI will format to 2 decimals
    $weights[] = (float)$row['weight_kg'];
  }
  echo json_encode($weights);
} catch (Throwable $e) {
  // keep UI safe; you can log $e->getMessage() server-side if needed
  echo json_encode([]);
}
