<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ResultRepository;
use App\Repositories\StudentRepository;
use PDO;
use Throwable;

final class ImportService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly StudentRepository $students,
        private readonly ResultRepository $results
    ) {
    }

    public function importCsv(string $path, int $adminId, string $fileName): array
    {
        $report = ['success' => 0, 'failed' => 0, 'errors' => []];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $report['failed'] = 1;
            $report['errors'][] = 'Unable to read uploaded file.';
            return $report;
        }

        try {
            $header = fgetcsv($handle);
            if (!$this->isExpectedHeader($header)) {
                $report['failed'] = 1;
                $report['errors'][] = $header === false
                    ? 'CSV file is empty.'
                    : 'CSV header mismatch. Use: student_id, academic_year, semester, course_code, course_title, credit_hours, grade';

                return $report;
            }

            $this->pdo->beginTransaction();
            $this->processRows($handle, $report);
            $this->storeImportLog($adminId, $fileName, $report);
            ActivityLogger::log('csv_import_completed', [
                'actor_role' => 'admin',
                'subject_type' => 'import',
                'details' => [
                    'file_name' => $fileName,
                    'success' => $report['success'],
                    'failed' => $report['failed'],
                ],
            ]);
            $this->pdo->commit();
        } catch (Throwable $throwable) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            $report['success'] = 0;
            $report['failed'] = max(1, $report['failed']);
            $report['errors'][] = 'Import failed: ' . $throwable->getMessage();

            ActivityLogger::log('csv_import_failed', [
                'actor_role' => 'admin',
                'subject_type' => 'import',
                'details' => ['file_name' => $fileName, 'error' => $throwable->getMessage()],
            ]);
        } finally {
            fclose($handle);
        }

        return $report;
    }

    private function isExpectedHeader(array|false $header): bool
    {
        if ($header === false) {
            return false;
        }

        $expected = ['student_id', 'academic_year', 'semester', 'course_code', 'course_title', 'credit_hours', 'grade'];
        $normalized = array_map(static fn ($cell) => strtolower(trim((string) $cell)), $header);

        return $normalized === $expected;
    }

    private function processRows($handle, array &$report): void
    {
        $rowIndex = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            $this->processRow($row, $rowIndex, $report);
        }
    }

    private function processRow(array $row, int $rowIndex, array &$report): void
    {
        if (count($row) < 7) {
            $report['failed']++;
            $report['errors'][] = 'Row ' . $rowIndex . ': incomplete row.';
            return;
        }

        [$studentId, $academicYear, $semester, $courseCode, $courseTitle, $creditHours, $grade] = array_map('trim', $row);
        $semesterInt = (int) $semester;
        $creditInt = (int) $creditHours;
        $grade = strtoupper($grade);

        if ($studentId === '' || $academicYear === '' || $semesterInt < 1 || $semesterInt > 3 || $courseCode === '' || $courseTitle === '' || $creditInt <= 0) {
            $report['failed']++;
            $report['errors'][] = 'Row ' . $rowIndex . ': invalid data.';
            return;
        }

        $student = $this->students->findByStudentId($studentId);
        if (!$student) {
            $report['failed']++;
            $report['errors'][] = 'Row ' . $rowIndex . ': student not found (' . $studentId . ').';
            return;
        }

        $termId = $this->results->findOrCreateTerm($academicYear, $semesterInt);
        $courseId = $this->results->findOrCreateCourse($courseCode, $courseTitle, $creditInt);
        $this->results->upsertResult((int) $student['id'], $termId, $courseId, $grade);
        $report['success']++;
    }

    private function storeImportLog(int $adminId, string $fileName, array $report): void
    {
        $this->pdo->prepare('INSERT INTO import_logs (admin_id, file_name, total_rows, success_rows, failed_rows, message) VALUES (:admin_id, :file_name, :total_rows, :success_rows, :failed_rows, :message)')
            ->execute([
                'admin_id' => $adminId,
                'file_name' => $fileName,
                'total_rows' => $report['success'] + $report['failed'],
                'success_rows' => $report['success'],
                'failed_rows' => $report['failed'],
                'message' => $report['errors'] === [] ? 'Import completed.' : implode(' | ', array_slice($report['errors'], 0, 5)),
            ]);
    }
}

