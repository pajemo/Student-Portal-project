<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\StudentDocumentRepository;

Auth::requireRole('student');
$pdo = Database::connection();
$repo = new StudentDocumentRepository($pdo);
$user = Auth::user();
$documents = $repo->forStudent((int) ($user['id'] ?? 0));

$pageTitle = 'Result File Downloads';
$activePage = 'documents';

ob_start();
?>
<section class="panel">
    <h3>Result File Downloads</h3>
    <p class="muted">Access your semester result files (PDF or ZIP).</p>
</section>

<?php if ($documents === []): ?>
    <section class="panel"><p class="muted">No result files are available yet.</p></section>
<?php else: ?>
    <div class="documents-grid">
        <?php foreach ($documents as $document): ?>
            <article class="document-card">
                <div class="document-header">
                    <div class="document-meta">
                        <h4><?= View::e((string) $document['title']) ?></h4>
                        <p class="document-term"><?= View::e((string) $document['academic_year']) ?> • <strong>Semester <?= View::e((string) $document['semester']) ?></strong></p>
                    </div>
                </div>
                <div class="document-body">
                    <p class="document-filename">📄 <?= View::e((string) $document['original_name']) ?></p>
                    <div class="document-stats">
                        <span class="stat-item"><strong><?= View::e(number_format(((int) $document['file_size']) / 1024, 1)) ?></strong> KB</span>
                    </div>
                </div>
                <div class="document-footer">
                    <a href="<?= View::e(app_url('student/document-download.php')) ?>?id=<?= (int) $document['id'] ?>" class="download-btn">⬇ Download</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-student.php';

