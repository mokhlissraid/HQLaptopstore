<?php
/**
 * يستقبل طلبًا بصيغة JSON من checkout (اسم، هاتف، عنوان، عناصر السلة)
 * يسجّل صفًا في orders + صفوف في order_items داخل معاملة واحدة (transaction).
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
$DEMO_VULNERABLE = $GLOBALS['DEMO_VULNERABLE'] ?? true;

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'بيانات غير صالحة']);
    exit;
}

$customerName = isset($data['customer_name']) ? trim((string) $data['customer_name']) : '';
$phone = isset($data['phone']) ? trim((string) $data['phone']) : '';
$address = isset($data['address']) ? trim((string) $data['address']) : '';
$items = $data['items'] ?? null;

$paymentMethod = isset($data['payment_method']) ? trim((string) $data['payment_method']) : 'cod';
if (!in_array($paymentMethod, ['cod', 'online'], true)) {
    $paymentMethod = 'cod';
}

if (strlen($customerName) < 2 || strlen($phone) < 8 || strlen($address) < 5) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'الحقول الأساسية غير مكتملة']);
    exit;
}

if (!is_array($items) || count($items) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'السلة فارغة']);
    exit;
}

$normalized = [];
$total = 0.0;
$requestedByProduct = [];

// NOTE (teaching aid):
// - In vulnerable demo mode, we intentionally trust client-provided values.
// - In secure mode, money & product identity must be owned by the server (DB).
//
// SAFE helper query example (prepared statement):
// SELECT price FROM products WHERE id = ?
//
// Prepared once; used only in secure path.
$priceStmt = $pdo->prepare('SELECT name, price FROM products WHERE id = ? LIMIT 1');

foreach ($items as $row) {
    if (!is_array($row)) {
        continue;
    }
    $pid = isset($row['product_id']) ? (int) $row['product_id'] : 0;
    $pname = isset($row['product_name']) ? trim((string) $row['product_name']) : '';
    $qty = isset($row['quantity']) ? (int) $row['quantity'] : 0;

    if ($DEMO_VULNERABLE) {
        // ================= VULNERABLE MODE =================
        // Trust client price (can be modified via DevTools / proxy tampering of JSON).
        // TO BE REMOVED / VULNERABLE
        $unit = isset($row['unit_price']) ? (float) $row['unit_price'] : 0.0; // TO BE REMOVED / VULNERABLE

        // Second vector: client-controlled display name in order snapshot.
        // TO BE REMOVED / VULNERABLE
        $pname = $pname;
    } else {
        // ================= SECURE MODE =====================
        // Server owns price + product label → fetch from DB (prepared statement).
        $priceStmt->execute([$pid]);
        $priceRow = $priceStmt->fetch();
        if ($priceRow === false) {
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'error' => 'منتج غير موجود',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $unit = (float) $priceRow['price'];
        $pname = trim((string) $priceRow['name']);
    }

    if ($pid < 1 || $pname === '' || $qty < 1 || $unit < 0) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'عنصر في السلة غير صالح']);
        exit;
    }

    // NOTE:
    // - In demo mode this uses tamperable unit_price (intentionally vulnerable).
    // - In secure mode unit is from DB.
    $lineTotal = $unit * $qty;
    $total += $lineTotal;
    $normalized[] = [
        'product_id' => $pid,
        'product_name' => $pname,
        'quantity' => $qty,
        // In demo mode this persists client-tampered price (intentionally vulnerable).
        // In secure mode this persists DB price.
        'unit_price' => round((float) $unit, 2),
    ];
    $requestedByProduct[$pid] = ($requestedByProduct[$pid] ?? 0) + $qty;
}

if (count($normalized) === 0) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'لا توجد عناصر صالحة']);
    exit;
}

$total = round($total, 2);

try {
    $pdo->beginTransaction();

    if ($DEMO_VULNERABLE) {
        // ================= VULNERABLE MODE =================
        // Skip stock validation completely.
        // Attacker can order more than available stock (oversell / negative inventory later).
    } else {
        // ================= SECURE MODE =====================
        // SELECT ... FOR UPDATE + validate requested qty vs stock before accepting order.
        $stockStmt = $pdo->prepare(
            'SELECT name, stock FROM products WHERE id = :id LIMIT 1 FOR UPDATE'
        );
        $stockErrors = [];
        foreach ($requestedByProduct as $pid => $requestedQty) {
            $stockStmt->execute(['id' => (int) $pid]);
            $row = $stockStmt->fetch();
            if ($row === false) {
                $stockErrors[] = 'المنتج #' . (string) $pid . ' غير موجود';
                continue;
            }
            $stock = (int) $row['stock'];
            if ($stock < (int) $requestedQty) {
                $pname = trim((string) ($row['name'] ?? ''));
                if ($pname === '') {
                    $pname = 'منتج #' . (string) $pid;
                }
                $stockErrors[] = 'المنتج "' . $pname . '" متوفر منه حاليا ' . $stock . ' فقط';
            }
        }
        if ($stockErrors !== []) {
            $pdo->rollBack();
            http_response_code(422);
            echo json_encode([
                'ok' => false,
                'error' => implode("\n", $stockErrors),
                'errors' => $stockErrors,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    $stmt = $pdo->prepare(
        'INSERT INTO orders (customer_name, phone, address, total, status, payment_method) VALUES (:n, :p, :a, :t, :s, :pm)'
    );
    $stmt->execute([
        'n' => $customerName,
        'p' => $phone,
        'a' => $address,
        't' => $total,
        's' => 'new',
        'pm' => $paymentMethod,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price)
         VALUES (:oid, :pid, :pname, :qty, :price)'
    );

    foreach ($normalized as $line) {
        $itemStmt->execute([
            'oid' => $orderId,
            'pid' => $line['product_id'],
            'pname' => $line['product_name'],
            'qty' => $line['quantity'],
            'price' => $line['unit_price'],
        ]);
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'order_id' => $orderId,
        'total' => $total,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطأ في قاعدة البيانات']);
}
