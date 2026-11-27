<?php
/**
 * Home Page: Browse & Search Available Books
 *
 * Accessible to guests, students, and admins. Supports initial server-side
 * filtering via GET and live filtering via Fetch API (assets/js/browse.js).
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$query = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$categoryId = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;
$condition = isset($_GET['condition']) && is_string($_GET['condition']) ? trim($_GET['condition']) : '';

$categories = Book::categories();
$books = Book::searchAvailable($query, $categoryId, $condition);
$currentUserId = Auth::id() ?? 0;

$title = 'Browse Books';
$pageScript = 'assets/js/browse.js';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<section class="p-4 p-md-5 mb-4 rounded-3 bg-white border shadow-sm">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h1 class="h2 fw-bold text-primary mb-2">Swap Textbooks &amp; Novels with Fellow Students</h1>
            <p class="text-muted mb-0">
                List books you have finished reading, browse what other students offer, and arrange
                a free book-for-book exchange. A swap completes only when both students confirm.
            </p>
        </div>
        <div class="col-lg-4 text-lg-end">
            <?php if ($currentUserId > 0): ?>
                <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary">+ List a Book</a>
                <a href="<?= e(url('swaps.php')) ?>" class="btn btn-outline-primary ms-2">My Swaps</a>
            <?php else: ?>
                <a href="<?= e(url('signup.php')) ?>" class="btn btn-primary">Create Student Account</a>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-primary ms-2">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<form id="search-form" method="GET" action="<?= e(url('index.php')) ?>" class="card border-0 shadow-sm p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label for="search-query" class="form-label small fw-semibold mb-1">Search Title or Author</label>
            <input type="text"
                   id="search-query"
                   name="q"
                   maxlength="100"
                   class="form-control"
                   placeholder="e.g. Clean Code, Mankiw..."
                   value="<?= e($query) ?>">
        </div>
        <div class="col-md-3">
            <label for="search-category" class="form-label small fw-semibold mb-1">Category</label>
            <select id="search-category" name="category_id" class="form-select">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat['id']) ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <label for="search-condition" class="form-label small fw-semibold mb-1">Condition</label>
            <select id="search-condition" name="condition" class="form-select">
                <option value="">Any Condition</option>
                <?php foreach (Book::CONDITIONS as $condValue => $condLabel): ?>
                    <option value="<?= e($condValue) ?>" <?= $condition === $condValue ? 'selected' : '' ?>>
                        <?= e($condLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-primary flex-grow-1">Search</button>
            <button type="button" id="reset-filters" class="btn btn-outline-secondary">Reset</button>
        </div>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h5 mb-0">Available Books</h2>
    <span id="books-count" class="text-muted small">
        <?= count($books) === 1 ? '1 book available' : count($books) . ' books available' ?>
    </span>
</div>

<div id="books-grid" class="row g-3" data-current-user-id="<?= e((string) $currentUserId) ?>">
    <?php if (empty($books)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 text-center text-muted">
                <p class="mb-2 fw-semibold">No matching books found.</p>
                <p class="mb-0 small">Try clearing your filters or check back once students list more books.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($books as $book): ?>
            <div class="col-sm-6 col-lg-4 col-xl-3">
                <div class="card h-100 shadow-sm book-card">
                    <div class="book-cover-box">
                        <?php if (!empty($book['image'])): ?>
                            <img src="<?= e(url('uploads/' . rawurlencode((string) $book['image']))) ?>"
                                 class="book-cover-img"
                                 alt="<?= e($book['title']) ?>">
                        <?php else: ?>
                            <div class="book-cover-placeholder"><?= e($book['title']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            <span class="badge bg-light text-dark border"><?= e($book['category_name']) ?></span>
                            <span class="badge bg-info text-dark"><?= e(Book::CONDITIONS[$book['book_condition']] ?? ucfirst($book['book_condition'])) ?></span>
                            <?php if ($currentUserId > 0 && (int) $book['owner_id'] === $currentUserId): ?>
                                <span class="badge bg-secondary">Your Listing</span>
                            <?php endif; ?>
                        </div>
                        <h2 class="h6 card-title mb-1"><?= e($book['title']) ?></h2>
                        <p class="text-muted small mb-2">by <?= e($book['author']) ?></p>
                        <p class="small text-secondary mb-3 mt-auto">
                            Listed by <?= e($book['owner_name']) ?>
                        </p>
                        <a href="<?= e(url('book.php?id=' . (int) $book['id'])) ?>"
                           class="btn btn-outline-primary btn-sm w-100">
                            View Details
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
