<?php
/**
 * إضافة منتج: يعرض نموذجًا (GET) ويعالج الإرسال (POST).
 *
 * تدفق POST: المتصفح يرسل حقول النموذج → PHP يقرأ $_POST → تحقق → INSERT → إعادة توجيه.
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';
hq_admin_require();

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/products_admin.php';
require_once __DIR__ . '/backend/product_image_upload.php';

/** @param mixed $v */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $slugRaw = trim((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
    $stock = (int) ($_POST['stock'] ?? 0);
    $warranty = (int) ($_POST['warranty_months'] ?? 6);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $status = $stock > 0 ? 'available' : 'sold';

    if ($name === '') {
        $error = 'اسم المنتج مطلوب.';
    } elseif ($price < 0) {
        $error = 'السعر غير صالح.';
    } elseif ($stock < 0 || $warranty < 0) {
        $error = 'المخزون أو الضمان غير صالح.';
    } else {
        $imagePath = 'assets/images/placeholder-laptop.svg';
        $upMany = hq_try_save_product_images('product_images');
        if (!$upMany['ok']) {
            $error = $upMany['error'];
        } elseif ($upMany['paths'] !== []) {
            $imagePath = $upMany['paths'][0];
        }

        if ($error === '') {
            $baseSlug = hq_normalize_slug($slugRaw);
            $slug = hq_ensure_unique_slug($pdo, $baseSlug, null);

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'INSERT INTO products (name, slug, description, price, image_path, warranty_months, stock, status, is_active)
                     VALUES (:name, :slug, :desc, :price, :img, :war, :stock, :st, :act)'
                );
                $stmt->execute([
                    'name' => $name,
                    'slug' => $slug,
                    'desc' => $description,
                    'price' => round($price, 2),
                    'img' => $imagePath,
                    'war' => $warranty,
                    'stock' => $stock,
                    'st' => $status,
                    'act' => $isActive,
                ]);
                $pid = (int) $pdo->lastInsertId();
                if ($upMany['paths'] !== []) {
                    hq_insert_product_images($pdo, $pid, $upMany['paths']);
                }
                $pdo->commit();
                header('Location: admin_products.php?msg=added');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'تعذر الحفظ. تحقق من قاعدة البيانات أو تكرار الـ slug.';
            }
        }
    }
}

$defaults = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'price' => '',
    'stock' => '0',
    'warranty_months' => '6',
    'is_active' => true,
    'status' => 'available',
];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error !== '') {
    $defaults['name'] = (string) ($_POST['name'] ?? '');
    $defaults['slug'] = (string) ($_POST['slug'] ?? '');
    $defaults['description'] = (string) ($_POST['description'] ?? '');
    $defaults['price'] = (string) ($_POST['price'] ?? '');
    $defaults['stock'] = (string) ($_POST['stock'] ?? '0');
    $defaults['warranty_months'] = (string) ($_POST['warranty_months'] ?? '6');
    $defaults['is_active'] = isset($_POST['is_active']);
    $defaults['status'] = ((int) ($_POST['stock'] ?? 0)) > 0 ? 'available' : 'sold';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إضافة منتج — HQ Laptop</title>
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
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="min-h-screen bg-brand-light text-brand-dark antialiased">
  <header class="border-b border-white/40 glass-nav">
    <div class="mx-auto flex max-w-2xl items-center justify-between px-4 py-3 sm:px-6">
      <a href="admin_products.php" class="text-sm font-semibold text-brand-purple hover:underline">← المنتجات</a>
    </div>
  </header>

  <main class="mx-auto max-w-2xl px-4 py-10 sm:px-6">
    <h1 class="text-2xl font-bold">منتج جديد</h1>
    <p class="mt-2 text-sm text-brand-dark/60">لرفع صورة أضفنا <code class="rounded bg-white/50 px-1">enctype=&quot;multipart/form-data&quot;</code> حتى يُرسل الملف مع الحقول النصية في نفس الطلب.</p>

    <?php if ($error !== ''): ?>
      <div class="mt-6 rounded-xl border border-red-200/60 bg-red-50/90 px-4 py-3 text-sm text-red-800"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-8 glass-panel space-y-5 rounded-3xl border border-white/50 p-6 sm:p-8 shadow-xl">
      <div>
        <label class="block text-sm font-medium mb-1.5" for="name">اسم المنتج</label>
        <input id="name" name="name" required maxlength="180" value="<?php echo h($defaults['name']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="slug">Slug (إنجليزي، فريد)</label>
        <input id="slug" name="slug" maxlength="200" placeholder="اتركه فارغًا ليُولَّد تلقائيًا"
          value="<?php echo h($defaults['slug']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="description">الوصف</label>
        <textarea id="description" name="description" rows="4" maxlength="65535"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25"><?php echo h($defaults['description']); ?></textarea>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1.5" for="price">السعر (د.ج)</label>
          <input id="price" name="price" type="number" step="0.01" min="0" required value="<?php echo h($defaults['price']); ?>"
            class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1.5" for="stock">المخزون</label>
          <input id="stock" name="stock" type="number" min="0" required value="<?php echo h($defaults['stock']); ?>"
            class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
          <p class="mt-1.5 text-xs text-brand-dark/55">الحالة: <strong><?php echo ((int) $defaults['stock'] > 0) ? 'متاح' : 'مباع'; ?></strong> (تلقائياً من المخزون).</p>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="warranty_months">ضمان (أشهر)</label>
        <input id="warranty_months" name="warranty_months" type="number" min="0" max="255" required value="<?php echo h($defaults['warranty_months']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="product_images">صور المنتج</label>
        <input id="product_images" name="product_images[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
          class="block w-full text-sm text-brand-dark/80 file:ml-4 file:rounded-xl file:border-0 file:bg-brand-purple file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:brightness-110">
        <p class="mt-1.5 text-xs text-brand-dark/50">يمكنك اختيار عدة صور (حتى 8). أول صورة تصبح صورة الغلاف. إن لم تختر صورًا نستخدم الصورة الافتراضية.</p>
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="rounded border-brand-dark/30" <?php echo $defaults['is_active'] ? 'checked' : ''; ?>>
        منتج نشط (يظهر في لوحة الأدمن؛ غير النشط لا يظهر في المتجر حتى لو متاح)
      </label>
      <button type="submit" class="w-full rounded-2xl bg-brand-purple py-3 text-sm font-semibold text-white shadow-lg hover:brightness-110 transition">حفظ المنتج</button>
    </form>
  </main>
</body>
</html>
