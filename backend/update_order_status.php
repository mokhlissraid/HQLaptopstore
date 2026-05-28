<?php
/**
 * تحديث حالة الطلب (POST): completed أو cancelled.
 *
 * عند «تم التسليم» (completed) وكانت الحالة السابقة new:
 *   نخصم من products.stock كمية كل بند في order_items (نفس product_id).
 * لا نخصم مرتين إن أُعيد إرسال الطلب وهو مكتمل مسبقًا.
 */
declare(strict_types=1);

require_once __DIR__ . '/../admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin.php');
    exit;
}

$orderId = (int) ($_POST['order_id'] ?? 0);
$status = trim((string) ($_POST['status'] ?? ''));

$allowed = ['completed', 'cancelled'];
if ($orderId < 1 || !in_array($status, $allowed, true)) {
    header('Location: ../admin.php?msg=order_bad');
    exit;
}

try {
    $pdo->beginTransaction();

    $sel = $pdo->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1 FOR UPDATE');
    $sel->execute(['id' => $orderId]);
    $row = $sel->fetch();
    if ($row === false) {
        $pdo->rollBack();
        header('Location: ../admin.php?msg=order_bad');
        exit;
    }

    $oldStatus = (string) $row['status'];

    $updOrder = $pdo->prepare('UPDATE orders SET status = :st WHERE id = :id LIMIT 1');
    $updOrder->execute(['st' => $status, 'id' => $orderId]);

    if ($status === 'completed' && $oldStatus === 'new') {
        $itemsStmt = $pdo->prepare(
            'SELECT product_id, quantity FROM order_items WHERE order_id = :oid'
        );
        $itemsStmt->execute(['oid' => $orderId]);
        $items = $itemsStmt->fetchAll();

        $dec = $pdo->prepare(
            'UPDATE products SET stock = IF(stock >= ?, stock - ?, 0) WHERE id = ?'
        );

        foreach ($items as $line) {
            $pid = (int) $line['product_id'];
            $qty = (int) $line['quantity'];
            if ($pid < 1 || $qty < 1) {
                continue;
            }
            $dec->execute([$qty, $qty, $pid]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../admin.php?msg=order_bad');
    exit;
}

header('Location: ../admin.php?msg=order_updated');
exit;
