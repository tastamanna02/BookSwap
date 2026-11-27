<?php
/**
 * Logout Handler
 *
 * Accepts POST requests only with a valid CSRF token so external links or
 * images cannot force a user to log out unexpectedly.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirect('index.php');
}

if (!Csrf::verifyFormPost()) {
    setFlash('danger', 'Something went wrong, try again.');
    redirect('index.php');
}

Auth::logout();

// Start a fresh session solely to show a friendly confirmation message after logout
session_start();
setFlash('info', 'You have been logged out safely.');
redirect('login.php');
