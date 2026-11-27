<?php
/**
 * Authentication & Authorization Helper
 *
 * Manages logged-in session state, role checks (guest, user, admin),
 * and server-side access guards for pages and JSON API endpoints.
 */

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

class Auth
{
    private const SESSION_USER_ID = 'user_id';
    private static ?array $cachedUser = null;
    private static bool $loaded = false;

    /**
     * Log in a verified user and regenerate the session ID to stop session fixation.
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_USER_ID] = (int) $user['id'];

        // Strip sensitive hash if present before caching in memory
        unset($user['password_hash']);
        self::$cachedUser = $user;
        self::$loaded = true;
    }

    /**
     * Destroy the user session and clear the session cookie cleanly.
     */
    public static function logout(): void
    {
        self::$cachedUser = null;
        self::$loaded = true;

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Return the currently authenticated user row (fresh from DB once per request), or null.
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$cachedUser;
        }

        self::$loaded = true;
        $userId = $_SESSION[self::SESSION_USER_ID] ?? null;

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            self::$cachedUser = null;
            return null;
        }

        $user = User::findById((int) $userId);
        if ($user === null) {
            // User record no longer exists; clean up stale session
            unset($_SESSION[self::SESSION_USER_ID]);
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = $user;
        return self::$cachedUser;
    }

    /**
     * True when a user is logged in.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Return the logged-in user's ID, or null for guests.
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * True only when the logged-in user has the 'admin' role in the database.
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? 'user') === 'admin';
    }

    /**
     * Server-side guard for HTML pages that require authentication.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            setFlash('warning', 'Please log in to access that page.');
            redirect('login.php');
        }
    }

    /**
     * Redirect logged-in users away from guest-only pages (login/signup).
     */
    public static function requireGuest(): void
    {
        if (self::check()) {
            redirect('index.php');
        }
    }

    /**
     * Server-side guard for admin-only HTML pages.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!self::isAdmin()) {
            http_response_code(403);
            $pageTitle = 'Access Denied';
            require APP_ROOT . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
            echo '<div class="alert alert-danger shadow-sm my-4">'
                . '<h1 class="h5 mb-2">403 - Access Denied</h1>'
                . '<p class="mb-3">You do not have permission to view this page.</p>'
                . '<a href="' . e(url('index.php')) . '" class="btn btn-outline-danger btn-sm">Return Home</a>'
                . '</div>';
            require APP_ROOT . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php';
            exit;
        }
    }

    /**
     * Server-side guard for Fetch API endpoints requiring a logged-in user.
     */
    public static function requireApiLogin(): void
    {
        if (!self::check()) {
            jsonResponse([
                'ok'      => false,
                'message' => 'Please log in to continue.',
            ], 401);
        }
    }

    /**
     * Server-side guard for Fetch API endpoints requiring admin privileges.
     */
    public static function requireApiAdmin(): void
    {
        self::requireApiLogin();

        if (!self::isAdmin()) {
            jsonResponse([
                'ok'      => false,
                'message' => 'You do not have permission to perform this action.',
            ], 403);
        }
    }
}
