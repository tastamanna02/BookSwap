<?php
/**
 * User Model
 *
 * Handles student and admin accounts using PDO prepared statements,
 * password_hash()/password_verify(), and brute-force login lockout tracking.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

class User
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Find a user by ID without returning sensitive password or lockout internals.
     */
    public static function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, created_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Find a user by email (includes password_hash and lockout columns).
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password_hash, role, failed_attempts, locked_until, created_at
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
    public static function create(string $name, string $email, string $password): int
    {
        $pdo = Database::getConnection();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim($name),
            strtolower(trim($email)),
            $hash,
            'user',
        ]);

        return (int) $pdo->lastInsertId();
    }

    /**
     * Verify login credentials using password_verify() and enforce account lockout
     * after repeated failed attempts.
     */
    public static function verifyCredentials(string $email, string $password): array
    {
        $user = self::findByEmail($email);
        if ($user === null) {
            return [
                'ok'      => false,
                'user'    => null,
                'message' => 'Invalid email or password.',
            ];
        }

        // Check if account is currently locked due to too many failed logins
        if (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time()) {
            return [
                'ok'      => false,
                'user'    => null,
                'message' => 'Too many failed login attempts. Please wait a few minutes and try again.',
            ];
        }

        $pdo = Database::getConnection();

        if (!password_verify($password, $user['password_hash'])) {
            // Reset counter if an old lockout has already expired
            $previousAttempts = (!empty($user['locked_until']) && strtotime((string) $user['locked_until']) <= time())
                ? 0
                : (int) $user['failed_attempts'];

            $newAttempts = $previousAttempts + 1;

            if ($newAttempts >= self::MAX_FAILED_ATTEMPTS) {
                $lockStmt = $pdo->prepare(
                    'UPDATE users
                     SET failed_attempts = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                     WHERE id = ?'
                );
                $lockStmt->execute([$newAttempts, self::LOCKOUT_MINUTES, (int) $user['id']]);

                return [
                    'ok'      => false,
                    'user'    => null,
                    'message' => 'Too many failed login attempts. Your account is temporarily locked for 15 minutes.',
                ];
            }

            $failStmt = $pdo->prepare(
                'UPDATE users SET failed_attempts = ?, locked_until = NULL WHERE id = ?'
            );
            $failStmt->execute([$newAttempts, (int) $user['id']]);

            return [
                'ok'      => false,
                'user'    => null,
                'message' => 'Invalid email or password.',
            ];
        }

        // Successful login: reset failed attempts and upgrade hash if PHP default changed
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET password_hash = ?, failed_attempts = 0, locked_until = NULL
                 WHERE id = ?'
            );
            $stmt->execute([$newHash, (int) $user['id']]);
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?'
            );
            $stmt->execute([(int) $user['id']]);
        }

        unset($user['password_hash'], $user['failed_attempts'], $user['locked_until']);

        return [
            'ok'      => true,
            'user'    => $user,
            'message' => '',
        ];
    }

    /**
     * Update the user's editable display name.
     */
    public static function updateName(int $userId, string $name): bool
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');

        return $stmt->execute([trim($name), $userId]);
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
