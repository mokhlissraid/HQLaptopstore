<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';

/** @param mixed $v */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$flash = isset($_GET['msg']) ? (string) $_GET['msg'] : '';
$flashMap = [
    'order_updated' => 'تم تحديث حالة الطلب.',
    'order_bad' => 'طلب غير صالح.',
    'order_deleted' => 'تم حذف الطلب.',
    'order_delerr' => 'تعذر حذف الطلب.',
];

$loadError = '';
$orders = [];

try {
    $stmt = $pdo->query(
        'SELECT id, customer_name, phone, address, total, status, payment_method, created_at FROM orders ORDER BY created_at DESC, id DESC'
    );
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $loadError = 'تعذر قراءة الطلبات. تأكد من استيراد قاعدة البيانات.';
}

$itemsByOrder = [];
if ($orders !== [] && $loadError === '') {
    try {
        $ids = array_map(static fn ($o) => (int) $o['id'], $orders);
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $q = $pdo->prepare(
                "SELECT order_id, product_name, quantity, unit_price FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC"
            );
            $q->execute($ids);
            while ($row = $q->fetch()) {
                $oid = (int) $row['order_id'];
                $itemsByOrder[$oid][] = $row;
            }
        }
    } catch (Throwable $e) {
        $loadError = 'تعذر قراءة تفاصيل الطلبات.';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>لوحة الطلبات — HQ Laptop</title>
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
    <div class="absolute -top-32 -left-24 h-96 w-96 rounded-full bg-purple-500/20 blur-3xl"></div>
  </div>

  <header class="sticky top-0 z-50 border-b border-white/40 glass-nav shadow-sm">
    <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6">
      <a href="index.php" class="text-sm font-semibold text-brand-purple hover:underline">← المتجر</a>
      <nav class="flex flex-wrap items-center gap-2 text-sm">
        <a href="admin.php" class="rounded-full px-3 py-1.5 font-medium text-brand-purple bg-white/60">الطلبات</a>
        <a href="admin_products.php" class="rounded-full px-3 py-1.5 text-brand-dark/80 hover:bg-white/50 transition">المنتجات</a>
        <a href="admin_logout.php" class="rounded-full px-3 py-1.5 text-red-800/90 hover:bg-red-50 transition">خروج</a>
      </nav>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
    <h1 class="text-2xl font-bold text-brand-dark">الطلبات الواردة</h1>
    <p class="mt-2 text-sm text-brand-dark/60">
      جدول <code class="rounded bg-white/50 px-1">orders</code> يخزّن الطلب ككل (العميل، المجموع، <strong class="text-brand-dark">الحالة</strong>)،
      وجدول <code class="rounded bg-white/50 px-1">order_items</code> يخزّن كل سطر منتج داخل الطلب — لأن طلبًا واحدًا قد يحتوي عدة أجهزة.
    </p>

    <?php if ($flash !== '' && isset($flashMap[$flash])): ?>
      <div class="mt-6 glass-panel rounded-xl border border-emerald-200/60 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900"><?php echo h($flashMap[$flash]); ?></div>
    <?php endif; ?>

    <?php if ($loadError !== ''): ?>
      <div class="mt-8 glass-panel rounded-2xl border border-red-200/60 bg-red-50/80 p-6 text-red-800 text-sm"><?php echo h($loadError); ?></div>
    <?php elseif (count($orders) === 0): ?>
      <div class="mt-8 glass-panel rounded-2xl border border-white/50 p-12 text-center text-brand-dark/65">
        لا توجد طلبات بعد. أنشئ طلبًا من صفحة <a href="checkout.html" class="text-brand-purple font-semibold hover:underline">الدفع</a>.
      </div>
    <?php else: ?>
      <div class="mt-8 space-y-6">
        <?php foreach ($orders as $o): ?>
          <?php
            $oid = (int) $o['id'];
            $items = $itemsByOrder[$oid] ?? [];
            $total = number_format((float) $o['total'], 2);
            $when = (string) $o['created_at'];
            $st = (string) $o['status'];
            $canAct = ($st === 'new');
            $pm = (string) ($o['payment_method'] ?? 'cod');
            $pmLabel = $pm === 'online' ? 'دفع إلكتروني (تجريبي)' : 'الدفع عند التسليم';
          ?>
          <article class="glass-panel rounded-2xl border border-white/50 overflow-hidden shadow-lg shadow-brand-purple/5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-5 py-4 border-b border-white/40 bg-white/20">
              <div>
                <p class="text-xs font-medium text-brand-purple">طلب #<?php echo h((string) $oid); ?></p>
                <p class="font-semibold text-brand-dark mt-0.5"><?php echo h((string) $o['customer_name']); ?></p>
                <p class="text-sm text-brand-dark/65 mt-1"><?php echo h((string) $o['phone']); ?></p>
              </div>
              <div class="text-left sm:text-right">
                <p class="text-lg font-bold text-brand-purple"><?php echo h($total); ?> <span class="text-sm font-medium text-brand-dark/50">د.ج</span></p>
                <p class="text-xs text-brand-dark/50 mt-1"><?php echo h($when); ?></p>
                <span class="inline-block mt-2 rounded-full bg-white/60 px-2.5 py-0.5 text-xs font-medium text-brand-dark/80"><?php echo h((string) $o['status']); ?></span>
              </div>
            </div>
            <div class="px-5 py-4">
              <p class="text-xs text-brand-dark/70">
                <span class="text-brand-dark/50">طريقة الدفع:</span>
                <span class="font-semibold text-brand-purple"><?php echo h($pmLabel); ?></span>
              </p>
              <p class="text-xs font-medium text-brand-dark/50 mb-2 mt-3">العنوان</p>
              <p class="text-sm text-brand-dark/80 leading-relaxed"><?php echo nl2br(h((string) $o['address'])); ?></p>
              <?php if (count($items) > 0): ?>
                <ul class="mt-4 space-y-2 text-sm border-t border-white/40 pt-4">
                  <?php foreach ($items as $it): ?>
                    <li class="flex justify-between gap-4">
                      <span><?php echo h((string) $it['product_name']); ?> × <?php echo h((string) $it['quantity']); ?></span>
                      <span class="text-brand-purple font-medium shrink-0"><?php echo h(number_format((float) $it['unit_price'], 2)); ?> د.ج</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <div class="mt-5 flex flex-wrap gap-2 border-t border-white/40 pt-4">
                <?php if ($canAct): ?>
                  <form method="post" action="backend/update_order_status.php" class="inline">
                    <input type="hidden" name="order_id" value="<?php echo h((string) $oid); ?>">
                    <input type="hidden" name="status" value="completed">
                    <button type="submit" class="rounded-xl bg-emerald-600/90 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-600 transition">تم التسليم</button>
                  </form>
                  <form method="post" action="backend/update_order_status.php" class="inline">
                    <input type="hidden" name="order_id" value="<?php echo h((string) $oid); ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <button type="submit" class="rounded-xl border border-red-300/70 bg-red-50/90 px-4 py-2 text-xs font-semibold text-red-900 hover:bg-red-100 transition">إلغاء الطلب</button>
                  </form>
                <?php else: ?>
                  <p class="text-xs text-brand-dark/50 py-2">لا يمكن تغيير الحالة (الحالة الحالية: <?php echo h($st); ?>).</p>
                <?php endif; ?>
                <a href="delete_order.php?id=<?php echo $oid; ?>" onclick="return confirm('حذف الطلب وجميع بنوده نهائيًا؟');"
                  class="rounded-xl border border-brand-dark/15 bg-white/40 px-4 py-2 text-xs font-semibold text-brand-dark hover:bg-white/70 transition ms-auto">حذف الطلب</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>
</body>
</html>
