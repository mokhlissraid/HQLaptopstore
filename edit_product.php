<?php
/**
 * تعديل منتج: نفس فكرة الإضافة لكن مع UPDATE WHERE id = ...
 * يجب إرسال id المنتج في الرابط (?id=) وفي النموذج كحقل مخفي عند الحفظ.
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
} else {
    $id = (int) ($_GET['id'] ?? 0);
}

if ($id < 1) {
    header('Location: admin_products.php');
    exit;
}

$error = '';
$notice = '';
$product = null;

if ($id > 0) {
    try {
        $product = hq_admin_get_product($pdo, $id);
    } catch (Throwable $e) {
        $error = 'خطأ في قاعدة البيانات.';
    }
}

if ($product === null) {
    header('Location: admin_products.php');
    exit;
}
$galleryRows = hq_fetch_product_images_rows($pdo, $id);
$gallery = array_map(static fn ($r) => (string) $r['image_path'], $galleryRows);

$flash = (string) ($_GET['msg'] ?? '');
if ($flash === 'cover_ok') {
    $notice = 'تم تعيين الصورة الرئيسية.';
} elseif ($flash === 'img_del_ok') {
    $notice = 'تم حذف الصورة.';
} elseif ($flash === 'img_action_err') {
    $error = 'تعذر تنفيذ العملية على الصورة.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $product !== null) {
    $imageAction = (string) ($_POST['image_action'] ?? '');
    if ($imageAction !== '') {
        $imageId = (int) ($_POST['image_id'] ?? 0);
        if ($imageId < 1) {
            header('Location: edit_product.php?id=' . $id . '&msg=img_action_err');
            exit;
        }
        $ok = false;
        if ($imageAction === 'set_cover') {
            $ok = hq_set_product_cover_from_image($pdo, $id, $imageId);
            header('Location: edit_product.php?id=' . $id . '&msg=' . ($ok ? 'cover_ok' : 'img_action_err'));
            exit;
        }
        if ($imageAction === 'delete') {
            $ok = hq_delete_product_image($pdo, $id, $imageId);
            header('Location: edit_product.php?id=' . $id . '&msg=' . ($ok ? 'img_del_ok' : 'img_action_err'));
            exit;
        }
        header('Location: edit_product.php?id=' . $id . '&msg=img_action_err');
        exit;
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $slugRaw = trim((string) ($_POST['slug'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = (float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'));
    $stock = (int) ($_POST['stock'] ?? 0);
    $warranty = (int) ($_POST['warranty_months'] ?? 6);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    // الحالة تُشتق من المخزون فقط: مخزون > 0 → متاح، وإلا → مباع (لا إدخال يدوي).
    $status = $stock > 0 ? 'available' : 'sold';

    if ($name === '') {
        $error = 'اسم المنتج مطلوب.';
    } elseif ($price < 0 || $stock < 0 || $warranty < 0) {
        $error = 'قيم غير صالحة.';
    } else {
        $imagePath = trim((string) ($product['image_path'] ?? ''));
        if ($imagePath === '') {
            $imagePath = 'assets/images/placeholder-laptop.svg';
        }

        $upMany = hq_try_save_product_images('product_images');
        if (!$upMany['ok']) {
            $error = $upMany['error'];
        } elseif ($upMany['paths'] !== []) {
            $imagePath = $upMany['paths'][0];
        }

        if ($error === '') {
            $baseSlug = hq_normalize_slug($slugRaw);
            $slug = hq_ensure_unique_slug($pdo, $baseSlug, $id);

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare(
                    'UPDATE products SET name = :name, slug = :slug, description = :desc, price = :price,
                     image_path = :img, warranty_months = :war, stock = :stock, status = :st, is_active = :act
                     WHERE id = :id'
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
                    'id' => $id,
                ]);
                if ($upMany['paths'] !== []) {
                    hq_insert_product_images($pdo, $id, $upMany['paths']);
                }
                $pdo->commit();
                header('Location: admin_products.php?msg=updated');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'تعذر التحديث.';
            }
        }
    }
}

$d = $product;
if ($error !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $d['name'] = trim((string) ($_POST['name'] ?? ''));
    $d['slug'] = trim((string) ($_POST['slug'] ?? ''));
    $d['description'] = trim((string) ($_POST['description'] ?? ''));
    $d['price'] = (string) ($_POST['price'] ?? '0');
    $d['stock'] = (string) ($_POST['stock'] ?? '0');
    $d['warranty_months'] = (string) ($_POST['warranty_months'] ?? '6');
    $d['status'] = ((int) ($_POST['stock'] ?? 0)) > 0 ? 'available' : 'sold';
    $d['is_active'] = isset($_POST['is_active']) ? 1 : 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>تعديل منتج — HQ Laptop</title>
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
    <h1 class="text-2xl font-bold">تعديل المنتج #<?php echo h((string) $d['id']); ?></h1>
    <p class="mt-2 text-sm text-brand-dark/60">UPDATE يغيّر الصف المطابق لـ id فقط؛ بقية الصفوف تبقى كما هي.</p>

    <?php if ($notice !== ''): ?>
      <div class="mt-6 rounded-xl border border-emerald-200/60 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-800"><?php echo h($notice); ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
      <div class="mt-6 rounded-xl border border-red-200/60 bg-red-50/90 px-4 py-3 text-sm text-red-800"><?php echo h($error); ?></div>
    <?php endif; ?>

    <?php if ($galleryRows !== []): ?>
      <div class="mt-8 glass-panel rounded-3xl border border-white/50 p-6 sm:p-8 shadow-xl">
        <label class="block text-sm font-medium mb-1.5">معرض الصور الحالي</label>
        <p class="mb-3 text-xs text-brand-dark/55">كل صورة لها زر منفصل (نموذج مستقل) — لا يُدمج داخل نموذج التعديل الرئيسي حتى يعمل زر الحفظ.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-xl border border-white/60 bg-white/20 p-2">
          <?php foreach ($galleryRows as $g): ?>
            <?php
              $gid = (int) $g['id'];
              $gpath = (string) $g['image_path'];
              $isCover = $gpath === (string) ($d['image_path'] ?? '');
            ?>
            <div class="rounded-xl border border-white/60 bg-white/40 p-2">
              <img src="<?php echo h($gpath); ?>" alt="" class="h-28 w-full rounded-lg object-cover border border-white/50">
              <p class="mt-1 text-[11px] text-brand-dark/55 break-all"><?php echo h($gpath); ?></p>
              <div class="mt-2 flex flex-wrap gap-2">
                <?php if ($isCover): ?>
                  <span class="rounded-lg bg-brand-purple/15 px-2.5 py-1 text-xs font-semibold text-brand-purple">الصورة الرئيسية الحالية</span>
                <?php else: ?>
                  <form method="post" class="inline">
                    <input type="hidden" name="id" value="<?php echo h((string) $d['id']); ?>">
                    <input type="hidden" name="image_action" value="set_cover">
                    <input type="hidden" name="image_id" value="<?php echo h((string) $gid); ?>">
                    <button type="submit" class="rounded-lg bg-brand-dark px-2.5 py-1 text-xs font-semibold text-white hover:bg-brand-purple transition">تعيين كرئيسية</button>
                  </form>
                <?php endif; ?>
                <form method="post" class="inline" onsubmit="return confirm('حذف هذه الصورة فقط؟');">
                  <input type="hidden" name="id" value="<?php echo h((string) $d['id']); ?>">
                  <input type="hidden" name="image_action" value="delete">
                  <input type="hidden" name="image_id" value="<?php echo h((string) $gid); ?>">
                  <button type="submit" class="rounded-lg border border-red-300/70 bg-red-50/90 px-2.5 py-1 text-xs font-semibold text-red-800 hover:bg-red-100 transition">حذف هذه الصورة</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="mt-8 glass-panel space-y-5 rounded-3xl border border-white/50 p-6 sm:p-8 shadow-xl">
      <input type="hidden" name="id" value="<?php echo h((string) $d['id']); ?>">

      <div>
        <label class="block text-sm font-medium mb-1.5" for="name">اسم المنتج</label>
        <input id="name" name="name" required maxlength="180" value="<?php echo h((string) $d['name']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="slug">Slug</label>
        <input id="slug" name="slug" required maxlength="200" value="<?php echo h((string) $d['slug']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="description">الوصف</label>
        <textarea id="description" name="description" rows="4" maxlength="65535"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25"><?php echo h((string) $d['description']); ?></textarea>
      </div>
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="block text-sm font-medium mb-1.5" for="price">السعر (د.ج)</label>
          <input id="price" name="price" type="number" step="0.01" min="0" required value="<?php echo h((string) $d['price']); ?>"
            class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1.5" for="stock">المخزون</label>
          <input id="stock" name="stock" type="number" min="0" required value="<?php echo h((string) $d['stock']); ?>"
            class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
          <p class="mt-1.5 text-xs text-brand-dark/55">الحالة في المتجر: <strong class="text-brand-dark"><?php echo ((int) $d['stock'] > 0) ? 'متاح' : 'مباع'; ?></strong> (تُحدَّث تلقائياً من المخزون عند الحفظ).</p>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="warranty_months">ضمان (أشهر)</label>
        <input id="warranty_months" name="warranty_months" type="number" min="0" max="255" required value="<?php echo h((string) $d['warranty_months']); ?>"
          class="w-full rounded-xl border border-white/60 bg-white/40 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5">الصورة الحالية</label>
        <div class="flex items-center gap-4 rounded-xl border border-white/60 bg-white/30 p-3">
          <img src="<?php echo h((string) ($d['image_path'] ?? 'assets/images/placeholder-laptop.svg')); ?>" alt="" class="h-20 w-28 rounded-lg object-cover border border-white/50">
          <p class="text-xs text-brand-dark/60 break-all"><?php echo h((string) ($d['image_path'] ?? '')); ?></p>
        </div>
      </div>
      <div>
        <label class="block text-sm font-medium mb-1.5" for="product_images">إضافة صور جديدة (اختياري)</label>
        <input id="product_images" name="product_images[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,.jpg,.jpeg,.png,.webp,.gif"
          class="block w-full text-sm text-brand-dark/80 file:ml-4 file:rounded-xl file:border-0 file:bg-brand-purple file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:brightness-110">
        <p class="mt-1.5 text-xs text-brand-dark/50">يمكنك اختيار عدة صور لإضافتها للمعرض. أول صورة جديدة ستصبح صورة الغلاف.</p>
      </div>
      <label class="flex items-center gap-2 text-sm">
        <input type="checkbox" name="is_active" value="1" class="rounded border-brand-dark/30" <?php echo ((int) $d['is_active'] === 1) ? 'checked' : ''; ?>>
        نشط
      </label>
      <button type="submit" class="w-full rounded-2xl bg-brand-purple py-3 text-sm font-semibold text-white shadow-lg hover:brightness-110 transition">حفظ التعديلات</button>
    </form>
  </main>
</body>
</html>
