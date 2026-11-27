<?php
/**
 * User Model
 *
 * Handles user lookups, secure password_hash()/password_verify() storage,
 * brute-force lockout tracking (5 failures -> 15 min lock), and admin stats.
 */

declare(strict_types=1);

class User
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Find a user by ID without returning their password hash.
     */
    public static function find(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, name, email, role, failed_attempts, locked_until, created_at
             FROM users
             WHERE id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user !== false ? $user : null;
    }

    /**
     * Find a user by email (includes password_hash and lockout columns for login).
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::pdo();
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
     * Register a new student account with password_hash() and 'user' role.
     */
    public static function create(string $name, string $email, string $password): int
    {
        $pdo = Database::pdo();
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
     * Update a user's display name.
     */
    public static function updateName(int $id, string $name): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE users SET name = ? WHERE id = ?');

        return $stmt->execute([trim($name), $id]);
    }

    /**
     * Hash and save a new password for the given user ID.
     */
    public static function updatePassword(int $id, string $newPassword): bool
    {
        $pdo = Database::pdo();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');

        return $stmt->execute([$hash, $id]);
    }

    /**
     * Verify a user's current password by ID (used when changing password in profile).
     */
    public static function verifyPassword(int $id, string $password): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row !== false && password_verify($password, $row['password_hash']);
    }

    /**
     * Return true if the user's account is currently inside a lockout window.
     */
    public static function isLocked(array $user): bool
    {
        if (empty($user['locked_until'])) {
            return false;
        }

        return strtotime((string) $user['locked_until']) > time();
    }

    /**
     * Increment failed login attempts; on the 5th failure lock for 15 minutes
     * and reset the attempt counter to 0.
     */
    public static function recordFailure(array $user): void
    {
        $pdo = Database::pdo();
        $userId = (int) $user['id'];
        $attempts = (int) ($user['failed_attempts'] ?? 0) + 1;

        if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
            $stmt = $pdo->prepare(
                'UPDATE users
                 SET failed_attempts = 0, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE)
                 WHERE id = ?'
            );
            $stmt->execute([self::LOCKOUT_MINUTES, $userId]);
            return;
        }

        $stmt = $pdo->prepare(
            'UPDATE users SET failed_attempts = ?, locked_until = NULL WHERE id = ?'
        );
        $stmt->execute([$attempts, $userId]);
    }

    /**
     * Clear any failed login attempts and lockout timestamp after a successful login.
     */
    public static function resetFailures(int $id): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?'
        );
        $stmt->execute([$id]);
    }

    /**
     * List all registered users along with how many books each user owns.
     */
    public static function all(): array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query(
            'SELECT u.id, u.name, u.email, u.role, u.locked_until, u.created_at,
                    COUNT(b.id) AS book_count
             FROM users u
             LEFT JOIN books b ON b.owner_id = u.id
             GROUP BY u.id
             ORDER BY u.created_at DESC, u.id DESC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Count total registered users.
     */
    public static function count(): int
    {
        $pdo = Database::pdo();
        $stmt = $pdo->query('SELECT COUNT(*) FROM users');

        return (int) $stmt->fetchColumn();
    }
}
