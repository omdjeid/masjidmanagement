<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_url(is_logged_in() ? 'dashboard.php' : 'login.php'));
    exit;
}

require_csrf_token(is_logged_in() ? 'dashboard.php' : 'login.php');
logout_user();

header('Location: ' . app_url('login.php'));
exit;
