<?php

declare(strict_types=1);


use App\Config\Database;
use App\Core\Auth;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\StudentRepository;

$user = Auth::user();
$flash = Flash::get();
$displayName = (string) ($user['name'] ?? 'Student');

if (($user['role'] ?? '') === 'student' && isset($user['id'])) {
    $studentRepo = new StudentRepository(Database::connection());
    $student = $studentRepo->findById((int) $user['id']);
    if ($student) {
        $freshName = trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
        if ($freshName !== '') {
            $displayName = $freshName;
            $_SESSION['auth']['name'] = $freshName;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Student academic results portal. View grades, past results, documents, and manage your academic profile.">
    <meta name="keywords" content="student portal, grades, results, academic, KNUST">
    <meta property="og:title" content="<?= View::e($pageTitle ?? 'Student Portal') ?>">
    <meta property="og:description" content="Student academic results portal">
    <meta property="og:type" content="website">
    <meta name="google-site-verification" content="">
    <title><?= View::e($pageTitle ?? 'Student Portal') ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= View::e(app_asset('images/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(app_asset('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(app_asset('images/favicon-16x16.png')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= View::e(app_asset('images/favicon.ico')) ?>">
    <link rel="manifest" href="<?= View::e(app_asset('images/site.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= View::e(app_asset('css/style.css')) ?>">
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebApplication",
      "name": "Student Result Portal",
      "description": "Student academic results and documents portal",
      "url": "<?= View::e(app_url('')) ?>",
      "applicationCategory": "BusinessApplication",
      "offers": {
        "@type": "Offer",
        "price": "0"
      }
    }
    </script>
</head>
<body class="portal-bg">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">
            <h1>Student Portal</h1>
            <img src="<?= View::e(app_asset('images/knustlogo.png')) ?>" alt="KNUST Logo" class="brand-logo">
        </div>
        <nav>
            <a class="nav-link <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= View::e(app_url('student/dashboard.php')) ?>">Dashboard</a>
            <a class="nav-link <?= ($activePage ?? '') === 'history' ? 'active' : '' ?>" href="<?= View::e(app_url('student/history.php')) ?>">Past Results</a>
            <?php /* <a class="nav-link <?= ($activePage ?? '') === 'documents' ? 'active' : '' ?>" href="<?= View::e(app_url('student/documents.php')) ?>">Result Files</a> */ ?>
            <a class="nav-link <?= ($activePage ?? '') === 'profile' ? 'active' : '' ?>" href="<?= View::e(app_url('student/profile.php')) ?>">Account Profile</a>
        </nav>
        <a class="nav-link signout" href="<?= View::e(app_url('logout.php')) ?>">Sign Out</a>
    </aside>
    <main class="content">
        <header class="topbar">
            <h2><?= View::e($pageTitle ?? 'Student Portal') ?></h2>
            <div class="pill topbar-user">
                <img src="<?= View::e(app_asset('images/profile.jpeg')) ?>" alt="Student Avatar" class="topbar-avatar">
                <span><?= View::e($displayName) ?></span>
            </div>
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
