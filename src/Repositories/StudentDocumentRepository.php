<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentDocumentRepository
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $tableExistsStmt = $this->pdo->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'student_documents' LIMIT 1");
        if (!$tableExistsStmt || !$tableExistsStmt->fetchColumn()) {
            return;
        }

        $columnsStmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'student_documents'");
        $existingColumns = $columnsStmt ? array_map('strval', $columnsStmt->fetchAll(PDO::FETCH_COLUMN)) : [];

        if (!in_array('student_id', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE student_documents ADD COLUMN student_id INT NULL AFTER admin_id');
        }

        if (!in_array('academic_year', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE student_documents ADD COLUMN academic_year VARCHAR(20) NULL AFTER student_id');
        }

        if (!in_array('semester', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE student_documents ADD COLUMN semester TINYINT NULL AFTER academic_year');
        }

        if (!in_array('student_id', $existingColumns, true)) {
            $this->pdo->exec('CREATE INDEX idx_student_documents_student ON student_documents (student_id)');
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $hasSemesterColumns = $this->hasSemesterColumns();
        if (!$hasSemesterColumns) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO student_documents (admin_id, title, original_name, stored_name, file_size, mime_type) VALUES (:admin_id, :title, :original_name, :stored_name, :file_size, :mime_type)'
            );
            $stmt->execute([
                'admin_id' => $data['admin_id'],
                'title' => $data['title'],
                'original_name' => $data['original_name'],
                'stored_name' => $data['stored_name'],
                'file_size' => $data['file_size'],
                'mime_type' => $data['mime_type'],
            ]);

            return (int) $this->pdo->lastInsertId();
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO student_documents (admin_id, student_id, academic_year, semester, title, original_name, stored_name, file_size, mime_type) VALUES (:admin_id, :student_id, :academic_year, :semester, :title, :original_name, :stored_name, :file_size, :mime_type) ON DUPLICATE KEY UPDATE admin_id = VALUES(admin_id), title = VALUES(title), original_name = VALUES(original_name), stored_name = VALUES(stored_name), file_size = VALUES(file_size), mime_type = VALUES(mime_type)'
        );
        $stmt->execute([
            'admin_id' => $data['admin_id'],
            'student_id' => $data['student_id'],
            'academic_year' => $data['academic_year'],
            'semester' => $data['semester'],
            'title' => $data['title'],
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'file_size' => $data['file_size'],
            'mime_type' => $data['mime_type'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        if (!$this->hasSemesterColumns()) {
            $stmt = $this->pdo->prepare(
                'SELECT d.*, NULL AS student_code, NULL AS first_name, NULL AS last_name FROM student_documents d ORDER BY d.created_at DESC, d.id DESC LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT d.*, s.student_id AS student_code, s.first_name, s.last_name FROM student_documents d LEFT JOIN students s ON s.id = d.student_id ORDER BY d.created_at DESC, d.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM student_documents WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId): array
    {
        if (!$this->hasSemesterColumns()) {
            return [];
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM student_documents WHERE student_id = :student_id ORDER BY academic_year DESC, semester DESC, created_at DESC, id DESC'
        );
        $stmt->execute(['student_id' => $studentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForStudent(int $documentId, int $studentId): ?array
    {
        if (!$this->hasSemesterColumns()) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM student_documents WHERE id = :id AND student_id = :student_id LIMIT 1');
        $stmt->execute([
            'id' => $documentId,
            'student_id' => $studentId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    private function hasSemesterColumns(): bool
    {
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'student_documents' AND column_name IN ('student_id', 'academic_year', 'semester')");
        $columns = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];

        return count($columns) === 3;
    }
}
