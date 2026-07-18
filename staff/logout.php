<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/../app/staff-auth.php';
require_local_staff_access();
start_staff_session();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf((string) ($_POST['csrf'] ?? ''));
    $_SESSION = [];
    session_destroy();
}
header('Location: login.php');
exit;
