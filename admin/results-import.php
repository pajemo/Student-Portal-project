<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\ResultRepository;
use App\Repositories\StudentRepository;
use App\Services\ImportService;

Auth::requireRole('admin');
$pdo = Database::connection();
$report = null;
$resultsImportUrl = app_url('admin/results-import.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Invalid CSRF token.');
        header('Location: ' . $resultsImportUrl);
        exit;
    }

    if (!isset($_FILES['results_csv']) || !Validator::csvFile($_FILES['results_csv'])) {
        Flash::set('error', 'Please upload a valid CSV file.');
        header('Location: ' . $resultsImportUrl);
        exit;
    }

    $service = new ImportService($pdo, new StudentRepository($pdo), new ResultRepository($pdo));
    $admin = Auth::user();
    $report = $service->importCsv((string) $_FILES['results_csv']['tmp_name'], (int) ($admin['id'] ?? 0), (string) $_FILES['results_csv']['name']);
}

$pageTitle = 'CSV Result Import';
$activePage = 'import';

ob_start();
?>
<section class="panel">
    <h3>Bulk Result Import</h3>
    <p class="muted">Upload a CSV with headers:<br><strong>student_id, academic_year, semester, course_code, course_title, credit_hours, grade</strong></p>
</section>

<section class="panel">
    <form method="post" action="<?= View::e($resultsImportUrl) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <label for="results_csv">CSV File</label>
        <input id="results_csv" name="results_csv" type="file" accept=".csv" required>
        <button class="btn alt" type="submit">Import CSV</button>
    </form>
</section>

<?php if ($report !== null): ?>
    <section class="panel">
        <h3>Import Report</h3>
        <p>Rows imported: <strong><?= View::e((string) $report['success']) ?></strong></p>
        <p>Rows failed: <strong><?= View::e((string) $report['failed']) ?></strong></p>
        <?php if (!empty($report['errors'])): ?>
            <div class="alert error">
                <?php foreach ($report['errors'] as $error): ?>
                    <div><?= View::e((string) $error) ?></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert success">Import completed successfully.</div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-admin.php';
