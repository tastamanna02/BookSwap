<?php
/**
 * Single Book Details & Swap Request Page
 *
 * Displays full book details. Logged-in students who do not own the book
 * can select one of their own available books and send a swap offer via Fetch.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Models\Book;

$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($bookId === false || $bookId === null || $bookId <= 0) {
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

$book = Book::findByIdWithOwner($bookId);
if ($book === null) {
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

$currentUserId = Auth::id();
$isOwner = ($currentUserId !== null && (int) $book['owner_id'] === $currentUserId);
$myAvailableBooks = ($currentUserId !== null && !$isOwner && $book['status'] === 'available')
    ? Book::findAvailableByOwner($currentUserId)
    : [];

$pageTitle = $book['title'];
$pageScripts = ['assets/js/book-detail.js'];
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="<?= e(url('index.php')) ?>">Browse Books</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($book['title']) ?></li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm p-3">
            <?php if (!empty($book['image'])): ?>
                <img src="<?= e(url('uploads/' . rawurlencode((string) $book['image']))) ?>"
                     class="book-detail-cover"
                     alt="<?= e($book['title']) ?>">
            <?php else: ?>
                <div class="book-cover-box rounded">
                    <div class="book-cover-placeholder fs-5"><?= e($book['title']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm p-4 mb-4">
            <div class="d-flex flex-wrap gap-2 mb-2">
                <span class="badge bg-light text-dark border"><?= e($book['category_name']) ?></span>
                <span class="badge bg-info text-dark"><?= e(ucfirst($book['book_condition'])) ?></span>
                <span class="badge text-uppercase <?= e(statusBadgeClass($book['status'])) ?>">
                    <?= e($book['status']) ?>
                </span>
            </div>

            <h1 class="h3 fw-bold text-primary mb-1"><?= e($book['title']) ?></h1>
            <p class="text-muted mb-3">by <?= e($book['author']) ?></p>

            <p class="mb-3">
                <?= !empty($book['description'])
                    ? nl2br(e($book['description']))
                    : '<span class="text-muted">No extra description provided.</span>' ?>
            </p>

            <hr>
            <p class="small mb-0">
                <strong>Listed by:</strong> <?= e($book['owner_name']) ?>
            </p>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h5 fw-bold mb-3">Request a Book Swap</h2>

            <?php if ($currentUserId === null): ?>
                <p class="text-muted mb-3">
                    Log in with your student account to offer one of your own books in exchange.
                </p>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-primary">Log In to Swap</a>

            <?php elseif ($isOwner): ?>
                <p class="text-muted mb-3">This is your own listing.</p>
                <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-primary btn-sm">Manage My Books</a>
                <?php if ($book['status'] === 'available'): ?>
                    <a href="<?= e(url('book-form.php?id=' . (int) $book['id'])) ?>" class="btn btn-outline-secondary btn-sm ms-2">Edit Book</a>
                <?php endif; ?>

            <?php elseif ($book['status'] !== 'available'): ?>
                <div class="alert alert-warning mb-0">
                    This book is currently <strong><?= e($book['status']) ?></strong> and cannot accept new swap requests.
                </div>

            <?php elseif (empty($myAvailableBooks)): ?>
                <p class="text-muted mb-3">
                    To request a swap, you need at least one available book of your own to offer in exchange.
                </p>
                <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary btn-sm">+ List a Book First</a>

            <?php else: ?>
                <div id="swap-form-alert" class="d-none" role="alert"></div>

                <form id="swap-request-form" data-requested-book-id="<?= e((string) $book['id']) ?>" novalidate>
                    <div class="mb-3">
                        <label for="offered_book_id" class="form-label">Select One of Your Available Books to Offer</label>
                        <select id="offered_book_id" name="offered_book_id" class="form-select" required>
                            <option value="">-- Choose your book --</option>
                            <?php foreach ($myAvailableBooks as $myBook): ?>
                                <option value="<?= e((string) $myBook['id']) ?>">
                                    <?= e($myBook['title'] . ' by ' . $myBook['author'] . ' (' . ucfirst($myBook['book_condition']) . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" id="swap-submit-btn" class="btn btn-primary">Send Swap Request</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
