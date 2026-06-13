<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;

Auth::requireRole('admin');
header('Location: ' . app_url('admin/student-controls.php'));
exit;

