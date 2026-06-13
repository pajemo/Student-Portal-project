<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function required(?string $value): bool
    {
        return $value !== null && trim($value) !== '';
    }

    public static function maxLength(?string $value, int $max): bool
    {
        if ($value === null) {
            return true;
        }

        return mb_strlen(trim($value)) <= $max;
    }

    public static function in(?string $value, array $allowed): bool
    {
        if ($value === null) {
            return false;
        }

        return in_array($value, $allowed, true);
    }

    public static function csvFile(array $file): bool
    {
        if (!isset($file['tmp_name'], $file['name'])) {
            return false;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        return $ext === 'csv';
    }

    public static function pdfFile(array $file): bool
    {
        if (!isset($file['tmp_name'], $file['name'])) {
            return false;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        return $ext === 'pdf';
    }

    public static function pdfOrZipFile(array $file): bool
    {
        if (!isset($file['tmp_name'], $file['name'])) {
            return false;
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        return in_array($ext, ['pdf', 'zip'], true);
    }

    public static function academicYear(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        $normalized = preg_replace('/\s+/', '', trim($value));
        if (!is_string($normalized)) {
            return false;
        }

        return (bool) preg_match('/^\d{4}[\/-]\d{4}$/', $normalized);
    }

    public static function normalizeAcademicYear(?string $value): ?string
    {
        if (!self::academicYear($value)) {
            return null;
        }

        $normalized = preg_replace('/\s+/', '', trim((string) $value));
        if (!is_string($normalized)) {
            return null;
        }

        return str_replace('-', '/', $normalized);
    }

    /**
     * @return array<int, string>
     */
    public static function academicYearOptions(int $startYear, int $endYear): array
    {
        $options = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $options[] = $year . '/' . ($year + 1);
        }

        return $options;
    }

    public static function academicYearAllowed(?string $value, int $startYear, int $endYear): bool
    {
        $normalized = self::normalizeAcademicYear($value);
        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::academicYearOptions($startYear, $endYear), true);
    }

    public static function email(?string $value): bool
    {
        if ($value === null) {
            return false;
        }

        return filter_var(trim($value), FILTER_VALIDATE_EMAIL) !== false;
    }
}
