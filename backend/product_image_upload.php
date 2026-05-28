<?php
/**
 * حفظ صورة منتج مرفوعة عبر النموذج (multipart).
 * الملفات تُخزَّن تحت assets/images/uploads/ باسم عشوائي + امتداد آمن.
 *
 * ملاحظة: فشل «حفظ الملف على الخادم» غالبًا بسبب صلاحيات المجلد (ليس قاعدة البيانات).
 * MySQL يخزّن فقط النص في image_path بعد نجاح الرفع.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
$DEMO_VULNERABLE = $GLOBALS['DEMO_VULNERABLE'] ?? true;

const HQ_UPLOAD_MAX_BYTES = 10242880; // 5 ميجابايت

/** @return array{ok: true, path: string|null}|array{ok: false, error: string} */
function hq_try_save_product_image(string $fieldName = 'product_image'): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['ok' => true, 'path' => null];
    }

    $f = $_FILES[$fieldName];
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($err === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'path' => null];
    }

    if ($err !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'تعذر استلام الملف. جرّب صورة أصغر أو تحقق من إعدادات PHP (upload_max_filesize).'];
    }

    if (!is_uploaded_file($f['tmp_name'])) {
        return ['ok' => false, 'error' => 'ملف غير صالح.'];
    }

    if (($f['size'] ?? 0) > HQ_UPLOAD_MAX_BYTES) {
        return ['ok' => false, 'error' => 'حجم الصورة يتجاوز 5 ميجابايت.'];
    }

    $tmp = $f['tmp_name'];

    if ($DEMO_VULNERABLE) {
        // ================= VULNERABLE MODE =================
        // Trust client filename + extension only → can upload polyglot / disguised payloads (e.g. *.php.jpg).
        // TO BE REMOVED / VULNERABLE
        $clientName = basename((string) ($f['name'] ?? '')); // TO BE REMOVED / VULNERABLE
        if ($clientName === '') {
            return ['ok' => false, 'error' => 'اسم الملف غير صالح.'];
        }

        $ext = strtolower((string) pathinfo($clientName, PATHINFO_EXTENSION));
        $weak = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        if (!in_array($ext, $weak, true)) {
            return ['ok' => false, 'error' => 'امتداد غير مسموح.'];
        }

        $root = realpath(dirname(__DIR__));
        if ($root === false) {
            return ['ok' => false, 'error' => 'تعذر تحديد مسار المشروع على الخادم.'];
        }

        $dir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                return ['ok' => false, 'error' => 'تعذر إنشاء مجلد الرفع assets/images/uploads/.'];
            }
        }

        @chmod($dir, 0775);

        if (!is_writable($dir)) {
            return [
                'ok' => false,
                'error' => 'مجلد الرفع غير قابل للكتابة من Apache.',
            ];
        }

        $dest = $dir . DIRECTORY_SEPARATOR . $clientName; // TO BE REMOVED / VULNERABLE
        if (!@move_uploaded_file($tmp, $dest)) {
            return ['ok' => false, 'error' => 'تعذر حفظ الملف على الخادم.'];
        }

        @chmod($dest, 0644);

        return ['ok' => true, 'path' => 'assets/images/uploads/' . $clientName];
    }

    // ================= SECURE MODE =====================
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    if ($mime === false) {
        return ['ok' => false, 'error' => 'تعذر التحقق من نوع الملف.'];
    }

    $map = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($map[$mime])) {
        return ['ok' => false, 'error' => 'الصيغ المسموحة: JPG، PNG، WEBP، GIF.'];
    }

    $ext = $map[$mime];
    $root = realpath(dirname(__DIR__));
    if ($root === false) {
        return ['ok' => false, 'error' => 'تعذر تحديد مسار المشروع على الخادم.'];
    }

    $dir = $root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'error' => 'تعذر إنشاء مجلد الرفع assets/images/uploads/.'];
        }
    }

    @chmod($dir, 0775);

    if (!is_writable($dir)) {
        return [
            'ok' => false,
            'error' => 'مجلد الرفع غير قابل للكتابة من Apache. الحل: من Terminal داخل مجلد المشروع hqlaptop نفّذ: chmod -R 775 assets/images/uploads  (أو امنح المجلد صلاحية الكتابة لمستخدم خادم الويب مثل _www أو daemon على macOS).',
        ];
    }

    $basename = 'p-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $dir . DIRECTORY_SEPARATOR . $basename;

    if (!@move_uploaded_file($tmp, $dest)) {
        return [
            'ok' => false,
            'error' => 'تعذر حفظ الملف على الخادم. تحقق من صلاحيات المجلد assets/images/uploads أو من open_basedir في php.ini — هذا ليس خطأ قاعدة بيانات.',
        ];
    }

    @chmod($dest, 0644);

    return ['ok' => true, 'path' => 'assets/images/uploads/' . $basename];
}

/**
 * حفظ عدة صور من input متعدد name="...[]".
 * @return array{ok: true, paths: list<string>}|array{ok: false, error: string}
 */
function hq_try_save_product_images(string $fieldName = 'product_images', int $maxFiles = 8): array
{
    if (!isset($_FILES[$fieldName]) || !is_array($_FILES[$fieldName])) {
        return ['ok' => true, 'paths' => []];
    }

    $bag = $_FILES[$fieldName];
    $names = $bag['name'] ?? [];
    $tmpNames = $bag['tmp_name'] ?? [];
    $errors = $bag['error'] ?? [];
    $sizes = $bag['size'] ?? [];

    if (!is_array($names) || !is_array($tmpNames) || !is_array($errors) || !is_array($sizes)) {
        return ['ok' => false, 'error' => 'صيغة ملفات غير صالحة.'];
    }

    $count = count($names);
    if ($count === 0) {
        return ['ok' => true, 'paths' => []];
    }
    if ($count > $maxFiles) {
        return ['ok' => false, 'error' => 'الحد الأقصى لعدد الصور هو ' . $maxFiles . '.'];
    }

    $saved = [];
    for ($i = 0; $i < $count; $i++) {
        $single = [
            'name' => $names[$i] ?? '',
            'type' => '',
            'tmp_name' => $tmpNames[$i] ?? '',
            'error' => $errors[$i] ?? UPLOAD_ERR_NO_FILE,
            'size' => $sizes[$i] ?? 0,
        ];

        if ((int) $single['error'] === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        $_FILES['__hq_single'] = $single;
        $res = hq_try_save_product_image('__hq_single');
        unset($_FILES['__hq_single']);

        if (!$res['ok']) {
            foreach ($saved as $rel) {
                $full = dirname(__DIR__) . '/' . $rel;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            return ['ok' => false, 'error' => $res['error']];
        }
        if ($res['path'] !== null) {
            $saved[] = $res['path'];
        }
    }

    return ['ok' => true, 'paths' => $saved];
}
