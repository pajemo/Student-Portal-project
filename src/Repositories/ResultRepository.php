<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ResultRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureManualGpaTable();
        $this->ensureManualCgpaTable();
    }

    private function ensureManualGpaTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS term_gpa_overrides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                term_id INT NOT NULL,
                gpa DECIMAL(4,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_term_gpa_override (student_id, term_id),
                CONSTRAINT fk_term_gpa_override_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
                CONSTRAINT fk_term_gpa_override_term FOREIGN KEY (term_id) REFERENCES academic_terms(id) ON DELETE CASCADE
            )'
        );
    }

    private function ensureManualCgpaTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS student_cgpa_overrides (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL UNIQUE,
                cgpa DECIMAL(4,2) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_student_cgpa_override_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
            )'
        );
    }

    public function findTermsForStudent(int $studentId): array
    {
        $sql = 'SELECT DISTINCT t.id, t.academic_year, t.semester, t.is_current
                FROM results r
                INNER JOIN academic_terms t ON t.id = r.term_id
                WHERE r.student_id = :student_id
                ORDER BY t.academic_year DESC, t.semester DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchAll();
    }

    public function findCurrentTermForStudent(int $studentId): ?array
    {
        $sql = 'SELECT t.id, t.academic_year, t.semester, t.is_current
                FROM results r
                INNER JOIN academic_terms t ON t.id = r.term_id
                WHERE r.student_id = :student_id AND t.is_current = 1
                GROUP BY t.id, t.academic_year, t.semester, t.is_current
                ORDER BY t.academic_year DESC, t.semester DESC
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['student_id' => $studentId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findResultsByTerm(int $studentId, int $termId): array
    {
        $sql = 'SELECT c.course_code, c.title, c.credit_hours, r.grade, t.academic_year, t.semester
                FROM results r
                INNER JOIN courses c ON c.id = r.course_id
                INNER JOIN academic_terms t ON t.id = r.term_id
                WHERE r.student_id = :student_id AND r.term_id = :term_id
                ORDER BY c.course_code ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'student_id' => $studentId,
            'term_id' => $termId,
        ]);

        return $stmt->fetchAll();
    }

    public function upsertResult(int $studentPk, int $termId, int $courseId, string $grade): void
    {
        $sql = 'INSERT INTO results (student_id, term_id, course_id, grade)
                VALUES (:student_id, :term_id, :course_id, :grade)
                ON DUPLICATE KEY UPDATE grade = VALUES(grade)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'student_id' => $studentPk,
            'term_id' => $termId,
            'course_id' => $courseId,
            'grade' => strtoupper(trim($grade)),
        ]);
    }

    public function findOrCreateTerm(string $academicYear, int $semester): int
    {
        $select = $this->pdo->prepare('SELECT id FROM academic_terms WHERE academic_year = :academic_year AND semester = :semester LIMIT 1');
        $select->execute([
            'academic_year' => $academicYear,
            'semester' => $semester,
        ]);
        $row = $select->fetch();

        if ($row) {
            return (int) $row['id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO academic_terms (academic_year, semester, is_current) VALUES (:academic_year, :semester, 0)');
        $insert->execute([
            'academic_year' => $academicYear,
            'semester' => $semester,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findOrCreateCourse(string $courseCode, string $title, int $creditHours): int
    {
        $code = strtoupper(trim($courseCode));
        $select = $this->pdo->prepare('SELECT id FROM courses WHERE course_code = :course_code LIMIT 1');
        $select->execute(['course_code' => $code]);
        $row = $select->fetch();

        if ($row) {
            $update = $this->pdo->prepare('UPDATE courses SET title = :title, credit_hours = :credit_hours WHERE id = :id');
            $update->execute([
                'title' => trim($title),
                'credit_hours' => $creditHours,
                'id' => (int) $row['id'],
            ]);

            return (int) $row['id'];
        }

        $insert = $this->pdo->prepare('INSERT INTO courses (course_code, title, credit_hours) VALUES (:course_code, :title, :credit_hours)');
        $insert->execute([
            'course_code' => $code,
            'title' => trim($title),
            'credit_hours' => $creditHours,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function upsertTermGpaOverride(int $studentPk, int $termId, float $gpa): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO term_gpa_overrides (student_id, term_id, gpa)
             VALUES (:student_id, :term_id, :gpa)
             ON DUPLICATE KEY UPDATE gpa = VALUES(gpa)'
        );
        $stmt->execute([
            'student_id' => $studentPk,
            'term_id' => $termId,
            'gpa' => round($gpa, 2),
        ]);
    }

    public function findTermGpaOverride(int $studentPk, int $termId): ?float
    {
        $stmt = $this->pdo->prepare('SELECT gpa FROM term_gpa_overrides WHERE student_id = :student_id AND term_id = :term_id LIMIT 1');
        $stmt->execute([
            'student_id' => $studentPk,
            'term_id' => $termId,
        ]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return (float) $row['gpa'];
    }

    public function upsertCgpaOverride(int $studentPk, float $cgpa): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO student_cgpa_overrides (student_id, cgpa)
             VALUES (:student_id, :cgpa)
             ON DUPLICATE KEY UPDATE cgpa = VALUES(cgpa)'
        );
        $stmt->execute([
            'student_id' => $studentPk,
            'cgpa' => round($cgpa, 2),
        ]);
    }

    public function findCgpaOverride(int $studentPk): ?float
    {
        $stmt = $this->pdo->prepare('SELECT cgpa FROM student_cgpa_overrides WHERE student_id = :student_id LIMIT 1');
        $stmt->execute(['student_id' => $studentPk]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return (float) $row['cgpa'];
    }

    public function allCourses(): array
    {
        $stmt = $this->pdo->query('SELECT id, course_code, title, credit_hours FROM courses ORDER BY course_code ASC');
        return $stmt->fetchAll();
    }

    public function courseCodeExists(string $courseCode, int $excludeCourseId = 0): bool
    {
        $sql = 'SELECT id FROM courses WHERE course_code = :course_code';
        $params = ['course_code' => strtoupper(trim($courseCode))];

        if ($excludeCourseId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeCourseId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function updateCourseById(int $courseId, string $courseCode, string $title, int $creditHours): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE courses
             SET course_code = :course_code, title = :title, credit_hours = :credit_hours
             WHERE id = :id'
        );
        $stmt->execute([
            'course_code' => strtoupper(trim($courseCode)),
            'title' => trim($title),
            'credit_hours' => $creditHours,
            'id' => $courseId,
        ]);
    }

    public function deleteCourseById(int $courseId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM courses WHERE id = :id');
        $stmt->execute(['id' => $courseId]);
    }

    public function allTerms(): array
    {
        $stmt = $this->pdo->query('SELECT id, academic_year, semester, is_current FROM academic_terms ORDER BY academic_year DESC, semester DESC');
        return $stmt->fetchAll();
    }

    public function setCurrentTerm(int $termId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('UPDATE academic_terms SET is_current = 0');
            $stmt = $this->pdo->prepare('UPDATE academic_terms SET is_current = 1 WHERE id = :id');
            $stmt->execute(['id' => $termId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function deleteResult(int $studentPk, int $termId, int $courseId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM results WHERE student_id = :student_id AND term_id = :term_id AND course_id = :course_id');
        $stmt->execute([
            'student_id' => $studentPk,
            'term_id' => $termId,
            'course_id' => $courseId,
        ]);
    }

    public function allStudentResultsForAdmin(): array
    {
        $sql = 'SELECT r.student_id, r.term_id, r.course_id, r.grade,
                       s.student_id AS student_code, s.first_name, s.last_name,
                       t.academic_year, t.semester,
                       c.course_code, c.title AS course_title, c.credit_hours
                FROM results r
                INNER JOIN students s ON s.id = r.student_id
                INNER JOIN academic_terms t ON t.id = r.term_id
                INNER JOIN courses c ON c.id = r.course_id
                ORDER BY s.student_id ASC, t.academic_year DESC, t.semester DESC, c.course_code ASC';
        $stmt = $this->pdo->query($sql);

        return $stmt->fetchAll();
    }

    /**
     * @return array<int, int>
     */
    public function courseUsageCounts(): array
    {
        $stmt = $this->pdo->query('SELECT course_id, COUNT(*) AS total FROM results GROUP BY course_id');
        $rows = $stmt->fetchAll();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['course_id']] = (int) $row['total'];
        }

        return $counts;
    }
}
