<?php
/**
 * Load active products for the storefront (used by index.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';
$DEMO_VULNERABLE = $GLOBALS['DEMO_VULNERABLE'] ?? true;

/**
 * @return list<array<string, mixed>>
 */
function hq_fetch_products(PDO $pdo): array
{
    $sql = <<<'SQL'
        SELECT id, name, slug, description, price, image_path, warranty_months, stock
        FROM products
        WHERE is_active = 1 AND status = 'available'
        ORDER BY created_at DESC, id DESC
    SQL;

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * @return array<string, mixed>|null
 */
function hq_fetch_product_by_id(PDO $pdo, int|string $id): ?array
{
    $demo = (bool) ($GLOBALS['DEMO_VULNERABLE'] ?? true);

    if ($demo) {
        // ================= VULNERABLE MODE =================
        // Direct interpolation of user-controlled id → SQL injection (e.g. 1 OR 1=1 -- ).
        // TO BE REMOVED / VULNERABLE
        $raw = is_string($id) ? $id : (string) (int) $id;
        $stmt = $pdo->query(
            'SELECT id, name, slug, description, price, image_path, warranty_months, stock FROM products '
            . "WHERE is_active = 1 AND status = 'available' AND id = {$raw} LIMIT 1"
        ); // TO BE REMOVED / VULNERABLE
        $row = $stmt ? $stmt->fetch() : false;

        return $row === false ? null : $row;
    }

    // ================= SECURE MODE =====================
    $sql = <<<'SQL'
        SELECT id, name, slug, description, price, image_path, warranty_months, stock
        FROM products
        WHERE is_active = 1 AND status = 'available' AND id = :id
        LIMIT 1
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => (int) $id]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

/**
 * @return list<string>
 */
function hq_fetch_product_images_public(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $productId]);
    $rows = $stmt->fetchAll();
    $images = [];
    foreach ($rows as $r) {
        $p = trim((string) ($r['image_path'] ?? ''));
        if ($p !== '') {
            $images[] = $p;
        }
    }
    return $images;
}
