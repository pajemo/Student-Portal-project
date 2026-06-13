<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Config\Database;
use App\Core\Auth;
use App\Repositories\ResultRepository;
use App\Repositories\StudentRepository;
use App\Services\ActivityLogger;
use App\Services\ResultService;
use App\Services\TranscriptPdf;

Auth::requireRole('student');
$user = Auth::user();
$studentId = (int) ($user['id'] ?? 0);

$resultService = new ResultService(new ResultRepository(Database::connection()), new StudentRepository(Database::connection()));
$bundle = $resultService->getTranscriptBundle($studentId);

$student = $bundle['student'] ?? [];
$history = $bundle['history'] ?? [];
$lines = [];
$lines[] = 'Student Name: ' . trim((string) ($student['first_name'] ?? '') . ' ' . (string) ($student['last_name'] ?? ''));
$lines[] = 'Student ID: ' . (string) ($student['student_id'] ?? '');
$lines[] = 'Program: ' . (string) ($student['program'] ?? '');
$lines[] = 'Faculty: ' . (string) ($student['faculty'] ?? '');
$lines[] = 'Policy: ' . (string) ($bundle['policy_name'] ?? '');
$lines[] = 'CGPA: ' . ($bundle['cgpa'] !== null ? number_format((float) $bundle['cgpa'], 2) : 'N/A');
$lines[] = '';

foreach ($history as $item) {
    $lines[] = (string) $item['term']['academic_year'] . ' - Semester ' . (string) $item['term']['semester'];
    $lines[] = 'Term GPA: ' . ($item['gpa'] !== null ? number_format((float) $item['gpa'], 2) : 'N/A');
    foreach ($item['rows'] as $row) {
        $lines[] = '  ' . $row['course_code'] . ' | ' . $row['title'] . ' | ' . $row['credit_hours'] . ' | ' . $row['grade'];
    }
    $lines[] = '';
}

ActivityLogger::log('transcript_exported', [
    'actor_role' => 'student',
    'actor_id' => $studentId,
    'subject_type' => 'student',
    'subject_id' => $studentId,
    'details' => ['format' => 'pdf'],
]);

$pdf = (new TranscriptPdf())->build([
    [
        'title' => 'Academic Transcript',
        'lines' => $lines,
    ],
]);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="transcript-' . ($student['student_id'] ?? 'student') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
