<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use App\Repositories\AdminRepository;
use App\Repositories\StudentRepository;
use App\Services\ActivityLogger;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function attemptStudent(string $username, string $studentId, string $password): bool
    {
        $identifier = strtolower(trim($username) . '|' . trim($studentId));
        $authenticated = false;

        if ($identifier !== '') {
            $lock = self::canAttempt($identifier, 'student');
            if (!$lock['allowed']) {
                Flash::set('error', $lock['message']);
            } else {
                $repo = new StudentRepository(Database::connection());
                $student = $repo->findByUsernameAndStudentId($username, $studentId);

                if ($student && password_verify($password, (string) $student['password_hash'])) {
                    self::clearFailures($identifier, 'student');
                    $_SESSION['auth'] = [
                        'role' => 'student',
                        'id' => (int) $student['id'],
                        'username' => (string) $student['username'],
                        'student_id' => (string) $student['student_id'],
                        'name' => trim((string) $student['first_name'] . ' ' . (string) $student['last_name']),
                    ];
                    ActivityLogger::log('login_success', [
                        'actor_role' => 'student',
                        'actor_id' => (int) $student['id'],
                        'subject_type' => 'student',
                        'subject_id' => (int) $student['id'],
                        'details' => ['username' => $student['username'], 'student_id' => $student['student_id']],
                    ]);
                    $authenticated = true;
                } else {
                    self::recordFailure($identifier, 'student');
                }
            }
        }

        return $authenticated;
    }

    public static function attemptAdmin(string $username, string $password): bool
    {
        $identifier = strtolower(trim($username));
        $authenticated = false;

        if ($identifier !== '') {
            $lock = self::canAttempt($identifier, 'admin');
            if (!$lock['allowed']) {
                Flash::set('error', $lock['message']);
            } else {
                $repo = new AdminRepository(Database::connection());
                $admin = $repo->findByUsername($username);

                if ($admin && password_verify($password, (string) $admin['password_hash'])) {
                    self::clearFailures($identifier, 'admin');
                    $_SESSION['auth'] = [
                        'role' => 'admin',
                        'id' => (int) $admin['id'],
                        'username' => (string) $admin['username'],
                        'name' => (string) $admin['full_name'],
                    ];
                    ActivityLogger::log('login_success', [
                        'actor_role' => 'admin',
                        'actor_id' => (int) $admin['id'],
                        'subject_type' => 'admin',
                        'subject_id' => (int) $admin['id'],
                        'details' => ['username' => $admin['username']],
                    ]);
                    $authenticated = true;
                } else {
                    self::recordFailure($identifier, 'admin');
                }
            }
        }

        return $authenticated;
    }

    public static function user(): ?array
    {
        if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
            return null;
        }

        return $_SESSION['auth'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function requireRole(string $role): void
    {
        if (!self::check() || self::role() !== $role) {
            ActivityLogger::log('access_denied', [
                'actor_role' => self::role() ?? 'guest',
                'action' => 'access_denied',
                'details' => ['required_role' => $role, 'path' => $_SERVER['REQUEST_URI'] ?? null],
            ]);
            Flash::set('error', 'Please sign in to continue.');
            $target = $role === 'admin' ? app_url('admin/login.php') : app_url('login.php');
            header('Location: ' . $target);
            exit;
        }
    }

    public static function logout(): void
    {
        $user = self::user();
        if ($user) {
            ActivityLogger::log('logout', [
                'actor_role' => (string) ($user['role'] ?? 'system'),
                'actor_id' => $user['id'] ?? null,
                'subject_type' => $user['role'] ?? null,
                'subject_id' => $user['id'] ?? null,
            ]);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    private static function canAttempt(string $identifier, string $role): array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT attempt_count, locked_until FROM login_attempts WHERE identifier = :identifier AND role = :role LIMIT 1');
        $stmt->execute(['identifier' => $identifier, 'role' => $role]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['allowed' => true, 'message' => ''];
        }

        $lockedUntil = $row['locked_until'] ?? null;
        if (is_string($lockedUntil) && $lockedUntil !== '') {
            $now = new DateTimeImmutable('now');
            $lockDate = new DateTimeImmutable($lockedUntil);
            if ($lockDate > $now) {
                return [
                    'allowed' => false,
                    'message' => 'Too many attempts. Try again at ' . $lockDate->format('H:i'),
                ];
            }
        }

        return ['allowed' => true, 'message' => ''];
    }

    private static function recordFailure(string $identifier, string $role): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT id, attempt_count FROM login_attempts WHERE identifier = :identifier AND role = :role LIMIT 1');
        $stmt->execute(['identifier' => $identifier, 'role' => $role]);
        $row = $stmt->fetch();

        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        if (!$row) {
            $insert = $pdo->prepare('INSERT INTO login_attempts (identifier, role, attempt_count, last_attempt_at) VALUES (:identifier, :role, 1, :last_attempt_at)');
            $insert->execute([
                'identifier' => $identifier,
                'role' => $role,
                'last_attempt_at' => $now,
            ]);
            return;
        }

        $count = (int) $row['attempt_count'] + 1;
        $lockedUntil = null;

        if ($count >= self::MAX_ATTEMPTS) {
            $lockedUntil = (new DateTimeImmutable('now'))->add(new DateInterval('PT' . self::LOCK_MINUTES . 'M'))->format('Y-m-d H:i:s');
            $count = 0;
        }

        $update = $pdo->prepare('UPDATE login_attempts SET attempt_count = :attempt_count, locked_until = :locked_until, last_attempt_at = :last_attempt_at WHERE id = :id');
        $update->bindValue('attempt_count', $count, PDO::PARAM_INT);
        $update->bindValue('locked_until', $lockedUntil);
        $update->bindValue('last_attempt_at', $now);
        $update->bindValue('id', (int) $row['id'], PDO::PARAM_INT);
        $update->execute();
    }

    private static function clearFailures(string $identifier, string $role): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE identifier = :identifier AND role = :role');
        $stmt->execute(['identifier' => $identifier, 'role' => $role]);
    }
}
