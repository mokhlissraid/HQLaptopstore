<?php
/**
 * دوال مساعدة لإدارة المنتجات (لوحة الأدمن فقط — بدون مصادقة).
 */
declare(strict_types=1);

/**
 * @return list<array<string, mixed>>
 */
function hq_admin_list_products(PDO $pdo): array
{
    $sql = <<<'SQL'
        SELECT id, name, slug, description, price, image_path, warranty_months, stock, status, is_active, created_at
        FROM products
        ORDER BY id DESC
    SQL;

    return $pdo->query($sql)->fetchAll();
}

/**
 * @return array<string, mixed>|null
 */
function hq_admin_get_product(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, slug, description, price, image_path, warranty_months, stock, status, is_active FROM products WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** يولّد slug آمنًا؛ إذا كان فارغًا بعد التنظيف يستخدم بادئة عشوائية */
function hq_normalize_slug(string $raw, string $fallbackPrefix = 'p'): string
{
    $s = trim($raw);
    $s = preg_replace('/\s+/u', '-', $s);
    $s = preg_replace('/[^a-z0-9\-_]/i', '', $s);
    $s = strtolower($s);
    $s = trim($s, '-');
    if ($s === '') {
        return $fallbackPrefix . '-' . bin2hex(random_bytes(4));
    }

    return $s;
}

/** يضمن عدم تكرار slug (عند التعديل نستثني id الحالي) */
function hq_ensure_unique_slug(PDO $pdo, string $base, ?int $exceptId): string
{
    $slug = $base;
    $n = 2;
    while (true) {
        if ($exceptId === null) {
            $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :s LIMIT 1');
            $stmt->execute(['s' => $slug]);
        } else {
            $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = :s AND id != :id LIMIT 1');
            $stmt->execute(['s' => $slug, 'id' => $exceptId]);
        }
        if ($stmt->fetch() === false) {
            return $slug;
        }
        $slug = $base . '-' . $n;
        ++$n;
    }
}

/**
 * @return list<string>
 */
function hq_fetch_product_images(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $productId]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $p = trim((string) ($r['image_path'] ?? ''));
        if ($p !== '') {
            $out[] = $p;
        }
    }
    return $out;
}

/**
 * @param list<string> $paths
 */
function hq_insert_product_images(PDO $pdo, int $productId, array $paths): void
{
    if ($paths === []) {
        return;
    }
    $stmt = $pdo->prepare(
        'INSERT INTO product_images (product_id, image_path, sort_order) VALUES (:pid, :path, :ord)'
    );
    $ord = 1;
    foreach ($paths as $path) {
        $stmt->execute([
            'pid' => $productId,
            'path' => $path,
            'ord' => $ord,
        ]);
        $ord++;
    }
}

/**
 * @return list<array{id:int,image_path:string,sort_order:int}>
 */
function hq_fetch_product_images_rows(PDO $pdo, int $productId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, image_path, sort_order FROM product_images WHERE product_id = :id ORDER BY sort_order ASC, id ASC'
    );
    $stmt->execute(['id' => $productId]);
    $rows = $stmt->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) ($r['id'] ?? 0),
            'image_path' => (string) ($r['image_path'] ?? ''),
            'sort_order' => (int) ($r['sort_order'] ?? 0),
        ];
    }
    return $out;
}

function hq_set_product_cover_from_image(PDO $pdo, int $productId, int $imageId): bool
{
    $stmt = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE id = :iid AND product_id = :pid LIMIT 1'
    );
    $stmt->execute(['iid' => $imageId, 'pid' => $productId]);
    $row = $stmt->fetch();
    if ($row === false) {
        return false;
    }
    $path = (string) $row['image_path'];
    if ($path === '') {
        return false;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE products SET image_path = :p WHERE id = :id LIMIT 1')
            ->execute(['p' => $path, 'id' => $productId]);

        // نعطي الصورة المختارة ترتيبًا أعلى (0) لتظهر أولًا في المعرض.
        $pdo->prepare('UPDATE product_images SET sort_order = sort_order + 1 WHERE product_id = :pid')
            ->execute(['pid' => $productId]);
        $pdo->prepare('UPDATE product_images SET sort_order = 0 WHERE id = :iid AND product_id = :pid')
            ->execute(['iid' => $imageId, 'pid' => $productId]);

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}

function hq_delete_product_image(PDO $pdo, int $productId, int $imageId): bool
{
    $sel = $pdo->prepare(
        'SELECT image_path FROM product_images WHERE id = :iid AND product_id = :pid LIMIT 1'
    );
    $sel->execute(['iid' => $imageId, 'pid' => $productId]);
    $row = $sel->fetch();
    if ($row === false) {
        return false;
    }
    $deletedPath = (string) $row['image_path'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM product_images WHERE id = :iid AND product_id = :pid LIMIT 1')
            ->execute(['iid' => $imageId, 'pid' => $productId]);

        $coverSel = $pdo->prepare('SELECT image_path FROM products WHERE id = :id LIMIT 1');
        $coverSel->execute(['id' => $productId]);
        $cover = $coverSel->fetch();
        $currentCover = $cover === false ? '' : (string) ($cover['image_path'] ?? '');

        if ($currentCover === $deletedPath) {
            $nextSel = $pdo->prepare(
                'SELECT image_path FROM product_images WHERE product_id = :pid ORDER BY sort_order ASC, id ASC LIMIT 1'
            );
            $nextSel->execute(['pid' => $productId]);
            $next = $nextSel->fetch();
            $newCover = $next === false
                ? 'assets/images/placeholder-laptop.svg'
                : (string) ($next['image_path'] ?? 'assets/images/placeholder-laptop.svg');

            $pdo->prepare('UPDATE products SET image_path = :p WHERE id = :id LIMIT 1')
                ->execute(['p' => $newCover, 'id' => $productId]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return false;
    }
}
