<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Env;
use PDO;
use PDOException;

final class Database
{
	private static ?PDO $pdo = null;

	public static function connection(): PDO
	{
		if (self::$pdo !== null) {
			return self::$pdo;
		}

		$host = Env::get('DB_HOST', '127.0.0.1');
		$port = Env::get('DB_PORT', '3306');
		$db = Env::get('DB_NAME', 'university_portal');
		$user = Env::get('DB_USER', 'root');
		$pass = Env::get('DB_PASS', '');

		$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

		try {
			self::$pdo = new PDO($dsn, $user, $pass, [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
				PDO::ATTR_EMULATE_PREPARES => false,
			]);
		} catch (PDOException $exception) {
			http_response_code(500);
			exit('Database connection failed: ' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8'));
		}

		return self::$pdo;
	}
}

