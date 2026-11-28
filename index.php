<?php
/**
 * Home Page: Browse & Search Available Books
 *
 * Accessible to guests and logged-in users. Renders the hero section, search
 * form (#q, #category), result counter (#count), loading spinner (#spinner),
 * card container (#grid), and pagination list (#pager) driven by browse.js.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

$q = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 100) : '';
$categoryId = isset($_GET['category']) ? (int) $_GET['category'] : (int) ($_GET['category_id'] ?? 0);
$priceMaxInput = isset($_GET['price_max']) && is_string($_GET['price_max']) ? trim($_GET['price_max']) : '';
if ($priceMaxInput !== '' && (!is_numeric($priceMaxInput) || (float) $priceMaxInput < 0)) {
    $priceMaxInput = '';
}
$rawSwapOnly = $_GET['swap_only'] ?? ($_GET['swap-only'] ?? '');
$swapOnly = is_string($rawSwapOnly)
    && in_array(strtolower(trim($rawSwapOnly)), ['1', 'true', 'yes', 'on'], true);
$categories = Book::categories();
$currentUserId = Auth::id() ?? 0;

$title = 'Browse Books';
$pageScript = 'assets/js/browse.js';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<section class="p-3 p-md-5 mb-4 rounded-3 bg-white border shadow-sm">
    <div class="row align-items-center g-3">
        <div class="col-lg-8">
            <h1 class="h2 fw-bold text-primary mb-2">Swap Textbooks &amp; Novels with Fellow Students</h1>
            <p class="text-muted mb-0">
                List books you have finished reading, browse what other students offer, and arrange
                a free book-for-book exchange. A swap completes only when both students confirm.
            </p>
        </div>
        <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
            <?php if ($currentUserId > 0): ?>
                <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary">+ List a Book</a>
                <a href="<?= e(url('my-books.php')) ?>" class="btn btn-outline-primary">My Books</a>
            <?php else: ?>
                <a href="<?= e(url('signup.php')) ?>" class="btn btn-primary">Create Account</a>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-primary">Log In</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<form id="search-form" method="GET" action="<?= e(url('index.php')) ?>" class="card border-0 shadow-sm p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-4">
            <label for="q" class="form-label small fw-semibold mb-1">Search Title or Author</label>
            <input type="text"
                   id="q"
                   name="q"
                   maxlength="100"
                   class="form-control"
                   placeholder="e.g. Clean Code, Mankiw..."
                   value="<?= e($q) ?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="price_max" class="form-label small fw-semibold mb-1">Max price (Tk)</label>
            <input type="number"
                   id="price_max"
                   name="price_max"
                   min="1"
                   step="0.01"
                   class="form-control"
                   placeholder="Any"
                   value="<?= e($priceMaxInput) ?>"
                   <?= $swapOnly ? 'disabled' : '' ?>>
        </div>
        <div class="col-6 col-md-3">
            <label for="category" class="form-label small fw-semibold mb-1">Category</label>
            <select id="category" name="category" class="form-select">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e((string) $cat['id']) ?>" <?= $categoryId === (int) $cat['id'] ? 'selected' : '' ?>>
                        <?= e($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-auto d-flex align-items-center pb-md-2">
            <div class="form-check mb-0">
                <input class="form-check-input"
                       type="checkbox"
                       id="swap_only"
                       name="swap_only"
                       value="1"
                       <?= $swapOnly ? 'checked' : '' ?>>
                <label class="form-check-label small fw-semibold" for="swap_only">
                    Swap only
                </label>
            </div>
        </div>
        <div class="col-6 col-md d-grid">
            <button type="submit" class="btn btn-primary">Search</button>
        </div>
    </div>
</form>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div class="d-flex align-items-center gap-2">
        <h2 class="h5 mb-0">Available Books</h2>
        <div id="spinner" class="spinner-border spinner-border-sm text-primary d-none" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    <span id="count" class="text-muted small"></span>
</div>

<div id="grid" class="row g-3" data-current-user-id="<?= e((string) $currentUserId) ?>"></div>

<nav aria-label="Books pagination" class="mt-4">
    <ul id="pager" class="pagination justify-content-center mb-0"></ul>
</nav>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
