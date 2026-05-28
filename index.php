<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/config.php';
$DEMO_VULNERABLE = $GLOBALS['DEMO_VULNERABLE'] ?? true;

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/get_products.php';

$productsError = '';
try {
    $products = hq_fetch_products($pdo);
} catch (Throwable $e) {
    $products = [];
    $productsError = 'تعذر تحميل المنتجات. تأكد من استيراد قاعدة البيانات وتشغيل MySQL.';
}

/** @param mixed $v */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HQ Laptop — لابتوب مستعمل بضمان</title>
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
  <!-- soft purple gradient orbs -->
  <div class="pointer-events-none fixed inset-0 -z-10">
    <div class="absolute -top-32 -right-24 h-96 w-96 rounded-full bg-purple-500/25 blur-3xl"></div>
    <div class="absolute top-1/3 -left-32 h-80 w-80 rounded-full bg-[#571399]/20 blur-3xl"></div>
    <div class="absolute bottom-0 right-1/4 h-72 w-72 rounded-full bg-sky-300/30 blur-3xl"></div>
  </div>

  <header class="sticky top-0 z-50 border-b border-white/40 glass-nav shadow-sm shadow-brand-purple/5">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
      <a href="index.php" class="flex items-center gap-3 min-w-0">
        <img src="assets/images/logo.png" alt="" class="h-10 w-auto max-w-[140px] object-contain shrink-0" width="120" height="40" onerror="this.classList.add('hidden')">
        <span class="text-lg font-bold tracking-tight text-gradient-brand sm:text-xl">HQ Laptop</span>
      </a>
      <nav class="flex items-center gap-2 sm:gap-3">
        <a href="index.php" class="rounded-full px-3 py-1.5 text-sm font-medium text-brand-purple bg-white/60 hover:bg-white/90 transition">المتجر</a>
        <a href="cart.html" class="rounded-full px-3 py-1.5 text-sm font-medium text-brand-dark/80 hover:text-brand-purple transition">السلة</a>
      </nav>
    </div>
  </header>

  <main>
    <section class="mx-auto max-w-6xl px-4 pt-12 pb-10 sm:px-6 sm:pt-16 sm:pb-14">
      <div class="glass-panel rounded-3xl border border-white/50 p-8 sm:p-12 shadow-xl shadow-brand-purple/10">
        <p class="text-sm font-medium uppercase tracking-widest text-brand-purple/80">ضمان موثوق · فحص كامل</p>
        <h1 class="mt-3 text-3xl font-bold leading-tight text-brand-dark sm:text-4xl md:text-5xl">
          لابتوب مستعمل <span class="text-gradient-brand">بجودة قريبة من الجديد</span>
        </h1>
        <p class="mt-4 max-w-2xl text-base text-brand-dark/70 leading-relaxed">
          متجر HQ Laptop يختار الأجهزة بعناية، يختبرها، ويقدّم ضمانًا واضحًا. تصفّح العروض أدناه واختر ما يناسب دراستك أو عملك.
        </p>
        <div class="mt-8 flex flex-wrap gap-3">
          <a href="#products" class="inline-flex items-center justify-center rounded-2xl bg-brand-purple px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-brand-purple/30 hover:brightness-110 transition">
            تصفح المنتجات
          </a>
          <a href="cart.html" class="inline-flex items-center justify-center rounded-2xl border border-brand-dark/15 bg-white/50 px-6 py-3 text-sm font-semibold text-brand-dark hover:bg-white/80 transition">
            سلة التسوق
          </a>
        </div>
      </div>
    </section>

    <section id="products" class="mx-auto max-w-6xl px-4 pb-20 sm:px-6">
      <div class="mb-8 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 class="text-2xl font-bold text-brand-dark">أجهزة متوفرة الآن</h2>
          <p class="text-sm text-brand-dark/60 mt-1">الأسعار تقريبية للعرض — التحديث من لوحة التحكم لاحقًا.</p>
        </div>
      </div>

      <?php if (!empty($productsError)): ?>
        <div class="glass-panel rounded-2xl border border-red-200/60 bg-red-50/80 p-6 text-red-800 text-sm">
          <?php echo h($productsError); ?>
        </div>
      <?php elseif (count($products) === 0): ?>
        <div class="glass-panel rounded-2xl border border-white/50 p-10 text-center text-brand-dark/70">
          لا توجد منتجات بعد. أضف صفوفًا في جدول <code class="rounded bg-white/60 px-1">products</code> أو استورد <code class="rounded bg-white/60 px-1">database/schema.sql</code>.
        </div>
      <?php else: ?>
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <?php foreach ($products as $p): ?>
            <?php
              $id = (int) $p['id'];
              $slug = (string) $p['slug'];
              $href = 'product.php?id=' . $id;
              $img = (string) ($p['image_path'] ?? '');
              if ($img === '') {
                  $img = 'assets/images/placeholder-laptop.svg';
              }
              $price = number_format((float) $p['price'], 2);
              $warranty = (int) $p['warranty_months'];
              $stock = (int) $p['stock'];
            ?>
            <article class="group flex flex-col overflow-hidden rounded-2xl border border-white/50 bg-white/35 shadow-lg shadow-brand-purple/5 backdrop-blur-xl transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-brand-purple/10">
              <a href="<?php echo h($href); ?>" class="relative block aspect-[16/10] overflow-hidden bg-gradient-to-b from-brand-light/90 to-white/70 p-3">
                <img src="<?php echo h($img); ?>" alt="" class="h-full w-full object-contain transition duration-500 group-hover:scale-[1.02]">
                <div class="absolute inset-0 bg-gradient-to-t from-brand-dark/10 to-transparent opacity-0 transition group-hover:opacity-100"></div>
              </a>
              <div class="flex flex-1 flex-col p-5">
                <h3 class="text-lg font-semibold text-brand-dark leading-snug">
                  <a href="<?php echo h($href); ?>" class="hover:text-brand-purple transition"><?php
                    if ($DEMO_VULNERABLE) {
                        // ================= VULNERABLE MODE =================
                        // Direct DB field output → stored XSS if name contains HTML/script (e.g. <script>…</script> or <img onerror=…>).
                        // TO BE REMOVED / VULNERABLE
                        echo (string) $p['name']; // TO BE REMOVED / VULNERABLE
                    } else {
                        // ================= SECURE MODE =====================
                        echo h($p['name']);
                    }
                  ?></a>
                </h3>
                <p class="mt-2 line-clamp-2 text-sm text-brand-dark/65 leading-relaxed"><?php
                  if ($DEMO_VULNERABLE) {
                      // TO BE REMOVED / VULNERABLE
                      echo (string) $p['description']; // TO BE REMOVED / VULNERABLE
                  } else {
                      echo h((string) $p['description']);
                  }
                ?></p>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-medium text-brand-dark/70">
                  <span class="rounded-full bg-white/70 px-3 py-1 border border-white/60">ضمان <?php echo h((string) $warranty); ?> أشهر</span>
                  <span class="rounded-full <?php echo $stock > 0 ? 'bg-emerald-500/15 text-emerald-800' : 'bg-red-500/15 text-red-800'; ?> px-3 py-1 border border-white/40">
                    <?php echo $stock > 0 ? 'متوفر' : 'نفدت الكمية'; ?>
                  </span>
                </div>
                <div class="mt-auto pt-5 flex items-center justify-between gap-3 border-t border-white/40">
                  <p class="text-xl font-bold text-brand-purple"><?php echo h($price); ?> <span class="text-sm font-medium text-brand-dark/50">د.ج</span></p>
                  <a href="<?php echo h($href); ?>" class="rounded-xl bg-brand-dark px-4 py-2 text-xs font-semibold text-brand-light hover:bg-brand-purple transition">التفاصيل</a>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <footer class="border-t border-white/40 glass-nav py-8">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-brand-dark/60">
      <p>© <?php echo date('Y'); ?> HQ Laptop — مشروع تجريبي.</p>
      <span class="flex flex-wrap items-center justify-center gap-x-4 gap-y-1">
        <a href="admin.php" class="text-brand-purple hover:underline">الطلبات</a>
        <a href="admin_products.php" class="text-brand-purple hover:underline">المنتجات</a>
      </span>
    </div>
  </footer>
</body>
</html>
