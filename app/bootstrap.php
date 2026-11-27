<?php
/**
 * Application Bootstrap
 *
 * Sets strict types, configures secure sessions, registers class autoloading
 * for app/Core and app/Models, sets a safe global exception handler, and
 * defines shared escaping, flash, redirect, 404, and JSON API helpers.
 */

declare(strict_types=1);

// Log PHP errors on the server instead of exposing stack traces in the browser
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');

/**
 * Detect BASE_URL dynamically so links work in C:\xampp\htdocs\BookSwap
 * as well as when served from the project root (php -S localhost:8000).
 */
$detectBaseUrl = static function (): string {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($docRoot !== false && $appRoot !== false) {
        $normDoc = str_replace('\\', '/', rtrim($docRoot, '\\/'));
        $normApp = str_replace('\\', '/', rtrim($appRoot, '\\/'));

        if (stripos($normApp, $normDoc) === 0) {
            $relative = substr($normApp, strlen($normDoc));
            return $relative === false ? '' : rtrim($relative, '/');
        }
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($scriptName));
    if (substr($dir, -4) === '/api') {
        $dir = dirname($dir);
    }
    $dir = rtrim($dir, '/');
    return ($dir === '.' || $dir === '') ? '' : $dir;
};

define('BASE_URL', $detectBaseUrl());

// Start session with HttpOnly and SameSite=Lax cookie protections
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// Autoload classes from app/Core and app/Models automatically
spl_autoload_register(static function (string $className): void {
    // Strip optional namespace prefix if passed, keeping only the class name
    $shortName = ltrim($className, '\\');
    if (strpos($shortName, '\\') !== false) {
        $parts = explode('\\', $shortName);
        $shortName = end($parts);
    }

    $directories = [
        APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR,
        APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR,
    ];

    foreach ($directories as $dir) {
        $file = $dir . $shortName . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Global exception handler: log real cause, display friendly message without stack trace
set_exception_handler(static function (Throwable $e): void {
    error_log('[BookSwap Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $isApi = defined('BOOKSWAP_API') || strpos($scriptPath, '/api/') !== false;

    if ($isApi) {
        json_out(['ok' => false, 'message' => 'Something went wrong, try again'], 500);
    }

    if (!headers_sent()) {
        http_response_code(500);
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>BookSwap - Error</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '</head><body class="bg-light d-flex align-items-center min-vh-100">'
        . '<div class="container"><div class="row justify-content-center"><div class="col-md-6">'
        . '<div class="card shadow-sm border-0 p-4 text-center">'
        . '<h1 class="h4 text-danger mb-3">Oops!</h1>'
        . '<p class="text-muted mb-4">Something went wrong, try again</p>'
        . '<a href="' . e(url('index.php')) . '" class="btn btn-primary">Back to Home</a>'
        . '</div></div></div></div></body></html>';
    exit;
});

/**
 * Escape HTML output with htmlspecialchars to block Cross-Site Scripting (XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an application URL prefixed with BASE_URL.
 */
function url(string $path = ''): string
{
    $cleanPath = ltrim($path, '/');
    if ($cleanPath === '') {
        return BASE_URL === '' ? '/' : BASE_URL . '/';
    }
    return (BASE_URL === '' ? '' : BASE_URL) . '/' . $cleanPath;
}

/**
 * Redirect to a local application path and stop script execution.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Set or read a one-time flash message stored in the session.
 * - Pass ($type, $msg) to store a flash message before a redirect.
 * - Call flash() with no arguments to read and clear the stored message.
 */
function flash(string $type = '', ?string $msg = null): ?array
{
    if ($type !== '' && $msg !== null) {
        $allowedTypes = ['success', 'danger', 'warning', 'info'];
        $_SESSION['flash'] = [
            'type' => in_array($type, $allowedTypes, true) ? $type : 'info',
            'msg'  => $msg,
        ];
        return null;
    }

    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $stored = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $stored;
}

/**
 * Send a JSON response with an HTTP status code and stop script execution.
 */
function json_out(array $data, int $code = 200): void
{
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

/**
 * Render the 404 Not Found page with a 404 HTTP status code and exit.
 */
function show_404(): void
{
    if (!headers_sent()) {
        http_response_code(404);
    }
    require APP_ROOT . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

/**
 * Enforce HTTP method, optional authentication, and X-CSRF-Token on POST APIs.
 */
function api_guard(string $method = 'POST', bool $needLogin = true): void
{
    $expectedMethod = strtoupper($method);
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== $expectedMethod) {
        json_out(['ok' => false, 'message' => 'Method not allowed.'], 405);
    }

    if ($needLogin && !Auth::check()) {
        json_out(['ok' => false, 'message' => 'Please log in to continue.'], 401);
    }

    if ($expectedMethod === 'POST') {
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!is_string($headerToken) && function_exists('getallheaders')) {
            foreach (getallheaders() as $name => $value) {
                if (strcasecmp((string) $name, 'X-CSRF-Token') === 0 && is_string($value)) {
                    $headerToken = $value;
                    break;
                }
            }
        }

        if (!Csrf::check(is_string($headerToken) ? $headerToken : null)) {
            json_out([
                'ok'      => false,
                'message' => 'Invalid security token. Please refresh the page and try again.',
            ], 403);
        }
    }
}

/**
 * Read and decode a JSON request body safely into an associative array.
 */
function api_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Allowed book condition keys matching Book::CONDITIONS and the database ENUM.
 */
function bookConditions(): array
{
    return array_keys(Book::CONDITIONS);
}

/**
 * Map book or swap status values to Bootstrap 5 badge color classes.
 */
function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'available':
        case 'completed':
            return 'bg-success';
        case 'accepted':
            return 'bg-primary';
        case 'pending':
        case 'reserved':
            return 'bg-warning text-dark';
        case 'declined':
            return 'bg-danger';
        case 'cancelled':
        case 'swapped':
        default:
            return 'bg-secondary';
    }
}
