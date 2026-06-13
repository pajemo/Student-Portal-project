<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

$locationHeader = 'Location: ';

if (Auth::check()) {
    header($locationHeader . app_url('index.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Your session token is invalid. Reload the page and try again.');
        header($locationHeader . app_url('login.php'));
        exit;
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (Auth::attemptStudent($username, $studentId, $password)) {
        Flash::set('success', 'Welcome back.');
        header($locationHeader . app_url('student/dashboard.php'));
        exit;
    }

    Flash::set('error', 'Invalid username or password.');
    header($locationHeader . app_url('login.php'));
    exit;
}

$flash = Flash::get();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= View::e(app_asset('images/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(app_asset('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(app_asset('images/favicon-16x16.png')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= View::e(app_asset('images/favicon.ico')) ?>">
    <link rel="manifest" href="<?= View::e(app_asset('images/site.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= View::e(app_asset('css/style.css')) ?>">
</head>
<body class="auth-bg aim-auth">
<div class="auth-wrap">
    <section class="auth-info fade-up aim-left">
        <img class="aim-left-logo" src="<?= View::e(app_asset('images/aim.png')) ?>" alt="Academic Info Manager logo">
        <h1>Academic Info Manager</h1>
        <p>You can also access the Student Portal on your mobile phone.</p>
        <p><a href="https://aim.knust.edu.gh/" target="_blank" rel="noopener noreferrer">Download App</a></p>
    </section>
    <section class="auth-card fade-up delay-1 aim-right-card">
        <img class="aim-right-logo" src="<?= View::e(app_asset('images/logo-light.png')) ?>" alt="University logo">
        <h2>Login</h2>
        <?php if ($flash): ?>
            <div class="alert <?= View::e((string) $flash['type']) ?>"><?= View::e((string) $flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= View::e(app_url('login.php')) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <label for="username">Username</label>
            <input id="username" name="username" type="text" placeholder="eg. username" required autocomplete="username">

            <label for="student_id">Student ID</label>
            <div class="toggle-field">
                <input id="student_id" name="student_id" type="password" placeholder="eg. 00000000" required autocomplete="off" data-toggle-visibility>
                <button class="toggle-visibility" type="button" data-toggle-target="student_id" aria-label="Show or hide student ID" aria-pressed="false"></button>
            </div>

            <label for="password">Password</label>
            <div class="toggle-field">
                <input id="password" name="password" type="password" placeholder="Enter password" required autocomplete="current-password" data-toggle-visibility>
                <button class="toggle-visibility" type="button" data-toggle-target="password" aria-label="Show or hide password" aria-pressed="false"></button>
            </div>

            <button class="btn" type="submit">Log In</button>
        </form>
        <p class="muted align-right"><a href="<?= View::e(app_url('forgot-password.php')) ?>">Forgot password?</a></p>
        <p class="muted ticket-line">Having issues accessing your portal? <a href="https://helpdesk.knust.edu.gh/open.php" target="_blank" rel="noopener noreferrer">Create a ticket</a></p>
        <p class="muted small">Admin? <a href="<?= View::e(app_url('admin/login.php')) ?>">Sign in here</a></p>
    </section>
</div>
<footer class="portal-footer auth-footer">Copyright © 2026 Kwame Nkrumah University Of Science and Technology by UITS</footer>
<script src="<?= View::e(app_asset('js/app.js')) ?>"></script>
</body>
</html>
