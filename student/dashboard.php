<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\ResultRepository;
use App\Repositories\StudentDocumentRepository;
use App\Repositories\StudentRepository;
use App\Services\ResultService;

Auth::requireRole('student');
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$pdo = Database::connection();
$studentRepo = new StudentRepository($pdo);
$resultService = new ResultService(new ResultRepository($pdo));
$documentRepo = new StudentDocumentRepository($pdo);
$student = $studentRepo->findById($studentId);
$bundle = $resultService->getCurrentTermResultBundle($studentId);
$documents = $documentRepo->forStudent($studentId);
$currentTermDocument = null;
$tuitionFeePaid = (int) ($student['tuition_fee_paid'] ?? 0) === 1;
$examsFeePaid = (int) ($student['exams_fee_paid'] ?? 0) === 1;
$tuitionFeeAmount = $tuitionFeePaid ? 0.0 : (float) ($student['tuition_fee_amount'] ?? 0);
$examsFeeAmount = $examsFeePaid ? 0.0 : (float) ($student['exams_fee_amount'] ?? 0);

if ($bundle['term']) {
    foreach ($documents as $document) {
        $sameYear = (string) ($document['academic_year'] ?? '') === (string) ($bundle['term']['academic_year'] ?? '');
        $sameSemester = (string) ($document['semester'] ?? '') === (string) ($bundle['term']['semester'] ?? '');
        if ($sameYear && $sameSemester) {
            $currentTermDocument = $document;
            break;
        }
    }
}

$pageTitle = 'Student Dashboard';
$activePage = 'dashboard';

ob_start();
?>
<div class="stats-grid">
    <article class="stat">
        <h4>Current Term</h4>
        <p><?= $bundle['term'] ? View::e((string) $bundle['term']['academic_year'] . ' / Sem ' . (string) $bundle['term']['semester']) : 'N/A' ?></p>
    </article>
    <article class="stat">
        <h4>Term GPA</h4>
        <p><?= $bundle['gpa'] !== null ? View::e(number_format((float) $bundle['gpa'], 2)) : 'N/A' ?></p>
    </article>
    <article class="stat">
        <h4>Cumulative GPA</h4>
        <p><?= $bundle['cgpa'] !== null ? View::e(number_format((float) $bundle['cgpa'], 2)) : 'N/A' ?></p>
    </article>
    <article class="stat">
        <h4>Status</h4>
        <p><span class="status-badge <?= strtolower((string) ($student['status'] ?? '')) ?>"><?= View::e((string) ($student['status'] ?? 'N/A')) ?></span></p>
    </article>
</div>

<section class="panel welcome-section">
    <div class="welcome-header">
        <div class="welcome-content">
            <h3>Welcome, <?= View::e(trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''))) ?></h3>
            <p class="muted"><strong>Program:</strong> <?= View::e((string) ($student['program'] ?? 'N/A')) ?><br><strong>Faculty:</strong> <?= View::e((string) ($student['faculty'] ?? 'N/A')) ?> | Level: <?= View::e((string) ($student['level'] ?? 'N/A')) ?></p>
            <p class="muted"><strong>Admission Year:</strong> <?= View::e((string) ($student['admission_year'] ?? 'N/A')) ?> | Status: <?= View::e((string) ($student['status'] ?? 'N/A')) ?></p>
        </div>
    </div>
</section>

<section class="panel fee-panel">
    <h3>Fees</h3>
    <div class="fee-grid">
        <article class="fee-item <?= $tuitionFeePaid ? 'paid' : 'unpaid' ?>">
            <h4>Tuition Fee</h4>
            <p class="fee-amount"><?= View::e(number_format($tuitionFeeAmount, 2)) ?></p>
            <p class="fee-status"><?= $tuitionFeePaid ? 'Paid' : 'Not Paid' ?></p>
        </article>
        <article class="fee-item <?= $examsFeePaid ? 'paid' : 'unpaid' ?>">
            <h4>Exams Fee</h4>
            <p class="fee-amount"><?= View::e(number_format($examsFeeAmount, 2)) ?></p>
            <p class="fee-status"><?= $examsFeePaid ? 'Paid' : 'Not Paid' ?></p>
        </article>
    </div>
</section>

<section class="table-wrap">
    <h3>
        Current Term Results Snapshot
        <?php if ($bundle['term']): ?>
            (<?= View::e((string) $bundle['term']['academic_year']) ?> / Semester <?= View::e((string) $bundle['term']['semester']) ?>)
        <?php endif; ?>
    </h3>
    <?php if ($currentTermDocument): ?>
        <p><a href="<?= View::e(app_url('student/document-download.php')) ?>?id=<?= (int) $currentTermDocument['id'] ?>">Download Current Term Result File</a></p>
    <?php else: ?>
        <p class="muted small">No uploaded result file for the current term yet.</p>
    <?php endif; ?>
    <?php if ($bundle['rows'] === []): ?>
        <p class="muted">No results available yet.</p>
    <?php else: ?>
        <table class="results-table">
            <thead>
            <tr>
                <th>Course Code</th>
                <th>Course Title</th>
                <th>Credits</th>
                <th>Grade</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($bundle['rows'] as $row): ?>
                <tr>
                    <td><?= View::e((string) $row['course_code']) ?></td>
                    <td><?= View::e((string) $row['title']) ?></td>
                    <td><?= View::e((string) $row['credit_hours']) ?></td>
                    <td><?= View::e((string) $row['grade']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-student.php';
