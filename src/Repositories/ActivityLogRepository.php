<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActivityLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO activity_logs (actor_role, actor_id, action, subject_type, subject_id, details, ip_address) VALUES (:actor_role, :actor_id, :action, :subject_type, :subject_id, :details, :ip_address)');
        $stmt->execute([
            'actor_role' => $data['actor_role'] ?? 'system',
            'actor_id' => $data['actor_id'] ?? null,
            'action' => $data['action'] ?? 'unknown',
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'details' => $data['details'] ?? null,
            'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);
    }

    public function recent(int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM activity_logs ORDER BY created_at DESC, id DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
