<?php
/**
 * قائمة المنتجات للأدمن: عرض، روابط تعديل / حذف / تبديل الحالة.
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/products_admin.php';

/** @param mixed $v */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$flash = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$flashMap = [
    'added' => 'تمت إضافة المنتج.',
    'updated' => 'تم تحديث المنتج.',
    'deleted' => 'تم حذف المنتج.',
    'toggled' => 'تم تحديث حالة التوفر.',
    'delerr' => 'تعذر الحذف (قد يكون مرتبطًا بطلبات سابقة).',
];

$products = [];
$error = '';
try {
    $products = hq_admin_list_products($pdo);
} catch (Throwable $e) {
    $error = 'تعذر تحميل المنتجات. إن ظهر خطأ عن عمود status، نفّذ ملف database/migration_admin_management.sql في phpMyAdmin.';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إدارة المنتجات — HQ Laptop</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: { light: '#DFF6FF', purple: '#571399', dark: '#1D283B' }
          },
          fontFamily: {
            sans: ['"IBM Plex Sans Arabic"', 'system-ui', 'sans-serif']
          }
        }
      }
    };
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen bg-brand-light text-brand-dark antialiased relative overflow-x-hidden">
  <div class="pointer-events-none fixed inset-0 -z-10">
    <div class="absolute top-20 -right-20 h-80 w-80 rounded-full bg-purple-500/20 blur-3xl"></div>
  </div>

  <header class="sticky top-0 z-50 border-b border-white/40 glass-nav shadow-sm">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
      <a href="index.php" class="text-sm font-semibold text-brand-purple hover:underline">← المتجر</a>
      <nav class="flex flex-wrap items-center gap-2 text-sm">
        <a href="admin.php" class="rounded-full px-3 py-1.5 text-brand-dark/80 hover:bg-white/50 transition">الطلبات</a>
        <a href="admin_products.php" class="rounded-full px-3 py-1.5 font-medium text-brand-purple bg-white/60">المنتجات</a>
        <a href="admin_logout.php" class="rounded-full px-3 py-1.5 text-red-800/90 hover:bg-red-50 transition">خروج</a>
      </nav>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl font-bold text-brand-dark">إدارة المنتجات</h1>
        <p class="mt-2 text-sm text-brand-dark/60">إضافة، تعديل، حذف، وتبديل حالة التوفر (متاح / مباع).</p>
      </div>
      <a href="add_product.php" class="inline-flex justify-center rounded-2xl bg-brand-purple px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-brand-purple/25 hover:brightness-110 transition">
        + منتج جديد
      </a>
    </div>

    <?php if ($flash !== '' && isset($flashMap[$flash])): ?>
      <div class="mt-6 glass-panel rounded-xl border border-emerald-200/60 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900"><?php echo h($flashMap[$flash]); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <div class="mt-6 glass-panel rounded-2xl border border-red-200/60 bg-red-50/80 p-6 text-red-800 text-sm"><?php echo h($error); ?></div>
    <?php elseif (count($products) === 0): ?>
      <div class="mt-8 glass-panel rounded-2xl border border-white/50 p-12 text-center text-brand-dark/65">لا توجد منتجات بعد.</div>
    <?php else: ?>
      <div class="mt-8 overflow-x-auto glass-panel rounded-2xl border border-white/50 shadow-lg">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-white/50 bg-white/30 text-right text-xs font-semibold uppercase tracking-wide text-brand-dark/60">
              <th class="px-4 py-3">#</th>
              <th class="px-4 py-3">الاسم</th>
              <th class="px-4 py-3">السعر</th>
              <th class="px-4 py-3">المخزون</th>
              <th class="px-4 py-3">الحالة</th>
              <th class="px-4 py-3">نشط</th>
              <th class="px-4 py-3 w-48">إجراءات</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/40">
            <?php foreach ($products as $p): ?>
              <?php
                $pid = (int) $p['id'];
                $st = (string) $p['status'];
                $isAv = $st === 'available';
              ?>
              <tr class="hover:bg-white/20 transition">
                <td class="px-4 py-3 font-mono text-brand-dark/70"><?php echo h((string) $pid); ?></td>
                <td class="px-4 py-3 font-medium"><?php echo h((string) $p['name']); ?></td>
                <td class="px-4 py-3 text-brand-purple font-semibold"><?php echo h(number_format((float) $p['price'], 2)); ?> د.ج</td>
                <td class="px-4 py-3"><?php echo h((string) $p['stock']); ?></td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2.5 py-0.5 text-xs font-medium <?php echo $isAv ? 'bg-emerald-500/15 text-emerald-800' : 'bg-amber-500/15 text-amber-900'; ?>">
                    <?php echo $isAv ? 'متاح' : 'مباع'; ?>
                  </span>
                </td>
                <td class="px-4 py-3"><?php echo ((int) $p['is_active'] === 1) ? 'نعم' : 'لا'; ?></td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap gap-1.5 justify-end">
                    <a href="edit_product.php?id=<?php echo $pid; ?>" class="rounded-lg bg-brand-dark/90 px-2.5 py-1 text-xs font-medium text-white hover:bg-brand-purple transition">تعديل</a>
                    <a href="toggle_product_status.php?id=<?php echo $pid; ?>" class="rounded-lg border border-brand-dark/20 bg-white/50 px-2.5 py-1 text-xs font-medium hover:bg-white/80 transition"><?php echo $isAv ? '→ مباع' : '→ متاح'; ?></a>
                    <a href="delete_product.php?id=<?php echo $pid; ?>" onclick="return confirm('حذف هذا المنتج نهائيًا؟');" class="rounded-lg border border-red-300/60 bg-red-50/80 px-2.5 py-1 text-xs font-medium text-red-800 hover:bg-red-100 transition">حذف</a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
