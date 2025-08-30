<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

$name   = $_GET['product_package_name'] ?? '';
$weight = $_GET['weight_kg'] ?? '';

if ($name === '' || $weight === '') { echo json_encode(new stdClass()); exit; }

try {
  $sql = "
    SELECT
      pl.id           AS pl_id,
      pl.product_id,                -- your code (e.g., BCH-001-001)
      pl.product_package_id,        -- numeric product PK (links to product)
      pl.batch_code,
      ROUND(COALESCE(p.price,0) * pl.weight_kg, 2) AS price_per_unit,
      DATEDIFF(b.expire_date, CURDATE()) AS days_left
    FROM product_list pl
    LEFT JOIN product p ON p.product_id = pl.product_package_id
    LEFT JOIN batch   b ON b.batch_code  = pl.batch_code
    WHERE pl.product_package_name = :name
      AND pl.weight_kg = :w
    ORDER BY pl.batch_code ASC, pl.product_package_id ASC, pl.id ASC
    LIMIT 1
  ";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':name'=>$name, ':w'=>$weight]);
  echo json_encode($stmt->fetch(PDO::FETCH_ASSOC) ?: new stdClass());
} catch (Throwable $e) {
  echo json_encode(new stdClass());
}
