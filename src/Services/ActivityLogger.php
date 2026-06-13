<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Repositories\ActivityLogRepository;

final class ActivityLogger
{
    public static function log(string $action, array $data = []): void
    {
        $repo = new ActivityLogRepository(Database::connection());
        $repo->create([
            'actor_role' => $data['actor_role'] ?? ($_SESSION['auth']['role'] ?? 'system'),
            'actor_id' => $data['actor_id'] ?? ($_SESSION['auth']['id'] ?? null),
            'action' => $action,
            'subject_type' => $data['subject_type'] ?? null,
            'subject_id' => $data['subject_id'] ?? null,
            'details' => self::stringifyDetails($data['details'] ?? null),
            'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? null),
        ]);
    }

    private static function stringifyDetails(mixed $details): ?string
    {
        if ($details === null) {
            return null;
        }

        if (is_string($details)) {
            return $details;
        }

        return json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
