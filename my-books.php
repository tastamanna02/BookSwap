<?php
/**
 * My Books Page
 *
 * Requires login. Displays a responsive Bootstrap table of the logged-in
 * user's listed books with Edit and Fetch-powered Delete actions.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireLogin();

$books = Book::byOwner((int) Auth::id());

$title = 'My Books';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary mb-1">My Books</h1>
        <p class="text-muted small mb-0">Manage the books you have listed for student swaps.</p>
    </div>
    <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary">+ Add New Book</a>
</div>

<div id="my-books-container">
    <?php if (empty($books)): ?>
        <div class="card border-0 shadow-sm p-5 text-center text-muted">
            <h2 class="h5 text-dark mb-2">No books listed yet</h2>
            <p class="mb-3">Share a textbook or novel you have finished so other students can propose a swap.</p>
            <div>
                <a href="<?= e(url('book-form.php')) ?>" class="btn btn-primary btn-sm">Add Your First Book</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Title &amp; Author</th>
                            <th class="d-none d-md-table-cell">Category</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="my-books-tbody">
                        <?php foreach ($books as $book): ?>
                            <?php
                            $isAvailable = ($book['status'] === 'available');
                            $badgeClass = $isAvailable ? 'bg-success' : 'bg-warning text-dark';
                            $badgeLabel = $isAvailable ? 'Available' : 'In a swap';
                            $rowId = 'book-' . (int) $book['id'];
                            ?>
                            <tr id="<?= e($rowId) ?>">
                                <td>
                                    <a href="<?= e(url('book.php?id=' . (int) $book['id'])) ?>"
                                       class="fw-semibold text-decoration-none">
                                        <?= e($book['title']) ?>
                                    </a>
                                    <div class="small text-muted">by <?= e($book['author']) ?></div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?= e($book['category_name']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= e($badgeClass) ?>">
                                        <?= e($badgeLabel) ?>
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="<?= e(url('book-form.php?id=' . (int) $book['id'])) ?>"
                                       class="btn btn-outline-primary btn-sm me-1">
                                        Edit
                                    </a>
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm"
                                            data-delete-book="<?= e((string) $book['id']) ?>"
                                            data-row="<?= e($rowId) ?>">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
