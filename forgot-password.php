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

$flash = Flash::get();
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Your session token is invalid. Reload the page and try again.');
        header('Location: ' . app_url('forgot-password.php'));
        exit;
    }

    $studentId = trim((string) ($_POST['student_id'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));

    $service = new PasswordResetService(new StudentRepository(Database::connection()), new PasswordResetRepository(Database::connection()));
    $result = $service->requestStudentReset($studentId, $email);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
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
        <h1>Password Reset</h1>
        <p>Request a student password reset using your Student ID and registered email address.</p>
        <ul>
            <li>Student self-service</li>
            <li>Reset link expires in 30 minutes</li>
            <li>Local demo shows the reset link on screen</li>
        </ul>
    </section>
    <section class="auth-card fade-up delay-1">
        <h2>Reset Request</h2>
        <?php if ($flash): ?>
            <div class="alert <?= View::e((string) $flash['type']) ?>"><?= View::e((string) $flash['message']) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= View::e(app_url('forgot-password.php')) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <label for="student_id">Student ID</label>
            <input id="student_id" name="student_id" type="text" required>

            <label for="email">Email</label>
            <input id="email" name="email" type="email" required>

            <button class="btn" type="submit">Generate Reset Link</button>
        </form>
        <p class="muted"><a href="<?= View::e(app_url('login.php')) ?>">Back to login</a></p>

        <?php if (is_array($result) && ($result['ok'] ?? false)): ?>
            <div class="panel" style="margin-top:16px;">
                <p class="muted">Reset link generated for <?= View::e((string) $result['student']['student_id']) ?>.</p>
                <p><a href="<?= View::e(app_url('reset-password.php')) ?>?token=<?= View::e((string) $result['token']) ?>">Open reset link</a></p>
                <p class="small muted">This link expires at <?= View::e((string) $result['expires_at']) ?>.</p>
            </div>
        <?php elseif (is_array($result) && !($result['ok'] ?? false)): ?>
            <div class="alert error" style="margin-top:16px;"><?= View::e((string) $result['message']) ?></div>
        <?php endif; ?>
    </section>
</div>
<footer class="portal-footer auth-footer">Copyright © 2026 Kwame Nkrumah University Of Science and Technology by UITS</footer>
</body>
</html>
