<?php
/**
 * Shared Header Partial
 *
 * Outputs the HTML5 document head, Bootstrap 5 CDN stylesheet, CSRF meta tag
 * for Fetch requests, the role-aware navigation bar, and one-time flash alerts.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;

$currentUser = Auth::user();
$flash = getFlash();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$fullTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' - BookSwap' : 'BookSwap - Student Book Exchange';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF token & base URL read by assets/js/app.js for Fetch requests -->
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <title><?= e($fullTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= e(url('index.php')) ?>">BookSwap</a>
        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNav"
                aria-controls="mainNav"
                aria-expanded="false"
                aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>"
                       href="<?= e(url('index.php')) ?>">Browse Books</a>
                </li>
                <?php if ($currentUser !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'my-books.php' ? 'active fw-semibold' : '' ?>"
                           href="<?= e(url('my-books.php')) ?>">My Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'book-form.php' ? 'active fw-semibold' : '' ?>"
                           href="<?= e(url('book-form.php')) ?>">Add Book</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'swaps.php' ? 'active fw-semibold' : '' ?>"
                           href="<?= e(url('swaps.php')) ?>">My Swaps</a>
                    </li>
                    <?php if (Auth::isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === 'admin.php' ? 'active fw-semibold' : '' ?>"
                               href="<?= e(url('admin.php')) ?>">Admin</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <?php if ($currentUser !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'profile.php' ? 'active fw-semibold' : '' ?>"
                           href="<?= e(url('profile.php')) ?>">
                            <?= e($currentUser['name']) ?>
                            <?php if (Auth::isAdmin()): ?>
                                <span class="badge bg-warning text-dark ms-1">Admin</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <!-- Logout uses POST + CSRF token so external sites cannot log users out -->
                        <form method="POST" action="<?= e(url('logout.php')) ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-outline-light btn-sm">Log Out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'login.php' ? 'active fw-semibold' : '' ?>"
                           href="<?= e(url('login.php')) ?>">Log In</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-light btn-sm fw-semibold text-primary"
                           href="<?= e(url('signup.php')) ?>">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <?php if ($flash !== null): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
            <?= e($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <div id="global-alert" class="d-none" role="alert"></div>
