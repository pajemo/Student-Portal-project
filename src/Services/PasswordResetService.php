<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Repositories\PasswordResetRepository;
use App\Repositories\StudentRepository;
use DateInterval;
use DateTimeImmutable;

final class PasswordResetService
{
    public function __construct(
        private readonly StudentRepository $students,
        private readonly PasswordResetRepository $tokens
    ) {
    }

    public function requestStudentReset(string $studentId, string $email): array
    {
        $student = $this->students->findByStudentIdAndEmail($studentId, $email);
        if (!$student) {
            ActivityLogger::log('password_reset_requested_failed', [
                'actor_role' => 'student',
                'details' => ['student_id' => $studentId],
            ]);

            return ['ok' => false, 'message' => 'We could not verify that account.'];
        }

        $expiresAt = (new DateTimeImmutable('now'))->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');
        $token = $this->tokens->create([
            'user_type' => 'student',
            'user_id' => (int) $student['id'],
            'identifier' => (string) $student['student_id'],
            'expires_at' => $expiresAt,
        ]);

        ActivityLogger::log('password_reset_requested', [
            'actor_role' => 'student',
            'subject_type' => 'student',
            'subject_id' => (int) $student['id'],
            'details' => ['student_id' => $student['student_id']],
        ]);

        return [
            'ok' => true,
            'token' => $token,
            'expires_at' => $expiresAt,
            'student' => $student,
        ];
    }

    public function completeStudentReset(string $token, string $password): array
    {
        $record = $this->tokens->findValidByToken($token);
        if (!$record) {
            return ['ok' => false, 'message' => 'Invalid or expired reset token.'];
        }

        $student = $this->students->findById((int) $record['user_id']);
        if (!$student) {
            return ['ok' => false, 'message' => 'Student record not found.'];
        }

        $this->students->updatePassword((int) $student['id'], $password);
        $this->tokens->markUsed((int) $record['id']);

        ActivityLogger::log('password_reset_completed', [
            'actor_role' => 'student',
            'subject_type' => 'student',
            'subject_id' => (int) $student['id'],
            'details' => ['student_id' => $student['student_id']],
        ]);

        return ['ok' => true];
    }

    public function adminResetStudentPassword(int $studentId, string $password): bool
    {
        $student = $this->students->findById($studentId);
        if (!$student) {
            return false;
        }

        $this->students->updatePassword($studentId, $password);

        ActivityLogger::log('admin_password_reset', [
            'actor_role' => 'admin',
            'subject_type' => 'student',
            'subject_id' => $studentId,
            'details' => ['student_id' => $student['student_id']],
        ]);

        return true;
    }
}
