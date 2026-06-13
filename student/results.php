<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\ResultRepository;
use App\Services\ResultService;

Auth::requireRole('student');
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$resultService = new ResultService(new ResultRepository(Database::connection()));
$bundle = $resultService->getCurrentTermResultBundle($studentId);

$pageTitle = 'Current Results';
$activePage = 'results';

ob_start();
?>
<section class="panel">
    <h3>Result Sheet</h3>
    <p class="muted">Academic Year: <?= $bundle['term'] ? View::e((string) $bundle['term']['academic_year']) : 'N/A' ?> | Semester: <?= $bundle['term'] ? View::e((string) $bundle['term']['semester']) : 'N/A' ?></p>
    <p class="muted">GPA: <?= $bundle['gpa'] !== null ? View::e(number_format((float) $bundle['gpa'], 2)) : 'N/A' ?> | CGPA: <?= $bundle['cgpa'] !== null ? View::e(number_format((float) $bundle['cgpa'], 2)) : 'N/A' ?></p>
</section>

<section class="table-wrap">
    <?php if ($bundle['rows'] === []): ?>
        <p class="muted">No current-term results found.</p>
    <?php else: ?>
        <table>
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
