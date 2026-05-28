<?php
/**
 * حماية لوحة التحكم: جلسة PHP بعد تسجيل الدخول في admin_login.php
 * استدعِ hq_admin_require() في أول سطر تنفيذي لكل صفحة أدمن (وملفات backend الحساسة).
 */
declare(strict_types=1);

function hq_admin_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function hq_admin_is_logged_in(): bool
{
    hq_admin_session_start();

    return !empty($_SESSION['hq_admin_ok']);
}

/** يتحقق من صفحة آمنة بعد تسجيل الدخول (منع فتح إعادة توجيه خارجية) */
function hq_admin_sanitize_next(string $raw): string
{
    $raw = trim($raw);
    if ($raw === '') {
        return 'admin.php';
    }
    $path = parse_url($raw, PHP_URL_PATH);
    if ($path === null || $path === '') {
        $path = $raw;
    }
    $base = strtolower(basename($path));
    $allowed = [
        'admin.php',
        'admin_products.php',
        'add_product.php',
        'edit_product.php',
        'delete_product.php',
        'toggle_product_status.php',
    ];
    if (!in_array($base, $allowed, true)) {
        return 'admin.php';
    }
    $query = parse_url($raw, PHP_URL_QUERY);

    return $base . ($query !== null && $query !== '' ? '?' . $query : '');
}

function hq_admin_redirect_to_login(): void
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $inBackend = strpos($script, '/backend/') !== false;
    $prefix = $inBackend ? '../' : '';

    $q = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
        ? '?' . $_SERVER['QUERY_STRING']
        : '';
    $next = basename($script) . $q;
    $next = hq_admin_sanitize_next($next);

    header('Location: ' . $prefix . 'admin_login.php?next=' . rawurlencode($next));
    exit;
}

function hq_admin_require(): void
{
    if (hq_admin_is_logged_in()) {
        return;
    }
    hq_admin_redirect_to_login();
}
