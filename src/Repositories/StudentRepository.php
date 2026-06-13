<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class StudentRepository
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->ensureFeeColumns();
    }

    private function ensureFeeColumns(): void
    {
        $columnsStmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'students'");
        $existingColumns = $columnsStmt ? array_map('strval', $columnsStmt->fetchAll(PDO::FETCH_COLUMN)) : [];

        if (!in_array('tuition_fee_amount', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE students ADD COLUMN tuition_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER status');
        }

        if (!in_array('tuition_fee_paid', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE students ADD COLUMN tuition_fee_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER tuition_fee_amount');
        }

        if (!in_array('exams_fee_amount', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE students ADD COLUMN exams_fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER tuition_fee_paid');
        }

        if (!in_array('exams_fee_paid', $existingColumns, true)) {
            $this->pdo->exec('ALTER TABLE students ADD COLUMN exams_fee_paid TINYINT(1) NOT NULL DEFAULT 0 AFTER exams_fee_amount');
        }
    }

    public function findByStudentId(string $studentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM students WHERE student_id = :student_id LIMIT 1');
        $stmt->execute(['student_id' => trim($studentId)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByUsernameAndStudentId(string $username, string $studentId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM students WHERE username = :username AND student_id = :student_id LIMIT 1');
        $stmt->execute([
            'username' => trim($username),
            'student_id' => trim($studentId),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM students WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findByStudentIdAndEmail(string $studentId, string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM students WHERE student_id = :student_id AND email = :email LIMIT 1');
        $stmt->execute([
            'student_id' => trim($studentId),
            'email' => trim($email),
        ]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function updatePassword(int $id, string $password): void
    {
        $stmt = $this->pdo->prepare('UPDATE students SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]);
    }

    public function allForAdmin(): array
    {
        $stmt = $this->pdo->query('SELECT id, username, student_id, first_name, last_name, program, faculty, level, admission_year, email, phone, status, tuition_fee_amount, tuition_fee_paid, exams_fee_amount, exams_fee_paid FROM students ORDER BY student_id ASC');
        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE students
             SET username = :username,
                 student_id = :student_id,
                 first_name = :first_name,
                 last_name = :last_name,
                 program = :program,
                 faculty = :faculty,
                 level = :level,
                 admission_year = :admission_year,
                 email = :email,
                 phone = :phone,
                 status = :status,
                 tuition_fee_amount = :tuition_fee_amount,
                 tuition_fee_paid = :tuition_fee_paid,
                 exams_fee_amount = :exams_fee_amount,
                 exams_fee_paid = :exams_fee_paid
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'username' => trim((string) $data['username']),
            'student_id' => trim((string) $data['student_id']),
            'first_name' => trim((string) $data['first_name']),
            'last_name' => trim((string) $data['last_name']),
            'program' => trim((string) $data['program']),
            'faculty' => trim((string) $data['faculty']),
            'level' => trim((string) $data['level']),
            'admission_year' => trim((string) $data['admission_year']),
            'email' => trim((string) $data['email']),
            'phone' => trim((string) $data['phone']),
            'status' => trim((string) $data['status']),
            'tuition_fee_amount' => round((float) ($data['tuition_fee_amount'] ?? 0), 2),
            'tuition_fee_paid' => (int) ($data['tuition_fee_paid'] ?? 0),
            'exams_fee_amount' => round((float) ($data['exams_fee_amount'] ?? 0), 2),
            'exams_fee_paid' => (int) ($data['exams_fee_paid'] ?? 0),
        ]);
    }

    public function usernameExists(string $username, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM students WHERE username = :username';
        $params = ['username' => trim($username)];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function studentIdExists(string $studentId, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM students WHERE student_id = :student_id';
        $params = ['student_id' => trim($studentId)];
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }
}
