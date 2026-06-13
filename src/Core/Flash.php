<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    public static function set(string $type, string $message): void
    {
        $_SESSION['_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    public static function get(): ?array
    {
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            return null;
        }

        $flash = $_SESSION['_flash'];
        unset($_SESSION['_flash']);

        return $flash;
    }
}
