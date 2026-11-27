<?php
/**
 * Shared Header Partial
 *
 * Outputs HTML5 head, CSRF meta tag, Bootstrap 5.3 CDN CSS, custom CSS,
 * dark responsive navbar with active highlighting & incoming swap badge,
 * and opens <main> with an #alerts container for dismissible flash messages.
 */

declare(strict_types=1);

$currentUser = Auth::user();
$flash = flash();
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? 'index.php');
$fullTitle = isset($title) && is_string($title) && $title !== ''
    ? $title . ' - BookSwap'
    : 'BookSwap - Student Book Exchange';
$pendingIncoming = $currentUser !== null ? Swap::pendingIncoming((int) $currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- CSRF token & base URL read by assets/js/app.js for Fetch requests -->
    <meta name="csrf" content="<?= e(Csrf::token()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <title><?= e($fullTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
          crossorigin="anonymous">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
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
                    <a class="nav-link<?= e($currentPage === 'index.php' ? ' active fw-semibold' : '') ?>"
                       aria-current="<?= e($currentPage === 'index.php' ? 'page' : 'false') ?>"
                       href="<?= e(url('index.php')) ?>">Browse</a>
                </li>
                <?php if ($currentUser !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= e($currentPage === 'my-books.php' ? ' active fw-semibold' : '') ?>"
                           aria-current="<?= e($currentPage === 'my-books.php' ? 'page' : 'false') ?>"
                           href="<?= e(url('my-books.php')) ?>">My Books</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link<?= e($currentPage === 'swaps.php' ? ' active fw-semibold' : '') ?>"
                           aria-current="<?= e($currentPage === 'swaps.php' ? 'page' : 'false') ?>"
                           href="<?= e(url('swaps.php')) ?>">
                            Swaps
                            <span id="nav-pending-badge"
                                  class="badge rounded-pill bg-danger ms-1<?= e($pendingIncoming > 0 ? '' : ' d-none') ?>">
                                <?= e((string) $pendingIncoming) ?>
                            </span>
                        </a>
                    </li>
                    <?php if (Auth::isAdmin()): ?>
                        <li class="nav-item">
                            <a class="nav-link<?= e($currentPage === 'admin.php' ? ' active fw-semibold' : '') ?>"
                               aria-current="<?= e($currentPage === 'admin.php' ? 'page' : 'false') ?>"
                               href="<?= e(url('admin.php')) ?>">Admin</a>
                        </li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                <?php if ($currentUser !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link<?= e($currentPage === 'profile.php' ? ' active fw-semibold' : '') ?>"
                           aria-current="<?= e($currentPage === 'profile.php' ? 'page' : 'false') ?>"
                           href="<?= e(url('profile.php')) ?>">
                            <?= e($currentUser['name']) ?>
                            <?php if (Auth::isAdmin()): ?>
                                <span class="badge bg-warning text-dark ms-1">Admin</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <!-- Logout uses POST + CSRF field so external sites cannot log users out -->
                        <form method="POST" action="<?= e(url('logout.php')) ?>" class="d-inline">
                            <?= Csrf::field() ?>
                            <button type="submit" class="btn btn-outline-light btn-sm">Log out</button>
                        </form>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link<?= e($currentPage === 'login.php' ? ' active fw-semibold' : '') ?>"
                           aria-current="<?= e($currentPage === 'login.php' ? 'page' : 'false') ?>"
                           href="<?= e(url('login.php')) ?>">Log in</a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm fw-semibold<?= e($currentPage === 'signup.php' ? ' active' : '') ?>"
                           aria-current="<?= e($currentPage === 'signup.php' ? 'page' : 'false') ?>"
                           href="<?= e(url('signup.php')) ?>">Sign up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-4 flex-grow-1">
    <div id="alerts">
        <?php if ($flash !== null): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= e($flash['msg']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
    </div>
