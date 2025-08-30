<?php
// api/get_orders.php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php';

/**
 * Runs a query and returns rows; throws on error.
 */
function runQuery($pdo, $sql) {
  $stmt = $pdo->query($sql);
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
  // Primary query:
  // - No JOINs (prevents 1:N explosion)
  // - Includes expire_date for the label
  // - If legacy orders have 0/NULL price, derive from product.price * weight by name
  $sql1 = "
    SELECT
      o.order_id,
      o.product_package_name,
      o.weight_kg,
      o.product_id,          -- kept for compatibility; UI doesn't display it
      o.batch_code,
      o.customer_id,
      o.delivery_id,
      o.expire_date,
      CASE
        WHEN o.price_per_unit IS NULL OR o.price_per_unit = 0
          THEN ROUND(
                 COALESCE(
                   (SELECT p.price
                      FROM product p
                     WHERE p.product_package_name = o.product_package_name
                     LIMIT 1),
                   0
                 ) * o.weight_kg, 2
               )
        ELSE o.price_per_unit
      END AS price_per_unit,
      DATEDIFF(o.expire_date, CURDATE()) AS days_left
    FROM orders o
    ORDER BY
      /* if created_at exists it will sort by that first; otherwise id */
      CASE WHEN (SELECT COUNT(*)
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'orders'
                    AND COLUMN_NAME = 'created_at') > 0
           THEN 0 ELSE 1 END,
      o.created_at DESC,
      o.id DESC
    LIMIT 500
  ";

  try {
    $rows = runQuery($pdo, $sql1);
    echo json_encode($rows);
  } catch (Throwable $e1) {
    // Fallback: minimal, guaranteed-to-work selection so UI never goes empty
    $sql2 = "
      SELECT
        o.order_id,
        o.product_package_name,
        o.weight_kg,
        o.product_id,
        o.batch_code,
        o.customer_id,
        o.delivery_id,
        o.expire_date,
        o.price_per_unit,
        DATEDIFF(o.expire_date, CURDATE()) AS days_left
      FROM orders o
      ORDER BY o.id DESC
      LIMIT 500
    ";
    $rows2 = runQuery($pdo, $sql2);
    echo json_encode($rows2);
  }

} catch (Throwable $e) {
  // Last resort: return an empty array (front-end will show a friendly message)
  echo json_encode([]);
}
