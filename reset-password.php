<?php

declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\PasswordResetRepository;
use App\Repositories\StudentRepository;
use App\Services\PasswordResetService;

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$service = new PasswordResetService(new StudentRepository(Database::connection()), new PasswordResetRepository(Database::connection()));
$message = null;
$isValid = $token !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($csrf) ? $csrf : null)) {
        Flash::set('error', 'Your session token is invalid. Reload the page and try again.');
        header('Location: ' . app_url('reset-password.php') . '?token=' . urlencode($token));
        exit;
    }

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($password === '' || strlen($password) < 8 || $password !== $confirm) {
        $message = 'Passwords must match and be at least 8 characters.';
    } else {
        $result = $service->completeStudentReset($token, $password);
        if (($result['ok'] ?? false) === true) {
            Flash::set('success', 'Password updated successfully.');
            header('Location: ' . app_url('login.php'));
            exit;
        }

        $message = (string) ($result['message'] ?? 'Unable to reset password.');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="apple-touch-icon" sizes="180x180" href="<?= View::e(app_asset('images/apple-touch-icon.png')) ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= View::e(app_asset('images/favicon-32x32.png')) ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= View::e(app_asset('images/favicon-16x16.png')) ?>">
    <link rel="icon" type="image/x-icon" href="<?= View::e(app_asset('images/favicon.ico')) ?>">
    <link rel="manifest" href="<?= View::e(app_asset('images/site.webmanifest')) ?>">
    <link rel="stylesheet" href="<?= View::e(app_asset('css/style.css')) ?>">
</head>
<body class="auth-bg">
<div class="auth-wrap">
    <section class="auth-info fade-up">
        <h1>Set New Password</h1>
        <p>Use the reset token you requested to create a new password.</p>
    </section>
    <section class="auth-card fade-up delay-1">
        <h2>Reset Password</h2>
        <?php if ($message): ?>
            <div class="alert error"><?= View::e($message) ?></div>
        <?php endif; ?>
        <?php if (!$isValid): ?>
            <div class="alert error">Missing reset token.</div>
        <?php else: ?>
            <form method="post" action="<?= View::e(app_url('reset-password.php')) ?>">
                <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                <input type="hidden" name="token" value="<?= View::e($token) ?>">
                <label for="password">New Password</label>
                <input id="password" name="password" type="password" required minlength="8">

                <label for="password_confirm">Confirm Password</label>
                <input id="password_confirm" name="password_confirm" type="password" required minlength="8">

                <button class="btn" type="submit">Update Password</button>
            </form>
        <?php endif; ?>
    </section>
</div>
<footer class="portal-footer auth-footer">Copyright © 2026 Kwame Nkrumah University Of Science and Technology by UITS</footer>
</body>
</html>
