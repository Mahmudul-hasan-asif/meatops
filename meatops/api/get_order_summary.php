<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

try {
  $sql = "
    SELECT
      pl.product_package_name,
      pl.weight_kg,
      COUNT(*) AS quantity,
      ROUND(COALESCE(p.price,0) * pl.weight_kg, 2) AS price_per_unit,
      MIN(DATEDIFF(b.expire_date, CURDATE())) AS days_left
    FROM product_list pl
    LEFT JOIN product p ON p.product_id = pl.product_package_id
    LEFT JOIN batch   b ON b.batch_code  = pl.batch_code
    GROUP BY pl.product_package_name, pl.weight_kg
    ORDER BY pl.product_package_name ASC, pl.weight_kg ASC
  ";
  $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode($rows);
} catch (Throwable $e) {
  echo json_encode([]);  // keep UI safe
}
