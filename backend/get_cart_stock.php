<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

$rawIds = isset($_GET['ids']) ? (string) $_GET['ids'] : '';
if ($rawIds === '') {
    echo json_encode(['ok' => true, 'stocks' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$ids = [];
foreach (explode(',', $rawIds) as $part) {
    $id = (int) trim($part);
    if ($id > 0 && !in_array($id, $ids, true)) {
        $ids[] = $id;
    }
}

if ($ids === []) {
    echo json_encode(['ok' => true, 'stocks' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$stocks = [];
foreach ($ids as $id) {
    $stocks[(string) $id] = 0;
}

try {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, stock FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);
    while ($row = $stmt->fetch()) {
        $pid = (int) ($row['id'] ?? 0);
        if ($pid < 1) {
            continue;
        }
        $stock = (int) ($row['stock'] ?? 0);
        $stocks[(string) $pid] = $stock < 0 ? 0 : $stock;
    }
    echo json_encode(['ok' => true, 'stocks' => $stocks], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
