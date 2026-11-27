<?php
/**
 * Application Bootstrap
 *
 * Loaded at the top of every page and API endpoint. Sets up error handling,
 * secure sessions, dynamic base URL paths, class autoloading, and helpers.
 */

declare(strict_types=1);

// Never print raw PHP errors or stack traces to users; log them on the server instead.
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Core filesystem paths
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_DIR', APP_ROOT . DIRECTORY_SEPARATOR . 'uploads');

/**
 * Compute BASE_URL dynamically so the app works both in XAMPP (e.g. /BookSwap)
 * and when served directly from a web root (e.g. php -S localhost:8000).
 */
$detectBaseUrl = static function (): string {
    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(APP_ROOT);

    if ($docRoot !== false && $appRoot !== false) {
        $normDoc = str_replace('\\', '/', rtrim($docRoot, '\\/'));
        $normApp = str_replace('\\', '/', rtrim($appRoot, '\\/'));

        // Case-insensitive prefix check for Windows paths
        if (stripos($normApp, $normDoc) === 0) {
            $relative = substr($normApp, strlen($normDoc));
            return $relative === false ? '' : rtrim($relative, '/');
        }
    }

    // Fallback when using an Apache Alias: inspect script path
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($scriptName));
    if (substr($dir, -4) === '/api') {
        $dir = dirname($dir);
    }
    $dir = rtrim($dir, '/');
    return ($dir === '.' || $dir === '') ? '' : $dir;
};

define('BASE_URL', $detectBaseUrl());

// Configure secure session cookie settings before starting the PHP session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,          // Expires when browser closes
        'path'     => BASE_URL !== '' ? BASE_URL . '/' : '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,       // Blocks JavaScript from reading the session cookie
        'samesite' => 'Lax',      // Extra defense against CSRF attacks
    ]);

    session_start();
}

// Simple PSR-4 style autoloader for App\Core\* and App\Models\*
spl_autoload_register(static function (string $className): void {
    $prefix = 'App\\';
    if (strpos($className, $prefix) !== 0) {
        return;
    }

    $relativeClass = substr($className, strlen($prefix));
    $file = APP_ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// Global exception handler: log real error, show friendly message without stack trace
set_exception_handler(static function (Throwable $e): void {
    error_log('[BookSwap Error] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $isApi = defined('BOOKSWAP_API')
        || strpos(str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/api/') !== false;

    if (!headers_sent()) {
        http_response_code(500);
    }

    if ($isApi) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok' => false,
            'message' => 'Something went wrong, try again.',
        ]);
        exit;
    }

    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>BookSwap - Error</title>'
        . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">'
        . '</head><body class="bg-light d-flex align-items-center min-vh-100">'
        . '<div class="container"><div class="row justify-content-center"><div class="col-md-6">'
        . '<div class="card shadow-sm border-0 p-4 text-center">'
        . '<h1 class="h4 text-danger mb-3">Oops!</h1>'
        . '<p class="text-muted mb-4">Something went wrong, try again.</p>'
        . '<a href="' . e(url('index.php')) . '" class="btn btn-primary">Back to Home</a>'
        . '</div></div></div></div></body></html>';
    exit;
});

/**
 * Escape HTML output safely to prevent Cross-Site Scripting (XSS).
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Build an application URL using BASE_URL so links work in any folder setup.
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
 * Store a one-time flash notification in the session (for Post-Redirect-Get).
 */
function setFlash(string $type, string $message): void
{
    $allowedTypes = ['success', 'danger', 'warning', 'info'];
    $_SESSION['flash'] = [
        'type'    => in_array($type, $allowedTypes, true) ? $type : 'info',
        'message' => $message,
    ];
}

/**
 * Retrieve and clear the one-time flash notification from the session.
 */
function getFlash(): ?array
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Send a JSON response with an HTTP status code and stop execution.
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data);
    exit;
}

/**
 * Parse a JSON request body safely into an associative array.
 */
function getJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Allowed book categories used across forms, search filters, and validation.
 */
function bookCategories(): array
{
    return [
        'Computer Science',
        'Engineering',
        'Mathematics & Science',
        'Economics & Business',
        'Humanities & Social Sciences',
        'Medicine & Health',
        'Fiction & Literature',
        'Exam Prep & Study Guides',
        'Other',
    ];
}

/**
 * Allowed physical book condition values matching the database ENUM.
 */
function bookConditions(): array
{
    return ['New', 'Like New', 'Good', 'Fair'];
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
