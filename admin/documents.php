<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Core\View;
use App\Repositories\StudentDocumentRepository;
use App\Repositories\StudentRepository;
use App\Services\ActivityLogger;

Auth::requireRole('admin');
$pdo = Database::connection();
$documentRepo = new StudentDocumentRepository($pdo);
$studentRepo = new StudentRepository($pdo);
$storageDir = dirname(__DIR__) . '/storage/student-documents';
$redirectUrl = app_url('admin/documents.php');
$locationHeader = 'Location: ';
$academicYearOptions = Validator::academicYearOptions(2023, 2030);

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0775, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Invalid CSRF token.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $academicYearInput = trim((string) ($_POST['academic_year'] ?? ''));
    $academicYear = Validator::normalizeAcademicYear($academicYearInput);
    $semester = (int) ($_POST['semester'] ?? 0);
    $file = $_FILES['document_file'] ?? null;

    if (!Validator::required($title) || !Validator::maxLength($title, 200) || $studentId < 1 || !Validator::academicYearAllowed($academicYearInput, 2023, 2030) || $academicYear === null || $semester < 1 || $semester > 10 || !is_array($file) || !Validator::pdfOrZipFile($file)) {
        Flash::set('error', 'Please select a student, choose an academic year from 2023/2024 to 2030/2031, choose a semester, and upload a valid PDF or ZIP file.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $student = $studentRepo->findById($studentId);
    if (!$student) {
        Flash::set('error', 'Selected student was not found.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $admin = Auth::user();
    $originalName = (string) $file['name'];
    $fileExt = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storedName = uniqid('doc_', true) . '.' . $fileExt;
    $mimeType = $fileExt === 'zip' ? 'application/zip' : 'application/pdf';
    $targetPath = $storageDir . '/' . $storedName;

    if (!move_uploaded_file((string) $file['tmp_name'], $targetPath)) {
        Flash::set('error', 'Unable to save the uploaded file.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $documentRepo->create([
        'admin_id' => (int) ($admin['id'] ?? 0),
        'student_id' => $studentId,
        'academic_year' => $academicYear,
        'semester' => $semester,
        'title' => $title,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_size' => (int) ($file['size'] ?? 0),
        'mime_type' => $mimeType,
    ]);

    ActivityLogger::log('document_uploaded', [
        'subject_type' => 'document',
        'subject_id' => $storedName,
        'details' => [
            'title' => $title,
            'original_name' => $originalName,
            'file_type' => strtoupper($fileExt),
            'student_id' => (string) $student['student_id'],
            'academic_year' => $academicYear,
            'semester' => $semester,
        ],
    ]);

    Flash::set('success', strtoupper($fileExt) . ' file uploaded successfully.');
    header($locationHeader . $redirectUrl);
    exit;
}

$documents = $documentRepo->recent(25);
$students = $studentRepo->allForAdmin();
$pageTitle = 'Result File Uploads';
$activePage = 'documents';

ob_start();
?>
<section class="panel">
    <h3>Upload Result File to Student Portal</h3>
    <p class="muted">Upload a semester result file (PDF or ZIP) for a specific student.</p>
</section>

<section class="panel">
    <form method="post" action="<?= View::e(app_url('admin/documents.php')) ?>" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <div class="stack-2">
            <div>
                <label for="student_id">Student</label>
                <select id="student_id" name="student_id" required>
                    <option value="">Select student</option>
                    <?php foreach ($students as $student): ?>
                        <option value="<?= (int) $student['id'] ?>"><?= View::e((string) $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="academic_year">Academic Year</label>
                <select id="academic_year" name="academic_year" required>
                    <option value="">Select academic year</option>
                    <?php foreach ($academicYearOptions as $academicYearOption): ?>
                        <option value="<?= View::e($academicYearOption) ?>"><?= View::e($academicYearOption) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="semester">Semester</label>
                <select id="semester" name="semester" required>
                    <option value="">Select semester</option>
                    <option value="1">1</option>
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4">4</option>
                    <option value="5">5</option>
                    <option value="6">6</option>
                    <option value="7">7</option>
                    <option value="8">8</option>
                    <option value="9">9</option>
                    <option value="10">10</option>
                </select>
            </div>
            <div>
                <label for="title">Document Title</label>
                <input id="title" name="title" type="text" maxlength="200" placeholder="Semester Result File" required>
            </div>
            <div>
                <label for="document_file">Result File</label>
                <input id="document_file" name="document_file" type="file" accept="application/pdf,.pdf,application/zip,.zip" required>
            </div>
        </div>
        <button class="btn alt" type="submit">Upload File</button>
    </form>
</section>

<section class="panel table-wrap">
    <h3>Recently Uploaded Files</h3>
    <?php if ($documents === []): ?>
        <p class="muted">No result files uploaded yet.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Title</th>
                <th>Student</th>
                <th>Semester</th>
                <th>Original File</th>
                <th>Size</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td><?= View::e((string) $document['title']) ?></td>
                    <td><?= View::e((string) ($document['student_code'] ?? '')) ?></td>
                    <td><?= View::e((string) ($document['academic_year'] ?? '')) ?> / Sem <?= View::e((string) ($document['semester'] ?? '')) ?></td>
                    <td><?= View::e((string) $document['original_name']) ?></td>
                    <td><?= View::e(number_format(((int) $document['file_size']) / 1024, 1)) ?> KB</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-admin.php';

