<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    $sessionSavePath = dirname(__DIR__) . '/storage/sessions';
    if (!is_dir($sessionSavePath)) {
        mkdir($sessionSavePath, 0700, true);
    }
    session_save_path($sessionSavePath);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('BASE_PATH', dirname(__DIR__));
define('SRC_PATH', BASE_PATH . '/src');
define('PUBLIC_PATH', BASE_PATH);

autoloadRegister();

App\Core\Env::load(BASE_PATH . '/.env');

function app_base_path(): string
{
    $configured = trim((string) App\Core\Env::get('APP_BASE_PATH', ''));
    if ($configured !== '') {
        $configured = '/' . trim($configured, '/');
        return $configured === '/' ? '' : $configured;
    }

    $documentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    if ($documentRoot === '') {
        return '';
    }

    $normalizedDocumentRoot = str_replace('\\', '/', rtrim((string) realpath($documentRoot), DIRECTORY_SEPARATOR));
    $normalizedBasePath = str_replace('\\', '/', rtrim((string) realpath(BASE_PATH), DIRECTORY_SEPARATOR));

    if ($normalizedDocumentRoot === '' || $normalizedBasePath === '' || strpos($normalizedBasePath, $normalizedDocumentRoot) !== 0) {
        return '';
    }

    $relativePath = substr($normalizedBasePath, strlen($normalizedDocumentRoot));
    $relativePath = '/' . trim(str_replace('\\', '/', (string) $relativePath), '/');

    return $relativePath === '/' ? '' : $relativePath;
}

function app_url(string $path = ''): string
{
    $base = app_base_path();
    if ($path === '') {
        return $base !== '' ? $base : '/';
    }

    return ($base !== '' ? $base : '') . '/' . ltrim($path, '/');
}

function app_asset(string $path): string
{
    $assetPath = 'assets/' . ltrim($path, '/');
    $url = app_url($assetPath);
    $filePath = PUBLIC_PATH . '/' . $assetPath;

    if (is_file($filePath)) {
        $version = (string) filemtime($filePath);
        $separator = strpos($url, '?') !== false ? '&' : '?';

        return $url . $separator . 'v=' . $version;
    }

    return $url;
}

function autoloadRegister(): void
{
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = SRC_PATH . '/' . str_replace('\\', '/', $relativeClass) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });
}
