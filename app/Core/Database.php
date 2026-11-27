<?php
/**
 * Database Connection Singleton (PDO)
 *
 * Marked final so it cannot be subclassed. Provides one shared PDO instance
 * per request with exceptions, associative fetches, and real prepared statements.
 */

declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    /**
     * Private constructor prevents creating Database objects directly.
     */
    private function __construct()
    {
    }

    /**
     * Private clone prevents duplicating the singleton instance.
     */
    private function __clone()
    {
    }

    /**
     * Return the shared PDO connection, creating it on the first call.
     */
    public static function pdo(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $cfg = require APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['name']
        );

        self::$instance = new PDO(
            $dsn,
            $cfg['user'],
            $cfg['pass'],
            [
                // Throw PDOException on SQL errors so transactions can roll back safely
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Fetch rows as associative arrays like $row['title']
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Use native MySQL prepared statements for stronger SQL injection defense
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return self::$instance;
    }
}
