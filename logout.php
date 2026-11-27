<?php
/**
 * Logout Handler
 *
 * Accepts POST requests with a valid CSRF token, destroys the current session
 * cleanly via Auth::logout(), starts a new session, flashes confirmation,
 * and redirects to login.php.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Csrf::check($_POST['csrf_token'] ?? null)) {
    redirect('index.php');
}

Auth::logout();

// Start a fresh session solely to store the one-time logout flash message
session_start();
flash('info', 'You have been logged out');
redirect('login.php');
