<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class PasswordResetRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): string
    {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare('INSERT INTO password_reset_tokens (user_type, user_id, identifier, token_hash, expires_at, requested_ip) VALUES (:user_type, :user_id, :identifier, :token_hash, :expires_at, :requested_ip)');
        $stmt->execute([
            'user_type' => $data['user_type'],
            'user_id' => $data['user_id'],
            'identifier' => $data['identifier'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => $data['expires_at'],
            'requested_ip' => $data['requested_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);

        return $token;
    }

    public function findValidByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM password_reset_tokens WHERE token_hash = :token_hash AND used_at IS NULL AND expires_at > NOW() LIMIT 1');
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markUsed(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
