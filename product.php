<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/config.php';
$DEMO_VULNERABLE = $GLOBALS['DEMO_VULNERABLE'] ?? true;

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/get_products.php';

/** @param mixed $v */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get id from URL (string for demo; never trust raw input in production SQL).
$id_raw = isset($_GET['id']) ? (string) $_GET['id'] : '';

if ($DEMO_VULNERABLE) {
    // ================= VULNERABLE MODE =================
    // WARNING: SQL Injection possible (for classroom demo only).
    // Why dangerous: the browser URL becomes part of the SQL string → attacker can alter the query.
    // How exploited: change ?id=… to inject extra SQL (classic teaching example: OR / UNION / comments).
    // TO BE REMOVED / VULNERABLE
    $product = null;
    if ($id_raw !== '') {
        $sql = 'SELECT * FROM products WHERE id = ' . $id_raw . ' LIMIT 1'; // TO BE REMOVED / VULNERABLE
        $result = $pdo->query($sql); // TO BE REMOVED / VULNERABLE
        $row = $result ? $result->fetch(PDO::FETCH_ASSOC) : false;
        $product = $row !== false ? $row : null;
    }
} else {
    // ================= SECURE MODE =====================
    // Safe: prepared statement + integer id — user input cannot become SQL syntax.
    // Why it fixes injection: the database treats the bound value as data, not as code.
    $id = (int) $id_raw;
    $product = null;
    if ($id > 0) {
        $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $product = $row !== false ? $row : null;
    }
}

if ($product === null) {
    http_response_code(404);
    $pageTitle = 'المنتج غير موجود — HQ Laptop';
    $notFound = true;
} else {
    $notFound = false;
    $pageTitle = ($DEMO_VULNERABLE ? (string) $product['name'] : h((string) $product['name'])) . ' — HQ Laptop';
    $img = (string) ($product['image_path'] ?? '');
    $images = hq_fetch_product_images_public($pdo, (int) $product['id']);
    if ($images === []) {
        if ($img === '') {
            $img = 'assets/images/placeholder-laptop.svg';
        }
        $images = [$img];
    } else {
        $img = $images[0];
    }
    $price = number_format((float) $product['price'], 2);
    $warranty = (int) $product['warranty_months'];
    $stock = (int) $product['stock'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php
    if ($notFound) {
        echo h($pageTitle);
    } elseif ($DEMO_VULNERABLE) {
        // ================= VULNERABLE MODE =================
        // Unescaped title → XSS if product name contains markup.
        // TO BE REMOVED / VULNERABLE
        echo $pageTitle; // TO BE REMOVED / VULNERABLE
    } else {
        echo h($pageTitle);
    }
  ?></title>
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
    <div class="absolute -top-32 -right-24 h-96 w-96 rounded-full bg-purple-500/25 blur-3xl"></div>
    <div class="absolute top-1/3 -left-32 h-80 w-80 rounded-full bg-[#571399]/20 blur-3xl"></div>
  </div>

  <header class="sticky top-0 z-50 border-b border-white/40 glass-nav shadow-sm shadow-brand-purple/5">
    <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
      <a href="index.php" class="flex items-center gap-3 min-w-0">
        <img src="assets/images/logo.png" alt="" class="h-10 w-auto max-w-[140px] object-contain shrink-0" width="120" height="40" onerror="this.classList.add('hidden')">
        <span class="text-lg font-bold tracking-tight text-gradient-brand sm:text-xl">HQ Laptop</span>
      </a>
      <nav class="flex items-center gap-2 sm:gap-3">
        <a href="index.php" class="rounded-full px-3 py-1.5 text-sm font-medium text-brand-dark/80 hover:text-brand-purple transition">المتجر</a>
        <a href="cart.html" class="rounded-full px-3 py-1.5 text-sm font-medium text-brand-dark/80 hover:text-brand-purple transition">السلة</a>
      </nav>
    </div>
  </header>

  <main class="mx-auto max-w-6xl px-4 py-10 sm:px-6 sm:py-14">
    <?php if ($notFound): ?>
      <div class="glass-panel rounded-3xl border border-white/50 p-10 text-center">
        <h1 class="text-2xl font-bold text-brand-dark">المنتج غير موجود</h1>
        <p class="mt-3 text-brand-dark/65">قد يكون الرابط قديمًا أو تم إزالة العرض.</p>
        <a href="index.php" class="mt-8 inline-flex rounded-2xl bg-brand-purple px-6 py-3 text-sm font-semibold text-white hover:brightness-110 transition">العودة للمتجر</a>
      </div>
    <?php else: ?>
      <div class="grid gap-10 lg:grid-cols-2 lg:items-start">
        <div class="overflow-hidden rounded-3xl border border-white/50 bg-white/35 shadow-xl shadow-brand-purple/10 backdrop-blur-xl p-3">
          <div class="relative overflow-hidden rounded-2xl">
            <img id="product-main-image" src="<?php echo h($img); ?>" alt="" class="aspect-[16/10] w-full object-contain bg-gradient-to-b from-brand-light/90 to-white/70 p-4">
            <?php if (count($images) > 1): ?>
              <button type="button" id="gallery-prev" class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-black/40 px-3 py-2 text-white hover:bg-black/60">‹</button>
              <button type="button" id="gallery-next" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-black/40 px-3 py-2 text-white hover:bg-black/60">›</button>
            <?php endif; ?>
          </div>
          <?php if (count($images) > 1): ?>
            <div class="mt-3 grid grid-cols-5 gap-2">
              <?php foreach ($images as $i => $g): ?>
                <button type="button" class="gallery-thumb overflow-hidden rounded-lg border <?php echo $i === 0 ? 'border-brand-purple' : 'border-white/60'; ?>" data-src="<?php echo h($g); ?>">
                  <img src="<?php echo h($g); ?>" alt="" class="h-16 w-full object-cover">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        <div class="glass-panel rounded-3xl border border-white/50 p-8 shadow-lg">
          <h1 class="text-3xl font-bold text-brand-dark leading-tight"><?php
            if ($DEMO_VULNERABLE) {
                // ================= VULNERABLE MODE =================
                // TO BE REMOVED / VULNERABLE
                echo (string) $product['name']; // TO BE REMOVED / VULNERABLE
            } else {
                echo h((string) $product['name']);
            }
          ?></h1>
          <p class="mt-4 text-base text-brand-dark/70 leading-relaxed whitespace-pre-line"><?php
            if ($DEMO_VULNERABLE) {
                // TO BE REMOVED / VULNERABLE
                echo (string) $product['description']; // TO BE REMOVED / VULNERABLE
            } else {
                echo h((string) $product['description']);
            }
          ?></p>
          <div class="mt-6 flex flex-wrap gap-2 text-xs font-medium">
            <span class="rounded-full bg-white/70 px-3 py-1 border border-white/60">ضمان <?php echo h((string) $warranty); ?> أشهر</span>
            <span class="rounded-full <?php echo $stock > 0 ? 'bg-emerald-500/15 text-emerald-800' : 'bg-red-500/15 text-red-800'; ?> px-3 py-1 border border-white/40">
              <?php echo $stock > 0 ? 'متوفر' : 'نفدت الكمية'; ?>
            </span>
          </div>
          <p class="mt-8 text-3xl font-bold text-brand-purple"><?php echo h($price); ?> <span class="text-lg font-medium text-brand-dark/50">د.ج</span></p>
          <div class="mt-8 flex flex-wrap gap-3">
            <button type="button" id="add-to-cart" <?php echo $stock <= 0 ? 'disabled' : ''; ?>
              class="rounded-2xl bg-brand-dark px-6 py-3 text-sm font-semibold text-brand-light hover:bg-brand-purple transition disabled:cursor-not-allowed disabled:opacity-50">
              أضف إلى السلة
            </button>
            <a href="index.php#products" class="inline-flex items-center justify-center rounded-2xl border border-brand-dark/15 bg-white/50 px-6 py-3 text-sm font-semibold text-brand-dark hover:bg-white/80 transition">متابعة التسوق</a>
          </div>
          <p id="cart-toast" class="mt-4 hidden text-sm font-medium text-emerald-700" role="status"></p>
        </div>
      </div>
      <script src="assets/js/cart.js"></script>
      <script>
        document.addEventListener('DOMContentLoaded', function () {
          var gallery = <?php echo json_encode($images, JSON_UNESCAPED_UNICODE); ?>;
          var mainImg = document.getElementById('product-main-image');
          var thumbs = Array.from(document.querySelectorAll('.gallery-thumb'));
          var idx = 0;
          function paintGallery() {
            if (!mainImg || gallery.length === 0) return;
            mainImg.src = gallery[idx];
            thumbs.forEach(function (t, i) {
              t.classList.toggle('border-brand-purple', i === idx);
              t.classList.toggle('border-white/60', i !== idx);
            });
          }
          var prev = document.getElementById('gallery-prev');
          var next = document.getElementById('gallery-next');
          if (prev) prev.addEventListener('click', function () { idx = (idx - 1 + gallery.length) % gallery.length; paintGallery(); });
          if (next) next.addEventListener('click', function () { idx = (idx + 1) % gallery.length; paintGallery(); });
          thumbs.forEach(function (t, i) {
            t.addEventListener('click', function () { idx = i; paintGallery(); });
          });

          var product = <?php echo json_encode([
              'id' => (int) $product['id'],
              'name' => (string) $product['name'],
              'price' => (float) $product['price'],
              'image' => $img,
          ], JSON_UNESCAPED_UNICODE); ?>;
          var btn = document.getElementById('add-to-cart');
          var toast = document.getElementById('cart-toast');
          if (!btn || btn.disabled || typeof HQCart === 'undefined') return;
          btn.addEventListener('click', function () {
            HQCart.addToCart(product);
            toast.textContent = 'تمت الإضافة إلى السلة.';
            toast.classList.remove('hidden');
          });
        });
      </script>
    <?php endif; ?>
  </main>

  <footer class="border-t border-white/40 glass-nav py-8 mt-8">
    <div class="mx-auto max-w-6xl px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-brand-dark/60">
      <p>© <?php echo date('Y'); ?> HQ Laptop — مشروع تجريبي.</p>
      <a href="admin.php" class="text-brand-purple hover:underline">لوحة الطلبات</a>
    </div>
  </footer>
</body>
</html>
