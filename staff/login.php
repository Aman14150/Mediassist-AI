<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/staff-auth.php';

require_local_staff_access();
start_staff_session();
if (staff_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    rate_limit('staff-login', 8, 900);
    try {
        verify_csrf((string) ($_POST['csrf'] ?? ''));
        $hash = env('STAFF_PASSWORD_HASH', '') ?? '';
        if ($hash === '' || !password_verify((string) ($_POST['password'] ?? ''), $hash)) {
            throw new RuntimeException('Incorrect password.');
        }
        session_regenerate_id(true);
        $_SESSION['staff_authenticated'] = true;
        header('Location: index.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage() === 'Incorrect password.' ? $exception->getMessage() : 'Unable to sign in. Please try again.';
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Staff sign in | Medicare Hospital</title><link href="../assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f3f7fa}.login-card{max-width:430px;margin:10vh auto;border:0;border-radius:18px;box-shadow:0 12px 40px rgba(20,60,80,.12)}</style></head>
<body><main class="container"><div class="card login-card"><div class="card-body p-5">
<h1 class="h3 mb-2">Hospital staff</h1><p class="text-secondary mb-4">Sign in to view confirmed appointments.</p>
<?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<label for="password" class="form-label">Staff password</label><input class="form-control form-control-lg mb-3" id="password" name="password" type="password" required autofocus autocomplete="current-password">
<button class="btn btn-primary btn-lg w-100" type="submit">Sign in</button></form>
<p class="small text-secondary mt-4 mb-0">Use HTTPS in production. Access is logged and rate-limited.</p>
</div></div></main></body></html>
