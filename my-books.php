<?php
/**
 * My Books Page
 *
 * Shows all books listed by the logged-in student (available, reserved, swapped).
 * Owners can add, edit (available only), or delete via Fetch POST.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Models\Book;

Auth::requireLogin();

$books = Book::findByUser((int) Auth::id());

$pageTitle = 'My Books';
$pageScripts = ['assets/js/my-books.js'];
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary mb-1">My Books</h1>
        <p class="text-muted small mb-0">Manage the books you have listed for student swaps.</p>
    </div>
    <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary">+ Add New Book</a>
</div>

<div id="my-books-list" class="row g-3">
    <?php if (empty($books)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 text-center text-muted">
                <p class="mb-3">You have not listed any books yet.</p>
                <div>
                    <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary btn-sm">Add Your First Book</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($books as $book): ?>
            <div class="col-sm-6 col-lg-4 js-book-item">
                <div class="card h-100 shadow-sm book-card">
                    <div class="book-cover-box">
                        <?php if (!empty($book['cover_image'])): ?>
                            <img src="<?= e(url('uploads/' . rawurlencode((string) $book['cover_image']))) ?>"
                                 class="book-cover-img"
                                 alt="<?= e($book['title']) ?>">
                        <?php else: ?>
                            <div class="book-cover-placeholder"><?= e($book['title']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-1 mb-2">
                            <span class="badge bg-light text-dark border"><?= e($book['category']) ?></span>
                            <span class="badge text-uppercase <?= e(statusBadgeClass($book['status'])) ?>">
                                <?= e($book['status']) ?>
                            </span>
                        </div>
                        <h2 class="h6 card-title mb-1"><?= e($book['title']) ?></h2>
                        <p class="text-muted small mb-3">
                            by <?= e($book['author']) ?> &bull; <?= e($book['book_condition']) ?>
                        </p>

                        <div class="mt-auto">
                            <div class="d-flex flex-wrap gap-2">
                                <a href="<?= e(url('book.php?id=' . (int) $book['id'])) ?>"
                                   class="btn btn-outline-secondary btn-sm">View</a>

                                <?php if ($book['status'] === 'available'): ?>
                                    <a href="<?= e(url('book-form.php?id=' . (int) $book['id'])) ?>"
                                       class="btn btn-outline-primary btn-sm">Edit</a>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm js-delete-my-book"
                                            data-book-id="<?= e((string) $book['id']) ?>"
                                            data-book-title="<?= e($book['title']) ?>">
                                        Delete
                                    </button>
                                <?php endif; ?>
                            </div>
                            <?php if ($book['status'] !== 'available'): ?>
                                <p class="small text-muted mt-2 mb-0">
                                    <?= $book['status'] === 'reserved'
                                        ? 'Locked while an accepted swap is in progress.'
                                        : 'Completed swap record (preserved for swap history).' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
