<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

if (Auth::check() && Auth::role() === 'admin') {
    header('Location: ' . app_url('admin/results-manage.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Your session token is invalid. Reload the page and try again.');
        header('Location: ' . app_url('admin/login.php'));
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::attemptAdmin($username, $password)) {
        Flash::set('success', 'Admin sign in successful.');
        header('Location: ' . app_url('admin/results-manage.php'));
        exit;
    }

    Flash::set('error', 'Invalid username or password.');
    header('Location: ' . app_url('admin/login.php'));
    exit;
}

$flash = Flash::get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= View::e(app_asset('images/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(app_asset('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(app_asset('images/favicon-16x16.png')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= View::e(app_asset('images/favicon.ico')) ?>">
    <link rel="manifest" href="<?= View::e(app_asset('images/site.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= View::e(app_asset('css/style.css')) ?>">
</head>
<body class="auth-bg">
<div class="auth-wrap single-card-auth">
    <section class="auth-card fade-up delay-1">
        <h2>Admin Login</h2>
        <?php if ($flash): ?>
            <div class="alert <?= View::e((string) $flash['type']) ?>"><?= View::e((string) $flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= View::e(app_url('admin/login.php')) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" required autocomplete="username">

            <label for="password">Password</label>
            <div class="toggle-field">
                <input id="password" name="password" type="password" required autocomplete="current-password" data-toggle-visibility>
                <button class="toggle-visibility" type="button" data-toggle-target="password" aria-label="Show or hide password" aria-pressed="false"></button>
            </div>

            <button class="btn alt" type="submit">Sign In</button>
        </form>
        <p class="muted">Student? <a href="<?= View::e(app_url('login.php')) ?>">Go to student login</a></p>
    </section>
</div>
<footer class="portal-footer auth-footer">Copyright © 2026 Kwame Nkrumah University Of Science and Technology by UITS</footer>
<script src="<?= View::e(app_asset('js/app.js')) ?>"></script>
</body>
</html>
