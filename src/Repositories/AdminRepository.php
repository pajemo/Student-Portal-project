<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AdminRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
        $stmt->execute(['username' => trim($username)]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
