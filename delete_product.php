<?php
/**
 * حذف منتج عبر GET (?id=).
 * المتصفح يفتح الرابط فقط؛ لذلك نستخدم confirm في الواجهة قبل الحذف.
 * إن وُجدت طلبات مرتبطة بـ product_id، قد يمنع FK الحذف (RESTRICT).
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: admin_products.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM products WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    header('Location: admin_products.php?msg=deleted');
} catch (Throwable $e) {
    header('Location: admin_products.php?msg=delerr');
}
exit;
