<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;

$user = Auth::user();
$flash = Flash::get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Admin console for managing student records, grades, documents, and system data.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="google-site-verification" content="">
    <title><?= View::e($pageTitle ?? 'Admin Portal') ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= View::e(app_asset('images/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(app_asset('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(app_asset('images/favicon-16x16.png')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= View::e(app_asset('images/favicon.ico')) ?>">
    <link rel="manifest" href="<?= View::e(app_asset('images/site.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= View::e(app_asset('css/style.css')) ?>">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "Kwame Nkrumah University Of Science and Technology",
      "url": "<?= View::e(app_url('')) ?>",
      "description": "Academic results and student portal"
    }
    </script>
</head>
<body class="portal-bg admin-theme">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <h1>Admin Console</h1>
            <p>Exam Records</p>
        </div>
        <nav>
            <a class="nav-link <?= ($activePage ?? '') === 'students' ? 'active' : '' ?>" href="<?= View::e(app_url('admin/student-controls.php')) ?>">Student Controls</a>
            <a class="nav-link <?= ($activePage ?? '') === 'import' ? 'active' : '' ?>" href="<?= View::e(app_url('admin/results-import.php')) ?>">CSV Import</a>
            <a class="nav-link <?= ($activePage ?? '') === 'documents' ? 'active' : '' ?>" href="<?= View::e(app_url('admin/documents.php')) ?>">Result Files</a>
            <a class="nav-link <?= ($activePage ?? '') === 'reset' ? 'active' : '' ?>" href="<?= View::e(app_url('admin/password-reset.php')) ?>">Password Reset</a>
            <a class="nav-link <?= ($activePage ?? '') === 'logs' ? 'active' : '' ?>" href="<?= View::e(app_url('admin/activity-logs.php')) ?>">Activity Logs</a>
        </nav>
        <a class="nav-link signout" href="<?= View::e(app_url('logout.php')) ?>">Sign Out</a>
    </aside>
    <main class="content">
        <header class="topbar">
            <h2><?= View::e($pageTitle ?? 'Admin Portal') ?></h2>
            <div class="pill"><?= View::e((string) ($user['name'] ?? 'Admin')) ?></div>
        </header>

        <?php if ($flash): ?>
            <div class="alert <?= View::e((string) $flash['type']) ?>"><?= View::e((string) $flash['message']) ?></div>
        <?php endif; ?>

        <?= $content ?? '' ?>
        <footer class="portal-footer">Copyright © 2026 Kwame Nkrumah University Of Science and Technology by UITS</footer>
    </main>
</div>
<script src="<?= View::e(app_asset('js/app.js')) ?>"></script>
</body>
</html>
