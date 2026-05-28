<?php
/**
 * تسجيل دخول لوحة التحكم (مشروع مدرسي — غيّر كلمة المرور في الإنتاج).
 */
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

hq_admin_session_start();

if (hq_admin_is_logged_in()) {
    header('Location: admin.php');
    exit;
}

const HQ_ADMIN_USERNAME = 'admin';
const HQ_ADMIN_PASSWORD = 'hqlaptop';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    if ($user === HQ_ADMIN_USERNAME && $pass === HQ_ADMIN_PASSWORD) {
        $_SESSION['hq_admin_ok'] = true;
        session_regenerate_id(true);
        $next = isset($_GET['next']) ? (string) $_GET['next'] : 'admin.php';
        $next = rawurldecode($next);
        $next = hq_admin_sanitize_next($next);
        header('Location: ' . $next);
        exit;
    }
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
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
  <title>دخول الإدارة — HQ Laptop</title>
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
<body class="min-h-screen bg-brand-light text-brand-dark antialiased flex flex-col items-center justify-center px-4">
  <div class="pointer-events-none fixed inset-0 -z-10">
    <div class="absolute top-1/4 -right-20 h-80 w-80 rounded-full bg-purple-500/25 blur-3xl"></div>
    <div class="absolute bottom-1/4 -left-20 h-72 w-72 rounded-full bg-brand-purple/15 blur-3xl"></div>
  </div>

  <div class="w-full max-w-md glass-panel rounded-3xl border border-white/50 p-8 shadow-xl shadow-brand-purple/10">
    <h1 class="text-xl font-bold text-brand-dark text-center">لوحة التحكم</h1>
    <p class="mt-2 text-center text-sm text-brand-dark/60">HQ Laptop — تسجيل الدخول</p>

    <?php if ($error !== ''): ?>
      <div class="mt-6 rounded-xl border border-red-200/60 bg-red-50/90 px-4 py-3 text-sm text-red-800"><?php echo h($error); ?></div>
    <?php endif; ?>

    <form method="post" action="" class="mt-8 space-y-5">
      <div>
        <label for="username" class="block text-sm font-medium text-brand-dark/80 mb-1.5">اسم المستخدم</label>
        <input id="username" name="username" required autocomplete="username"
          class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <div>
        <label for="password" class="block text-sm font-medium text-brand-dark/80 mb-1.5">كلمة المرور</label>
        <input id="password" name="password" type="password" required autocomplete="current-password"
          class="w-full rounded-xl border border-white/60 bg-white/50 px-4 py-3 focus:border-brand-purple focus:outline-none focus:ring-2 focus:ring-brand-purple/25">
      </div>
      <button type="submit" class="w-full rounded-2xl bg-brand-purple py-3 text-sm font-semibold text-white shadow-lg shadow-brand-purple/25 hover:brightness-110 transition">
        دخول
      </button>
    </form>

    <p class="mt-8 text-center">
      <a href="index.php" class="text-sm text-brand-purple hover:underline">← العودة للمتجر</a>
    </p>
  </div>
</body>
</html>
