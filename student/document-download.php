<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Repositories\StudentDocumentRepository;
use App\Services\ActivityLogger;

Auth::requireRole('student');

$notFoundMessage = 'File not found.';
$studentId = (int) (Auth::user()['id'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit($notFoundMessage);
}

$repo = new StudentDocumentRepository(Database::connection());
$document = $repo->findForStudent($id, $studentId);
if (!$document) {
    http_response_code(404);
    exit($notFoundMessage);
}

$storageDir = dirname(__DIR__) . '/storage/student-documents';
$filePath = $storageDir . '/' . $document['stored_name'];
if (!is_file($filePath)) {
    http_response_code(404);
    exit($notFoundMessage);
}

ActivityLogger::log('pdf_document_downloaded', [
    'subject_type' => 'document',
    'subject_id' => (string) $document['id'],
    'details' => [
        'title' => $document['title'],
        'file_name' => $document['original_name'],
        'mime_type' => $document['mime_type'],
        'academic_year' => $document['academic_year'],
        'semester' => $document['semester'],
    ],
]);

$contentType = (string) ($document['mime_type'] ?? 'application/octet-stream');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . basename((string) $document['original_name']) . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);

