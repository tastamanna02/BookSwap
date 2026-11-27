<?php
/**
 * Database Connection Wrapper (PDO Singleton)
 *
 * Manages a single shared PDO connection to MySQL per request, configured with
 * exceptions on errors and real prepared statements (emulation disabled).
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $connection = null;

    /**
     * Prevent direct instantiation; use Database::getConnection().
     */
    private function __construct()
    {
    }

    /**
     * Return the shared PDO connection, creating it on first call.
     */
    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = require APP_ROOT . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['dbname'],
            $config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                // Throw PDOException on database errors so transactions can roll back cleanly
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Return associative arrays by default (e.g. $row['title'])
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Force real MySQL prepared statements for stronger SQL injection protection
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        return self::$connection;
    }
}
