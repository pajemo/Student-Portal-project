<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\ResultRepository;
use App\Repositories\StudentDocumentRepository;
use App\Services\ResultService;

Auth::requireRole('student');
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$resultService = new ResultService(new ResultRepository(Database::connection()));
$history = $resultService->getHistoryGrouped($studentId);
$documentRepo = new StudentDocumentRepository(Database::connection());
$documents = $documentRepo->forStudent($studentId);
$documentsByTerm = [];
foreach ($documents as $document) {
    $termKey = (string) ($document['academic_year'] ?? '') . '|' . (string) ($document['semester'] ?? '');
    $documentsByTerm[$termKey] = $document;
}

$pageTitle = 'Past Results';
$activePage = 'history';

ob_start();
?>
<section class="panel">
    <h3>Result History by Academic Year and Semester</h3>
    <p class="muted">All published results are listed below in reverse chronological order.</p>
</section>

<?php if ($history === []): ?>
    <section class="panel"><p class="muted">No result history available yet.</p></section>
<?php endif; ?>

<div class="history-grid">
<?php foreach ($history as $item): ?>
    <article class="term-card table-wrap">
        <div class="term-header">
            <h3><?= View::e((string) $item['term']['academic_year']) ?> - Semester <?= View::e((string) $item['term']['semester']) ?></h3>
            <p class="term-gpa">Term GPA: <strong><?= $item['gpa'] !== null ? View::e(number_format((float) $item['gpa'], 2)) : 'N/A' ?></strong></p>
        </div>
        <div class="term-content">
            <?php
            $termKey = (string) ($item['term']['academic_year'] ?? '') . '|' . (string) ($item['term']['semester'] ?? '');
            $termDocument = $documentsByTerm[$termKey] ?? null;
            ?>
            <?php if ($termDocument): ?>
                <p><a href="<?= View::e(app_url('student/document-download.php')) ?>?id=<?= (int) $termDocument['id'] ?>">📥 Download Result File for this Semester</a></p>
            <?php else: ?>
                <p class="muted small">No uploaded result file for this semester yet.</p>
            <?php endif; ?>
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
                <?php foreach ($item['rows'] as $row): ?>
                    <tr>
                        <td><?= View::e((string) $row['course_code']) ?></td>
                        <td><?= View::e((string) $row['title']) ?></td>
                        <td><?= View::e((string) $row['credit_hours']) ?></td>
                        <td><?= View::e((string) $row['grade']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-student.php';
