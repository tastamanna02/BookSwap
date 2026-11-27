<?php
/**
 * Database Configuration File
 *
 * Returns an associative array containing the MySQL connection settings
 * used by app/Core/Database.php. Using XAMPP defaults by default.
 */

declare(strict_types=1);

return [
    // Hostname where MySQL / MariaDB runs in XAMPP (127.0.0.1 avoids slow DNS lookup)
    'host' => '127.0.0.1',

    // Standard MySQL port in XAMPP
    'port' => 3306,

    // Database name created by sql/schema.sql
    'dbname' => 'bookswap',

    // Default XAMPP MySQL credentials (change these in production!)
    'username' => 'root',
    'password' => '',

    // Always use utf8mb4 so emojis and international characters work safely
    'charset' => 'utf8mb4',
];
