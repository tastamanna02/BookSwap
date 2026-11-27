<?php
/**
 * Cross-Site Request Forgery (CSRF) Protection
 *
 * Stores one random secret token per session and verifies submitted form
 * and Fetch API tokens in constant time with hash_equals().
 */

declare(strict_types=1);

class Csrf
{
    private const SESSION_KEY = 'csrf_token';

    /**
     * Return the current session's CSRF token, generating it if missing.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Return a hidden HTML input element carrying the CSRF token for forms.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(self::token()) . '">';
    }

    /**
     * Verify a submitted CSRF token against the session token safely.
     */
    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $sessionToken = $_SESSION[self::SESSION_KEY] ?? '';
        if (!is_string($sessionToken) || $sessionToken === '') {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }
}
