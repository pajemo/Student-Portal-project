<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;
use App\Repositories\StudentRepository;
use App\Services\PasswordResetService;

Auth::requireRole('admin');
$redirectUrl = app_url('admin/password-reset.php');
$locationHeader = 'Location: ';
$studentRepo = new StudentRepository(Database::connection());
$service = new PasswordResetService($studentRepo, new \App\Repositories\PasswordResetRepository(Database::connection()));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Invalid CSRF token.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $studentId = (int) ($_POST['student_pk'] ?? 0);
    $password = (string) ($_POST['password'] ?? '');

    if ($studentId < 1 || strlen($password) < 8) {
        Flash::set('error', 'Select a student and enter a password of at least 8 characters.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    if ($service->adminResetStudentPassword($studentId, $password)) {
        Flash::set('success', 'Student password updated.');
    } else {
        Flash::set('error', 'Student not found.');
    }

    header($locationHeader . $redirectUrl);
    exit;
}

$students = $studentRepo->allForAdmin();
$pageTitle = 'Password Reset';
$activePage = 'reset';

ob_start();
?>
<section class="panel">
    <h3>Admin Password Reset</h3>
    <p class="muted">Reset a student password directly from the admin console.</p>
</section>

<section class="panel">
    <form method="post" action="<?= View::e(app_url('admin/password-reset.php')) ?>">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <label for="student_pk">Student</label>
        <select id="student_pk" name="student_pk" required>
            <option value="">Select student</option>
            <?php foreach ($students as $student): ?>
                <option value="<?= View::e((string) $student['id']) ?>"><?= View::e((string) $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="password">New Password</label>
        <input id="password" name="password" type="password" required minlength="8">

        <button class="btn alt" type="submit">Reset Password</button>
    </form>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-admin.php';

