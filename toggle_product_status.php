<?php
/**
 * تبديل حالة المنتج بين available و sold (طلب GET بسيط).
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/products_admin.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: admin_products.php');
    exit;
}

$row = hq_admin_get_product($pdo, $id);
if ($row === null) {
    header('Location: admin_products.php');
    exit;
}

$next = ((string) ($row['status'] ?? '')) === 'sold' ? 'available' : 'sold';

$stmt = $pdo->prepare('UPDATE products SET status = :st WHERE id = :id LIMIT 1');
$stmt->execute(['st' => $next, 'id' => $id]);

header('Location: admin_products.php?msg=toggled');
exit;
