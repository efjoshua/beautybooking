<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';

auth_boot();

/* Already signed in? Go straight through. */
if (is_logged_in()) {
    redirect('admin/index.php');
}

$error = null;
$email = '';
$next  = $_GET['next'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email  = trim((string) ($_POST['email'] ?? ''));
    $result = auth_login($email, (string) ($_POST['password'] ?? ''));

    if ($result['ok']) {
        /* Only follow `next` if it stays inside this app — an open redirect
           is a phishing gift. */
        $target = (string) ($_POST['next'] ?? '');
        if ($target !== '' && str_starts_with($target, BASE_URL . '/') && !str_contains($target, '//')) {
            header('Location: ' . $target);
            exit;
        }
        redirect('admin/index.php');
    }
    $error = $result['error'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Staff Login — <?= e(APP_NAME) ?></title>
<meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Montserrat:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/css/base.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/components.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/public.css') ?>">
<link rel="stylesheet" href="<?= url('assets/css/admin.css') ?>">
<link rel="icon" type="image/svg+xml" href="<?= url('assets/img/favicon.svg') ?>">
</head>
<body class="login-page">

<div class="login-card">
  <div class="text-center">
    <span class="wordmark" id="loginMark"></span>
    <h3 class="mt-5 mb-1">Staff Portal</h3>
    <p class="small muted">Sign in to manage bookings, clients and the business.</p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger mb-5">
      <span class="ico" data-icon="alert"></span>
      <span><b>Could not sign you in</b><p><?= e($error) ?></p></span>
    </div>
  <?php endif; ?>

  <form method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field">
      <label class="label" for="email">Email</label>
      <input class="input" id="email" name="email" type="email" autocomplete="username"
             value="<?= e($email) ?>" required autofocus>
    </div>
    <div class="field">
      <label class="label" for="pw">Password</label>
      <input class="input" id="pw" name="password" type="password"
             autocomplete="current-password" required>
    </div>
    <div class="flex items-center justify-between mb-5 wrapflex gap-3">
      <label class="check"><input type="checkbox" name="remember"> Keep me signed in</label>
      <a href="#" class="small t-pink" id="forgot">Forgot password?</a>
    </div>
    <button type="submit" class="btn btn-primary btn-block btn-lg">Sign In</button>
  </form>


  <p class="text-center small muted mt-6 mb-0">
    <a href="<?= url('public/index.php') ?>" class="link-arrow" style="font-size:.6875rem">← Back to the public site</a>
  </p>
</div>

<script src="<?= url('assets/js/ui.js') ?>"></script>
<script src="<?= url('assets/js/admin-ui.js') ?>"></script>
<script>
document.getElementById('loginMark').innerHTML =
  '<span class="crown">' + PBS.icon('crown') + '</span>' +
  '<span class="princess">Princess</span>' +
  '<span class="beauty">Beauty</span>' +
  '<span class="salon">Salon</span>';

document.getElementById('forgot').addEventListener('click', function (e) {
  e.preventDefault();
  PBS.toast('Password reset by email is not wired up yet — ask the owner to reset it.');
});
</script>
</body>
</html>
