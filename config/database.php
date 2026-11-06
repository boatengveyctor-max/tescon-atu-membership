<?php
declare(strict_types=1);

require_once __DIR__ . '/constants.php';

final class Database 
{
    private static ?PDO $pdo = null;

    private function __construct() {}

    public static function connection(): PDO 
    {
        if (self::$pdo === null) {
            if (DB_USER === '') {
                throw new RuntimeException('Database credentials are not configured. Set DB_USER in the server environment or .env file.');
            }

            self::$pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        }

        return self::$pdo;
    }
}

function db(): PDO 
{
    return Database::connection();
}