<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Auth;

$user = Auth::user();
if (!$user) {
    header('Location: ' . app_url('login.php'));
    exit;
}

if (($user['role'] ?? null) === 'admin') {
    header('Location: ' . app_url('admin/results-manage.php'));
    exit;
}

header('Location: ' . app_url('student/dashboard.php'));
exit;
