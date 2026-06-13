<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\StudentRepository;

Auth::requireRole('student');
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$repo = new StudentRepository(Database::connection());
$student = $repo->findById($studentId);

$pageTitle = 'Account Profile';
$activePage = 'profile';

ob_start();
?>
<section class="panel">
    <h3>Student Profile</h3>
    <p class="muted">Personal and academic details on record.</p>
</section>

<section class="panel">
    <div class="stack-2">
        <div>
            <label for="full_name">Full Name</label>
            <input id="full_name" type="text" value="<?= View::e(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''))) ?>" disabled>
        </div>
        <div>
            <label for="student_id_view">Student ID</label>
            <input id="student_id_view" type="text" value="<?= View::e((string) ($student['student_id'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="program">Program</label>
            <input id="program" type="text" value="<?= View::e((string) ($student['program'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="faculty">Faculty</label>
            <input id="faculty" type="text" value="<?= View::e((string) ($student['faculty'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="level">Level</label>
            <input id="level" type="text" value="<?= View::e((string) ($student['level'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="admission_year">Admission Year</label>
            <input id="admission_year" type="text" value="<?= View::e((string) ($student['admission_year'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" type="text" value="<?= View::e((string) ($student['email'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label for="phone">Phone</label>
            <input id="phone" type="text" value="<?= View::e((string) ($student['phone'] ?? '')) ?>" disabled>
        </div>
        <div>
            <label>Status</label>
            <div style="padding: 11px 12px; font-size: 0.95rem;">
                <span class="status-badge <?= strtolower((string) ($student['status'] ?? '')) ?>"><?= View::e((string) ($student['status'] ?? 'N/A')) ?></span>
            </div>
        </div>
    </div>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-student.php';
