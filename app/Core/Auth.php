<?php
/**
 * Authentication & Role Authorization Helper
 *
 * Manages session login state via $_SESSION['uid'], caches the current user,
 * prevents session fixation on login, and enforces page access rules.
 */

declare(strict_types=1);

class Auth
{
    private const SESSION_UID = 'uid';
    private static ?array $cachedUser = null;
    private static bool $loaded = false;

    /**
     * Return the logged-in user row (cached per request), or null for guests.
     */
    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$cachedUser;
        }

        self::$loaded = true;
        $uid = $_SESSION[self::SESSION_UID] ?? null;

        if (!is_int($uid) && !ctype_digit((string) $uid)) {
            self::$cachedUser = null;
            return null;
        }

        $user = User::find((int) $uid);
        if ($user === null) {
            unset($_SESSION[self::SESSION_UID]);
            self::$cachedUser = null;
            return null;
        }

        self::$cachedUser = $user;
        return self::$cachedUser;
    }

    /**
     * Return true when a user is currently authenticated.
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Return the logged-in user's ID, or null when browsing as guest.
     */
    public static function id(): ?int
    {
        $user = self::user();
        return $user !== null ? (int) $user['id'] : null;
    }

    /**
     * Return true only when the logged-in user has the 'admin' role.
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && ($user['role'] ?? 'user') === 'admin';
    }

    /**
     * Log in a verified user and regenerate the session ID to stop session fixation.
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION[self::SESSION_UID] = (int) $user['id'];

        unset($user['password_hash']);
        self::$cachedUser = $user;
        self::$loaded = true;
    }

    /**
     * Clear session data, expire the session cookie, and destroy the session.
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
     * Require a logged-in user, otherwise set a flash notice and redirect to login.php.
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('warning', 'Please log in to continue.');
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
     * Require an authenticated admin user, returning HTTP 403 for non-admins.
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!self::isAdmin()) {
            http_response_code(403);
            $title = 'Access Denied';
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
}
