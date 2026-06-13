<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\View;
use App\Repositories\ActivityLogRepository;

Auth::requireRole('admin');
$logs = (new ActivityLogRepository(Database::connection()))->recent(100);

$pageTitle = 'Activity Logs';
$activePage = 'logs';

ob_start();
?>
<section class="panel">
    <h3>Audit Trail</h3>
    <p class="muted">Recent login, reset, import, export, and permission events.</p>
</section>

<section class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>Time</th>
            <th>Actor</th>
            <th>Action</th>
            <th>Subject</th>
            <th>Details</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= View::e((string) $log['created_at']) ?></td>
                <td><?= View::e((string) $log['actor_role']) ?><?= $log['actor_id'] ? ' #' . View::e((string) $log['actor_id']) : '' ?></td>
                <td><?= View::e((string) $log['action']) ?></td>
                <td><?= View::e(trim((string) ($log['subject_type'] ?? '') . ' ' . (string) ($log['subject_id'] ?? ''))) ?></td>
                <td><?= View::e((string) ($log['details'] ?? '')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-admin.php';
