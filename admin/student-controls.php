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
use App\Services\ActivityLogger;

Auth::requireRole('admin');

$pdo = Database::connection();
$studentRepo = new StudentRepository($pdo);
$resultRepo = new ResultRepository($pdo);
$studentControlsUrl = app_url('admin/student-controls.php');
$redirectUrl = $studentControlsUrl;
$locationHeader = 'Location: ';
$studentNotFoundMessage = 'Student not found.';
$studentQueryKey = '?student=';
$profileIncompleteMessage = 'Please complete all student profile fields.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['_token'] ?? null;
    if (!Csrf::verify(is_string($token) ? $token : null)) {
        Flash::set('error', 'Invalid CSRF token.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    $action = trim((string) ($_POST['action'] ?? ''));

    if ($action === 'update_student') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $tuitionFeeAmountRaw = trim((string) ($_POST['tuition_fee_amount'] ?? '0'));
        $examsFeeAmountRaw = trim((string) ($_POST['exams_fee_amount'] ?? '0'));
        $payload = [
            'username' => trim((string) ($_POST['username'] ?? '')),
            'student_id' => trim((string) ($_POST['student_id'] ?? '')),
            'first_name' => trim((string) ($_POST['first_name'] ?? '')),
            'last_name' => trim((string) ($_POST['last_name'] ?? '')),
            'program' => trim((string) ($_POST['program'] ?? '')),
            'faculty' => trim((string) ($_POST['faculty'] ?? '')),
            'level' => trim((string) ($_POST['level'] ?? '')),
            'admission_year' => trim((string) ($_POST['admission_year'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'status' => trim((string) ($_POST['status'] ?? '')),
            'tuition_fee_amount' => is_numeric($tuitionFeeAmountRaw) ? (float) $tuitionFeeAmountRaw : -1,
            'tuition_fee_paid' => trim((string) ($_POST['tuition_fee_paid'] ?? '0')),
            'exams_fee_amount' => is_numeric($examsFeeAmountRaw) ? (float) $examsFeeAmountRaw : -1,
            'exams_fee_paid' => trim((string) ($_POST['exams_fee_paid'] ?? '0')),
        ];

        if ($studentPk < 1 || !Validator::required($payload['username']) || !Validator::required($payload['student_id']) || !Validator::required($payload['first_name']) || !Validator::required($payload['last_name']) || !Validator::required($payload['program']) || !Validator::required($payload['faculty']) || !Validator::required($payload['level']) || !Validator::required($payload['admission_year']) || !Validator::required($payload['email']) || !Validator::required($payload['phone']) || !Validator::required($payload['status'])) {
            Flash::set('error', $profileIncompleteMessage);
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        if ($payload['tuition_fee_amount'] < 0 || $payload['exams_fee_amount'] < 0 || !Validator::in($payload['tuition_fee_paid'], ['0', '1']) || !Validator::in($payload['exams_fee_paid'], ['0', '1'])) {
            Flash::set('error', 'Please provide valid tuition and exams fee values.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        $payload['tuition_fee_paid'] = (int) $payload['tuition_fee_paid'];
        $payload['exams_fee_paid'] = (int) $payload['exams_fee_paid'];

        $student = $studentRepo->findById($studentPk);
        if (!$student) {
            Flash::set('error', $studentNotFoundMessage);
            header($locationHeader . $redirectUrl);
            exit;
        }

        if (!Validator::email($payload['email'])) {
            Flash::set('error', 'Please provide a valid email address.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        if ($studentRepo->usernameExists($payload['username'], $studentPk)) {
            Flash::set('error', 'That username already belongs to another student.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        if ($studentRepo->studentIdExists($payload['student_id'], $studentPk)) {
            Flash::set('error', 'That student ID already belongs to another student.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        $studentRepo->updateProfile($studentPk, $payload);
        ActivityLogger::log('student_profile_updated', [
            'subject_type' => 'student',
            'subject_id' => $studentPk,
            'details' => [
                'student_id' => $payload['student_id'],
                'level' => $payload['level'],
                'program' => $payload['program'],
            ],
        ]);

        Flash::set('success', 'Student profile updated successfully.');
        header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
        exit;
    }

    if ($action === 'save_manual_gpa') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $semester = (int) ($_POST['semester'] ?? 0);
        $manualGpa = trim((string) ($_POST['manual_gpa'] ?? ''));
        $gpaValue = is_numeric($manualGpa) ? (float) $manualGpa : -1;

        if ($studentPk < 1 || !Validator::required($academicYear) || $semester < 1 || $semester > 10 || $gpaValue < 0 || $gpaValue > 4.0) {
            Flash::set('error', 'Please provide valid GPA values. GPA must be between 0.00 and 4.00.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        $student = $studentRepo->findById($studentPk);
        if (!$student) {
            Flash::set('error', $studentNotFoundMessage);
            header($locationHeader . $redirectUrl);
            exit;
        }

        $termId = $resultRepo->findOrCreateTerm($academicYear, $semester);
        $resultRepo->upsertTermGpaOverride($studentPk, $termId, $gpaValue);
        ActivityLogger::log('manual_gpa_saved', [
            'subject_type' => 'student',
            'subject_id' => $studentPk,
            'details' => [
                'student_id' => $student['student_id'],
                'academic_year' => $academicYear,
                'semester' => $semester,
                'manual_gpa' => round($gpaValue, 2),
            ],
        ]);

        Flash::set('success', 'Manual GPA saved successfully.');
        header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
        exit;
    }

    if ($action === 'save_manual_cgpa') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $manualCgpa = trim((string) ($_POST['manual_cgpa'] ?? ''));
        $cgpaValue = is_numeric($manualCgpa) ? (float) $manualCgpa : -1;

        if ($studentPk < 1 || $cgpaValue < 0 || $cgpaValue > 4.0) {
            Flash::set('error', 'Please provide valid CGPA values. CGPA must be between 0.00 and 4.00.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        $student = $studentRepo->findById($studentPk);
        if (!$student) {
            Flash::set('error', $studentNotFoundMessage);
            header($locationHeader . $redirectUrl);
            exit;
        }

        $resultRepo->upsertCgpaOverride($studentPk, $cgpaValue);
        ActivityLogger::log('manual_cgpa_saved', [
            'subject_type' => 'student',
            'subject_id' => $studentPk,
            'details' => [
                'student_id' => $student['student_id'],
                'manual_cgpa' => round($cgpaValue, 2),
            ],
        ]);

        Flash::set('success', 'Manual CGPA saved successfully.');
        header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
        exit;
    }

    if ($action === 'save_result') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $semester = (int) ($_POST['semester'] ?? 0);
        $courseCode = trim((string) ($_POST['course_code'] ?? ''));
        $courseTitle = trim((string) ($_POST['course_title'] ?? ''));
        $creditHours = (int) ($_POST['credit_hours'] ?? 0);
        $grade = strtoupper(trim((string) ($_POST['grade'] ?? '')));
        $originalTermId = (int) ($_POST['original_term_id'] ?? 0);
        $originalCourseId = (int) ($_POST['original_course_id'] ?? 0);

        if ($studentPk < 1 || !Validator::required($academicYear) || $semester < 1 || $semester > 10 || !Validator::required($courseCode) || !Validator::required($courseTitle) || $creditHours < 1 || !Validator::in($grade, ['A', 'B+', 'B', 'C+', 'C', 'D+', 'D', 'F'])) {
            Flash::set('error', 'Please provide valid result values.');
            header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
            exit;
        }

        $student = $studentRepo->findById($studentPk);
        if (!$student) {
            Flash::set('error', $studentNotFoundMessage);
            header($locationHeader . $redirectUrl);
            exit;
        }

        $termId = $resultRepo->findOrCreateTerm($academicYear, $semester);
        $courseId = $resultRepo->findOrCreateCourse($courseCode, $courseTitle, $creditHours);

        if ($originalTermId > 0 && $originalCourseId > 0 && ($originalTermId !== $termId || $originalCourseId !== $courseId)) {
            $resultRepo->deleteResult($studentPk, $originalTermId, $originalCourseId);
        }

        $resultRepo->upsertResult($studentPk, $termId, $courseId, $grade);
        ActivityLogger::log('result_saved', [
            'subject_type' => 'student',
            'subject_id' => $studentPk,
            'details' => [
                'student_id' => $student['student_id'],
                'academic_year' => $academicYear,
                'semester' => $semester,
                'course_code' => $courseCode,
                'grade' => $grade,
            ],
        ]);

        Flash::set('success', 'Result saved successfully.');
        header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
        exit;
    }

    if ($action === 'save_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $courseCode = trim((string) ($_POST['course_code'] ?? ''));
        $courseTitle = trim((string) ($_POST['course_title'] ?? ''));
        $creditHours = (int) ($_POST['credit_hours'] ?? 0);

        if (!Validator::required($courseCode) || !Validator::required($courseTitle) || $creditHours < 1) {
            Flash::set('error', 'Please provide valid course details.');
            header($locationHeader . $redirectUrl);
            exit;
        }

        if ($resultRepo->courseCodeExists($courseCode, $courseId)) {
            Flash::set('error', 'That course code already belongs to another course.');
            header($locationHeader . $redirectUrl . ($courseId > 0 ? '?edit_course_id=' . $courseId . '#course-editor' : '#course-editor'));
            exit;
        }

        if ($courseId > 0) {
            $resultRepo->updateCourseById($courseId, $courseCode, $courseTitle, $creditHours);
        } else {
            $courseId = $resultRepo->findOrCreateCourse($courseCode, $courseTitle, $creditHours);
        }

        ActivityLogger::log('course_saved', [
            'subject_type' => 'course',
            'subject_id' => $courseId,
            'details' => [
                'course_code' => strtoupper($courseCode),
                'title' => $courseTitle,
                'credit_hours' => $creditHours,
            ],
        ]);

        Flash::set('success', $courseId > 0 ? 'Course saved successfully.' : 'Course saved successfully.');
        header($locationHeader . $redirectUrl . '#course-editor');
        exit;
    }

    if ($action === 'delete_course') {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId < 1) {
            Flash::set('error', 'Please select a course to delete.');
            header($locationHeader . $redirectUrl);
            exit;
        }

        $resultRepo->deleteCourseById($courseId);
        ActivityLogger::log('course_deleted', [
            'subject_type' => 'course',
            'subject_id' => $courseId,
        ]);

        Flash::set('success', 'Course deleted successfully.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    if ($action === 'set_current_term') {
        $termId = (int) ($_POST['term_id'] ?? 0);
        $selectedAcademicYear = trim((string) ($_POST['academic_year'] ?? ''));
        $selectedSemester = (int) ($_POST['semester'] ?? 0);

        if ($termId < 1) {
            // Accept short form like 23/24 and normalize to 2023/2024.
            if ((bool) preg_match('/^\d{2}[\/-]\d{2}$/', $selectedAcademicYear) === true) {
                $parts = preg_split('/[\/-]/', $selectedAcademicYear);
                if (is_array($parts) && count($parts) === 2) {
                    $left = (int) ($parts[0] ?? 0);
                    $right = (int) ($parts[1] ?? 0);
                    $selectedAcademicYear = sprintf('20%02d/20%02d', $left, $right);
                }
            }

            $normalizedAcademicYear = Validator::normalizeAcademicYear($selectedAcademicYear);
            if ($normalizedAcademicYear === null || !Validator::academicYearAllowed($normalizedAcademicYear, 2023, 2035) || $selectedSemester < 1 || $selectedSemester > 10) {
                Flash::set('error', 'Please select an academic year (23/24 and above) and semester (1-10).');
                header($locationHeader . $redirectUrl);
                exit;
            }

            $termId = $resultRepo->findOrCreateTerm($normalizedAcademicYear, $selectedSemester);
        }

        $resultRepo->setCurrentTerm($termId);
        ActivityLogger::log('current_term_updated', [
            'subject_type' => 'term',
            'subject_id' => $termId,
        ]);

        Flash::set('success', 'Current term updated successfully.');
        header($locationHeader . $redirectUrl);
        exit;
    }

    if ($action === 'delete_result') {
        $studentPk = (int) ($_POST['student_pk'] ?? 0);
        $termId = (int) ($_POST['term_id'] ?? 0);
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($studentPk < 1 || $termId < 1 || $courseId < 1) {
            Flash::set('error', 'Invalid result selection.');
            header($locationHeader . $redirectUrl);
            exit;
        }

        $resultRepo->deleteResult($studentPk, $termId, $courseId);
        ActivityLogger::log('result_deleted', [
            'subject_type' => 'student',
            'subject_id' => $studentPk,
            'details' => [
                'term_id' => $termId,
                'course_id' => $courseId,
            ],
        ]);

        Flash::set('success', 'Result deleted successfully.');
        header($locationHeader . $redirectUrl . $studentQueryKey . $studentPk);
        exit;
    }
}

$students = $studentRepo->allForAdmin();
$courses = $resultRepo->allCourses();
$courseUsageCounts = $resultRepo->courseUsageCounts();
$terms = $resultRepo->allTerms();
$yearOptions = Validator::academicYearOptions(2023, 2035);
$currentTerm = null;
foreach ($terms as $term) {
    if ((int) ($term['is_current'] ?? 0) === 1) {
        $currentTerm = $term;
        break;
    }
}
$selectedStudentId = (int) ($_GET['student'] ?? ($students[0]['id'] ?? 0));
$selectedStudent = $selectedStudentId > 0 ? $studentRepo->findById($selectedStudentId) : null;
$allResults = $resultRepo->allStudentResultsForAdmin();
$selectedStudentResults = array_values(array_filter(
    $allResults,
    static fn (array $row): bool => (int) ($row['student_id'] ?? 0) === $selectedStudentId
));
$editResult = [
    'term_id' => trim((string) ($_GET['edit_term_id'] ?? '')),
    'course_id' => trim((string) ($_GET['edit_course_id'] ?? '')),
    'academic_year' => trim((string) ($_GET['edit_academic_year'] ?? '')),
    'semester' => trim((string) ($_GET['edit_semester'] ?? '')),
    'course_code' => trim((string) ($_GET['edit_course_code'] ?? '')),
    'course_title' => trim((string) ($_GET['edit_course_title'] ?? '')),
    'credit_hours' => trim((string) ($_GET['edit_credit_hours'] ?? '')),
    'grade' => trim((string) ($_GET['edit_grade'] ?? '')),
];
$isEditingResult = $editResult['term_id'] !== '' && $editResult['course_id'] !== '';
$editCourseId = (int) ($_GET['edit_course_id'] ?? 0);
$editCourse = null;
foreach ($courses as $course) {
    if ((int) ($course['id'] ?? 0) === $editCourseId) {
        $editCourse = $course;
        break;
    }
}

$pageTitle = 'Student Controls';
$activePage = 'students';

ob_start();
?>
<section class="panel">
    <h3>Student Controls</h3>
    <p class="muted">Edit student profile data, manage courses, update grades and credits, control manual GPA/CGPA, and set the current academic term.</p>
</section>

<section class="panel">
    <form method="get" action="<?= View::e($studentControlsUrl) ?>">
        <label for="student">Select Student</label>
        <select id="student" name="student" onchange="this.form.submit()">
            <?php foreach ($students as $student): ?>
                <option value="<?= (int) $student['id'] ?>" <?= (int) $student['id'] === $selectedStudentId ? 'selected' : '' ?>><?= View::e((string) $student['student_id'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</section>

<?php if ($selectedStudent): ?>
<section class="panel">
    <h3>Edit Student Profile</h3>
    <form method="post" action="<?= View::e($studentControlsUrl) ?>">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="update_student">
        <input type="hidden" name="student_pk" value="<?= (int) $selectedStudent['id'] ?>">
        <div class="stack-2">
            <div><label for="username">Username</label><input id="username" name="username" type="text" value="<?= View::e((string) $selectedStudent['username']) ?>" required></div>
            <div><label for="student_id">Student ID</label><input id="student_id" name="student_id" type="text" value="<?= View::e((string) $selectedStudent['student_id']) ?>" required></div>
            <div><label for="first_name">First Name</label><input id="first_name" name="first_name" type="text" value="<?= View::e((string) $selectedStudent['first_name']) ?>" required></div>
            <div><label for="last_name">Last Name</label><input id="last_name" name="last_name" type="text" value="<?= View::e((string) $selectedStudent['last_name']) ?>" required></div>
            <div><label for="program">Program</label><input id="program" name="program" type="text" value="<?= View::e((string) $selectedStudent['program']) ?>" required></div>
            <div><label for="faculty">Faculty</label><input id="faculty" name="faculty" type="text" value="<?= View::e((string) $selectedStudent['faculty']) ?>" required></div>
            <div><label for="level">Level</label><input id="level" name="level" type="text" value="<?= View::e((string) $selectedStudent['level']) ?>" required></div>
            <div><label for="admission_year">Admission Year</label><input id="admission_year" name="admission_year" type="text" value="<?= View::e((string) $selectedStudent['admission_year']) ?>" required></div>
            <div><label for="email">Email</label><input id="email" name="email" type="text" value="<?= View::e((string) $selectedStudent['email']) ?>" required></div>
            <div><label for="phone">Phone</label><input id="phone" name="phone" type="text" value="<?= View::e((string) $selectedStudent['phone']) ?>" required></div>
            <div>
                <label for="status">Status</label>
                <select id="status" name="status" required>
                    <option value="Active" <?= (string) $selectedStudent['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                    <option value="Inactive" <?= (string) $selectedStudent['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                    <option value="Suspended" <?= (string) $selectedStudent['status'] === 'Suspended' ? 'selected' : '' ?>>Suspended</option>
                </select>
            </div>
            <div><label for="tuition_fee_amount">Tuition Fee Amount</label><input id="tuition_fee_amount" name="tuition_fee_amount" type="number" min="0" step="0.01" value="<?= View::e((string) ($selectedStudent['tuition_fee_amount'] ?? '0.00')) ?>" required></div>
            <div>
                <label for="tuition_fee_paid">Tuition Fee Status</label>
                <select id="tuition_fee_paid" name="tuition_fee_paid" required>
                    <option value="1" <?= (int) ($selectedStudent['tuition_fee_paid'] ?? 0) === 1 ? 'selected' : '' ?>>Paid (Green)</option>
                    <option value="0" <?= (int) ($selectedStudent['tuition_fee_paid'] ?? 0) === 0 ? 'selected' : '' ?>>Not Paid (Red)</option>
                </select>
            </div>
            <div><label for="exams_fee_amount">Exams Fee Amount</label><input id="exams_fee_amount" name="exams_fee_amount" type="number" min="0" step="0.01" value="<?= View::e((string) ($selectedStudent['exams_fee_amount'] ?? '0.00')) ?>" required></div>
            <div>
                <label for="exams_fee_paid">Exams Fee Status</label>
                <select id="exams_fee_paid" name="exams_fee_paid" required>
                    <option value="1" <?= (int) ($selectedStudent['exams_fee_paid'] ?? 0) === 1 ? 'selected' : '' ?>>Paid (Green)</option>
                    <option value="0" <?= (int) ($selectedStudent['exams_fee_paid'] ?? 0) === 0 ? 'selected' : '' ?>>Not Paid (Red)</option>
                </select>
            </div>
        </div>
        <button class="btn alt" type="submit">Save Student Profile</button>
    </form>
</section>

<section class="stack-2">
    <section class="panel">
        <h3>Set Manual GPA</h3>
        <p class="muted">Override the displayed GPA for a selected term.</p>
        <form method="post" action="<?= View::e($studentControlsUrl) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="save_manual_gpa">
            <input type="hidden" name="student_pk" value="<?= (int) $selectedStudent['id'] ?>">
            <label for="manual_gpa_year">Academic Year</label>
            <input id="manual_gpa_year" name="academic_year" type="text" placeholder="2025/2026" required>
            <label for="manual_gpa_semester">Semester</label>
            <select id="manual_gpa_semester" name="semester" required>
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
            <label for="manual_gpa">Manual GPA (0.00 - 4.00)</label>
            <input id="manual_gpa" name="manual_gpa" type="number" min="0" max="4" step="0.01" placeholder="3.45" required>
            <button class="btn alt" type="submit">Save Manual GPA</button>
        </form>
    </section>

    <section class="panel">
        <h3>Set Manual CGPA</h3>
        <p class="muted">Override the displayed cumulative GPA for this student.</p>
        <form method="post" action="<?= View::e($studentControlsUrl) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="save_manual_cgpa">
            <input type="hidden" name="student_pk" value="<?= (int) $selectedStudent['id'] ?>">
            <label for="manual_cgpa">Manual CGPA (0.00 - 4.00)</label>
            <input id="manual_cgpa" name="manual_cgpa" type="number" min="0" max="4" step="0.01" placeholder="3.52" required>
            <button class="btn alt" type="submit">Save Manual CGPA</button>
        </form>
    </section>
</section>

<section class="panel">
    <h3>Add or Update Result Row</h3>
    <p class="muted">Use the Edit link beside an existing result row to preload this form.</p>
    <?php if ($isEditingResult): ?>
        <div class="alert success">Editing <?= View::e($editResult['course_code']) ?> for <?= View::e($editResult['academic_year']) ?> semester <?= View::e($editResult['semester']) ?>. Update the values below and save.</div>
        <p class="muted"><a href="<?= View::e($studentControlsUrl) ?>?student=<?= (int) $selectedStudent['id'] ?>#result-editor">Cancel editing</a></p>
    <?php endif; ?>
    <form id="result-editor" method="post" action="<?= View::e($studentControlsUrl) ?>">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="save_result">
        <input type="hidden" name="student_pk" value="<?= (int) $selectedStudent['id'] ?>">
        <input type="hidden" name="original_term_id" value="<?= View::e($editResult['term_id']) ?>">
        <input type="hidden" name="original_course_id" value="<?= View::e($editResult['course_id']) ?>">
        <div class="stack-2">
            <div>
                <label for="result_year">Academic Year</label>
                <select id="result_year" name="academic_year" required>
                    <?php foreach ($yearOptions as $year): ?>
                        <?php
                        $yearParts = explode('/', $year);
                        $shortLabel = count($yearParts) === 2
                            ? substr((string) $yearParts[0], -2) . '/' . substr((string) $yearParts[1], -2)
                            : $year;
                        ?>
                        <option value="<?= View::e($year) ?>" <?= $editResult['academic_year'] === $year ? 'selected' : '' ?>><?= View::e($shortLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="result_semester">Semester</label>
                <select id="result_semester" name="semester" required>
                    <option value="1" <?= $editResult['semester'] === '1' ? 'selected' : '' ?>>1</option>
                    <option value="2" <?= $editResult['semester'] === '2' ? 'selected' : '' ?>>2</option>
                    <option value="3" <?= $editResult['semester'] === '3' ? 'selected' : '' ?>>3</option>
                    <option value="4" <?= $editResult['semester'] === '4' ? 'selected' : '' ?>>4</option>
                    <option value="5" <?= $editResult['semester'] === '5' ? 'selected' : '' ?>>5</option>
                    <option value="6" <?= $editResult['semester'] === '6' ? 'selected' : '' ?>>6</option>
                    <option value="7" <?= $editResult['semester'] === '7' ? 'selected' : '' ?>>7</option>
                    <option value="8" <?= $editResult['semester'] === '8' ? 'selected' : '' ?>>8</option>
                    <option value="9" <?= $editResult['semester'] === '9' ? 'selected' : '' ?>>9</option>
                    <option value="10" <?= $editResult['semester'] === '10' ? 'selected' : '' ?>>10</option>
                </select>
            </div>
            <div>
                <label for="result_course_code">Course Code</label>
                <input id="result_course_code" name="course_code" type="text" placeholder="MATH 151" value="<?= View::e($editResult['course_code']) ?>" required>
            </div>
            <div>
                <label for="result_course_title">Course Title</label>
                <input id="result_course_title" name="course_title" type="text" placeholder="Calculus I" value="<?= View::e($editResult['course_title']) ?>" required>
            </div>
            <div>
                <label for="result_credit_hours">Credit Hours</label>
                <input id="result_credit_hours" name="credit_hours" type="number" min="1" value="<?= View::e($editResult['credit_hours']) ?>" required>
            </div>
            <div>
                <label for="result_grade">Grade</label>
                <select id="result_grade" name="grade" required>
                    <option value="A" <?= $editResult['grade'] === 'A' ? 'selected' : '' ?>>A</option>
                    <option value="B+" <?= $editResult['grade'] === 'B+' ? 'selected' : '' ?>>B+</option>
                    <option value="B" <?= $editResult['grade'] === 'B' ? 'selected' : '' ?>>B</option>
                    <option value="C+" <?= $editResult['grade'] === 'C+' ? 'selected' : '' ?>>C+</option>
                    <option value="C" <?= $editResult['grade'] === 'C' ? 'selected' : '' ?>>C</option>
                    <option value="D+" <?= $editResult['grade'] === 'D+' ? 'selected' : '' ?>>D+</option>
                    <option value="D" <?= $editResult['grade'] === 'D' ? 'selected' : '' ?>>D</option>
                    <option value="F" <?= $editResult['grade'] === 'F' ? 'selected' : '' ?>>F</option>
                </select>
            </div>
        </div>
        <button class="btn alt" type="submit"><?= $isEditingResult ? 'Update Result Row' : 'Save Result Row' ?></button>
    </form>
</section>

<section class="panel table-wrap">
    <h3>Selected Student Result Rows</h3>
    <?php if ($selectedStudentResults === []): ?>
        <p class="muted">No result rows found for this student.</p>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Academic Year</th>
                <th>Semester</th>
                <th>Course Code</th>
                <th>Course Title</th>
                <th>Credit Hours</th>
                <th>Grade</th>
                <th>Edit</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($selectedStudentResults as $row): ?>
                <tr>
                    <td><?= View::e((string) $row['academic_year']) ?></td>
                    <td><?= View::e((string) $row['semester']) ?></td>
                    <td><?= View::e((string) $row['course_code']) ?></td>
                    <td><?= View::e((string) $row['course_title']) ?></td>
                    <td><?= View::e((string) $row['credit_hours']) ?></td>
                    <td><?= View::e((string) $row['grade']) ?></td>
                    <td>
                        <a href="<?= View::e($studentControlsUrl) ?>?student=<?= (int) $selectedStudent['id'] ?>&edit_term_id=<?= (int) $row['term_id'] ?>&edit_course_id=<?= (int) $row['course_id'] ?>&edit_academic_year=<?= urlencode((string) $row['academic_year']) ?>&edit_semester=<?= urlencode((string) $row['semester']) ?>&edit_course_code=<?= urlencode((string) $row['course_code']) ?>&edit_course_title=<?= urlencode((string) $row['course_title']) ?>&edit_credit_hours=<?= urlencode((string) $row['credit_hours']) ?>&edit_grade=<?= urlencode((string) $row['grade']) ?>#result-editor">Edit</a>
                    </td>
                    <td>
                        <form method="post" action="<?= View::e($studentControlsUrl) ?>" onsubmit="return confirm('Delete this result row?');">
                            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
                            <input type="hidden" name="action" value="delete_result">
                            <input type="hidden" name="student_pk" value="<?= (int) $selectedStudent['id'] ?>">
                            <input type="hidden" name="term_id" value="<?= (int) $row['term_id'] ?>">
                            <input type="hidden" name="course_id" value="<?= (int) $row['course_id'] ?>">
                            <button type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="stack-2">
    <section class="panel">
        <h3>Add or Update Course</h3>
        <?php if ($editCourse): ?>
            <div class="alert success">Editing <?= View::e((string) $editCourse['course_code']) ?>. Update the course details below.</div>
            <p class="muted"><a href="<?= View::e($studentControlsUrl) ?>#course-editor">Cancel editing</a></p>
        <?php endif; ?>
        <form id="course-editor" method="post" action="<?= View::e($studentControlsUrl) ?>">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="save_course">
            <input type="hidden" name="course_id" value="<?= (int) ($editCourse['id'] ?? 0) ?>">
            <label for="course_code">Course Code</label>
            <input id="course_code" name="course_code" type="text" placeholder="CS 301" value="<?= View::e((string) ($editCourse['course_code'] ?? '')) ?>" required>
            <label for="course_title">Course Title</label>
            <input id="course_title" name="course_title" type="text" placeholder="Database Systems" value="<?= View::e((string) ($editCourse['title'] ?? '')) ?>" required>
            <label for="credit_hours">Credit Hours</label>
            <input id="credit_hours" name="credit_hours" type="number" min="1" value="<?= View::e((string) ($editCourse['credit_hours'] ?? '')) ?>" required>
            <button class="btn alt" type="submit"><?= $editCourse ? 'Update Course' : 'Save Course' ?></button>
        </form>

        <?php if ($courses !== []): ?>
            <div class="table-wrap" style="margin-top:1rem;">
                <table>
                    <thead>
                    <tr>
                        <th>Course Code</th>
                        <th>Course Title</th>
                        <th>Credit Hours</th>
                        <th>Edit</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($courses as $course): ?>
                        <tr>
                            <td><?= View::e((string) $course['course_code']) ?></td>
                            <td><?= View::e((string) $course['title']) ?></td>
                            <td><?= View::e((string) $course['credit_hours']) ?></td>
                            <td><a href="<?= View::e($studentControlsUrl) ?>?<?= $selectedStudent ? 'student=' . (int) $selectedStudent['id'] . '&' : '' ?>edit_course_id=<?= (int) $course['id'] ?>#course-editor">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="panel">
        <h3>Delete Course</h3>
        <p class="muted small">Deleting a course also removes every linked result row for that course.</p>
        <form method="post" action="<?= View::e($studentControlsUrl) ?>" onsubmit="return confirm('Delete this course and all linked results?');">
            <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="delete_course">
            <label for="course_id">Course</label>
            <select id="course_id" name="course_id" required>
                <option value="">Select course</option>
                <?php foreach ($courses as $course): ?>
                    <option value="<?= (int) $course['id'] ?>"><?= View::e((string) $course['course_code'] . ' - ' . $course['title'] . ' (' . $course['credit_hours'] . ' CH, ' . ($courseUsageCounts[(int) $course['id']] ?? 0) . ' result rows)') ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn alt" type="submit">Delete Course</button>
        </form>
    </section>
</section>

<section class="panel">
    <h3>Set Current Semester</h3>
    <form method="post" action="<?= View::e($studentControlsUrl) ?>">
        <input type="hidden" name="_token" value="<?= View::e(Csrf::token()) ?>">
        <input type="hidden" name="action" value="set_current_term">
        <div class="stack-2">
            <div>
                <label for="current_academic_year">Academic Year</label>
                <select id="current_academic_year" name="academic_year" required>
                    <?php foreach ($yearOptions as $year): ?>
                        <?php
                        $yearParts = explode('/', $year);
                        $shortLabel = count($yearParts) === 2
                            ? substr((string) $yearParts[0], -2) . '/' . substr((string) $yearParts[1], -2)
                            : $year;
                        ?>
                        <option value="<?= View::e($year) ?>" <?= (string) ($currentTerm['academic_year'] ?? '') === $year ? 'selected' : '' ?>><?= View::e($shortLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="current_semester">Semester</label>
                <select id="current_semester" name="semester" required>
                    <?php for ($sem = 1; $sem <= 10; $sem++): ?>
                        <option value="<?= $sem ?>" <?= (int) ($currentTerm['semester'] ?? 0) === $sem ? 'selected' : '' ?>><?= $sem ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <p class="muted small">Select academic year from 23/24 upward and semester from 1 to 10.</p>
        <label for="term_id">Or pick existing term directly</label>
        <select id="term_id" name="term_id">
            <option value="">Use year + semester above</option>
            <?php foreach ($terms as $term): ?>
                <option value="<?= (int) $term['id'] ?>"><?= View::e((string) $term['academic_year'] . ' / Semester ' . $term['semester']) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn alt" type="submit">Save Current Term</button>
    </form>
</section>
<?php
$content = (string) ob_get_clean();
require_once dirname(__DIR__) . '/templates/layout-admin.php';
