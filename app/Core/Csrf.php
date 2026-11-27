<?php
/**
 * Cross-Site Request Forgery (CSRF) Protection Helper
 *
 * Generates one random secret token per session and verifies it on every
 * HTML form POST and every Fetch API POST (via the X-CSRF-Token header).
 */

declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Return the current session's CSRF token, creating one if needed.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Return a hidden HTML input element containing the CSRF token for forms.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    /**
     * Compare a submitted token against the session token in constant time.
     */
    public static function verifyToken(?string $submittedToken): bool
    {
        if (!is_string($submittedToken) || $submittedToken === '') {
            return false;
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $submittedToken);
    }

    /**
     * Validate the CSRF token submitted from an HTML form POST.
     */
    public static function verifyFormPost(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return false;
        }

        $submitted = isset($_POST['csrf_token']) && is_string($_POST['csrf_token'])
            ? $_POST['csrf_token']
            : null;

        return self::verifyToken($submitted);
    }

    /**
     * Read the X-CSRF-Token header sent by Fetch API requests.
     */
    public static function getHeaderToken(): ?string
    {
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) && is_string($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Fallback for Apache configurations that expose headers via getallheaders()
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'X-CSRF-Token') === 0 && is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Enforce POST method and valid X-CSRF-Token header on JSON API endpoints.
     */
    public static function requireValidApiPost(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            jsonResponse(['ok' => false, 'message' => 'Method not allowed.'], 405);
        }

        if (!self::verifyToken(self::getHeaderToken())) {
            jsonResponse([
                'ok'      => false,
                'message' => 'Invalid security token. Please refresh the page and try again.',
            ], 403);
        }
    }
}
