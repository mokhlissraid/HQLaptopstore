<?php
/**
 * حذف طلب (GET). order_items تُحذف تلقائيًا بسبب ON DELETE CASCADE على order_id.
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id < 1) {
    header('Location: admin.php');
    exit;
}

try {
    $stmt = $pdo->prepare('DELETE FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    header('Location: admin.php?msg=order_deleted');
} catch (Throwable $e) {
    header('Location: admin.php?msg=order_delerr');
}
exit;
