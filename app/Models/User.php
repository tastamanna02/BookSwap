<?php
/**
 * User Model
 *
 * Handles student and admin database queries, secure password hashing
 * (password_hash / password_verify), and profile updates using PDO.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User
{
    /**
     * Find a user by ID without returning their password hash.
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, university, bio, role, created_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Find a user by email (includes password_hash for login verification).
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password_hash, university, bio, role, created_at
             FROM users
             WHERE email = ?
             LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Check whether an email address is already registered.
     */
    public static function emailExists(string $email): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([strtolower(trim($email))]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * Register a new student account with a securely hashed password.
     * Role is always forced to 'user' on public signup.
     */
    public static function create(string $name, string $email, string $password, string $university = ''): int
    {
        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, university, role)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($name),
            strtolower(trim($email)),
            $hash,
            trim($university),
            'user',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Verify email and password using password_verify(); return safe user data or null.
     */
    public static function verifyCredentials(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Automatically upgrade older password hashes if PHP's default cost changes
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([$newHash, (int) $user['id']]);
        }

        unset($user['password_hash']);
        return $user;
    }

    /**
     * Update a user's editable profile fields.
     */
    public static function updateProfile(int $userId, string $name, string $university, string $bio): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'UPDATE users
             SET name = ?, university = ?, bio = ?
             WHERE id = ?'
        );

        return $stmt->execute([
            trim($name),
            trim($university),
            trim($bio),
            $userId,
        ]);
    }

    /**
     * Change password after verifying the user's current password.
     */
    public static function updatePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();

        if ($row === false || !password_verify($currentPassword, $row['password_hash'])) {
            return ['ok' => false, 'message' => 'Your current password is incorrect.'];
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $update->execute([$newHash, $userId]);

        return ['ok' => true, 'message' => 'Password updated successfully.'];
    }

    /**
     * Count total registered users for the admin dashboard.
     */
    public static function countAll(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');
        return (int) $stmt->fetchColumn();
    }
}
