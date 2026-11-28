<?php
/**
 * Single Book Details & Swap Request Page (book.php?id=)
 *
 * Shows 404 if missing. Displays cover/placeholder, title, author, category,
 * condition, description, owner name, and listing date, plus role-aware actions.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$bookId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($bookId === false || $bookId === null || $bookId <= 0) {
    show_404();
}

$book = Book::find($bookId);
if ($book === null) {
    show_404();
}

$currentUserId = Auth::id();
$isOwner = ($currentUserId !== null && (int) $book['owner_id'] === $currentUserId);
$myAvailableBooks = ($currentUserId !== null && !$isOwner && $book['status'] === 'available')
    ? Book::byOwner($currentUserId, true)
    : [];

$listedDate = date('M j, Y', strtotime((string) $book['created_at']));

$title = $book['title'];
$pageScript = 'assets/js/book.js';
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
                <span class="badge bg-info text-dark">
                    <?= e(Book::CONDITIONS[$book['book_condition']] ?? ucfirst($book['book_condition'])) ?>
                </span>
                <span class="badge <?= e($book['status'] === 'available' ? 'bg-success' : 'bg-warning text-dark') ?>">
                    <?= e($book['status'] === 'available' ? 'Available' : 'In a swap') ?>
                </span>
            </div>

            <h1 class="h3 fw-bold text-primary mb-1"><?= e($book['title']) ?></h1>
            <p class="text-muted mb-2">by <?= e($book['author']) ?></p>

            <?php $formattedPrice = Book::formatPrice($book['price'] ?? null); ?>
            <div class="mb-3">
                <?php if ($formattedPrice !== null): ?>
                    <?php [$priceLabel, $priceBadgeClass] = Book::priceBadge($book['price_type'] ?? 'fixed'); ?>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="h4 fw-bold text-primary mb-0">Tk <?= e($formattedPrice) ?></span>
                        <span class="badge fs-6 <?= e($priceBadgeClass) ?>">
                            <?= e($priceLabel) ?>
                        </span>
                    </div>
                    <?php if (($book['price_type'] ?? 'fixed') === 'bargaining'): ?>
                        <div class="small text-muted mt-1">Price is negotiable, message the owner</div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge fs-6 bg-secondary">Swap only</span>
                <?php endif; ?>
            </div>

            <p class="mb-3">
                <?= !empty($book['description'])
                    ? nl2br(e($book['description']))
                    : '<span class="text-muted">No extra description provided.</span>' ?>
            </p>

            <hr>
            <div class="d-flex flex-wrap justify-content-between small text-muted gap-2">
                <span><strong>Owner:</strong> <?= e($book['owner_name']) ?></span>
                <span><strong>Listed:</strong> <?= e($listedDate) ?></span>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-4">
            <h2 class="h5 fw-bold mb-3">Swap Options</h2>

            <?php if ($isOwner): ?>
                <p class="text-muted mb-3">This is your own listing.</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= e(url('book-form.php?id=' . (int) $book['id'])) ?>" class="btn btn-primary btn-sm">Edit Book</a>
                    <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-secondary btn-sm">My Books</a>
                </div>

            <?php elseif ($currentUserId === null): ?>
                <p class="text-muted mb-3">
                    Please <a href="<?= e(url('login.php')) ?>">log in to request a swap</a> for this book.
                </p>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-primary btn-sm">Log In</a>

            <?php elseif ($book['status'] !== 'available'): ?>
                <div class="alert alert-warning mb-0">
                    This book is currently reserved in an active swap and cannot accept new requests.
                </div>

            <?php elseif (empty($myAvailableBooks)): ?>
                <p class="text-muted mb-3">
                    You need at least one available book of your own to offer in exchange.
                </p>
                <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary btn-sm">+ List a Book</a>

            <?php else: ?>
                <div id="swap-form-alert" class="d-none" role="alert"></div>

                <form id="swap-form" data-book-id="<?= e((string) $book['id']) ?>" novalidate>
                    <div class="mb-3">
                        <label for="offered_book_id" class="form-label">Select One of Your Available Books to Offer</label>
                        <select id="offered_book_id" name="offered_book_id" class="form-select" required>
                            <option value="">-- Choose your book --</option>
                            <?php foreach ($myAvailableBooks as $myBook): ?>
                                <option value="<?= e((string) $myBook['id']) ?>">
                                    <?= e($myBook['title'] . ' by ' . $myBook['author'] . ' (' . (Book::CONDITIONS[$myBook['book_condition']] ?? ucfirst($myBook['book_condition'])) . ')') ?>
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
