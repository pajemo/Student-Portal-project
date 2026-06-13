<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Env;

final class GradingPolicy
{
    public static function gradePoints(): array
    {
        $json = Env::get('GRADE_POLICY_JSON');
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded['grade_points']) && is_array($decoded['grade_points'])) {
                return self::normalizeGradePoints($decoded['grade_points']);
            }
        }

        return [
            'A' => 4.0,
            'A-' => 3.7,
            'B+' => 3.5,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.5,
            'C' => 2.0,
            'C-' => 1.7,
            'D+' => 1.5,
            'D' => 1.0,
            'F' => 0.0,
        ];
    }

    public static function precision(): int
    {
        $value = Env::get('GRADE_PRECISION', '2');
        return max(0, (int) $value);
    }

    public static function policyName(): string
    {
        return Env::get('GRADE_POLICY_NAME', 'Default 4.0 scale') ?? 'Default 4.0 scale';
    }

    private static function normalizeGradePoints(array $points): array
    {
        $normalized = [];
        foreach ($points as $grade => $point) {
            $normalized[strtoupper(trim((string) $grade))] = (float) $point;
        }

        ksort($normalized);
        return $normalized;
    }
}
