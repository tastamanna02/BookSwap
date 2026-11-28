<?php
/**
 * Admin Dashboard Page
 *
 * Protected server-side by Auth::requireAdmin(). Displays 7 platform stat cards,
 * the 10 most recent books with [data-delete-book] moderation, and all users.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireAdmin();

$totalUsers = (int) User::count();
$bookCounts = Book::counts();
$swapStats = Swap::stats();

$statCards = [
    ['label' => 'Users',               'value' => $totalUsers],
    ['label' => 'Books Listed',        'value' => (int) ($bookCounts['total'] ?? 0)],
    ['label' => 'Available Books',     'value' => (int) ($bookCounts['available'] ?? 0)],
    ['label' => 'Pending Swaps',       'value' => (int) ($swapStats['pending'] ?? 0)],
    ['label' => 'Active Swaps',        'value' => (int) ($swapStats['accepted'] ?? 0)],
    ['label' => 'Completed Swaps',     'value' => (int) ($swapStats['completed'] ?? 0)],
    ['label' => 'Completed This Week', 'value' => (int) ($swapStats['completed_7d'] ?? 0)],
];

$recentBooks = Book::recent(10, false);
$users = User::all();

$title = 'Admin Dashboard';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1">Admin Dashboard</h1>
    <p class="text-muted small mb-0">Platform metrics, recent book moderation, and registered accounts.</p>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($statCards as $card): ?>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="card border-0 shadow-sm p-3 stat-card h-100">
                <span class="text-muted small"><?= e($card['label']) ?></span>
                <span class="h4 fw-bold mb-0"><?= e((string) $card['value']) ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
    <h2 class="h5 fw-bold mb-3">10 Most Recent Books</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th class="d-none d-md-table-cell">ID</th>
                    <th>Title &amp; Author</th>
                    <th class="d-none d-md-table-cell">Category</th>
                    <th class="d-none d-md-table-cell">Owner</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody id="admin-recent-tbody">
                <?php if (empty($recentBooks)): ?>
                    <tr id="admin-recent-empty">
                        <td colspan="6" class="text-center text-muted py-3">No books listed yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentBooks as $book): ?>
                        <?php $rowId = 'admin-book-' . (int) $book['id']; ?>
                        <tr id="<?= e($rowId) ?>">
                            <td class="d-none d-md-table-cell">#<?= e((string) $book['id']) ?></td>
                            <td class="text-break">
                                <a href="<?= e(url('book.php?id=' . (int) $book['id'])) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($book['title']) ?>
                                </a>
                                <div class="text-muted">
                                    <?= e($book['author']) ?> &bull; <?= e(Book::CONDITIONS[$book['book_condition']] ?? ucfirst($book['book_condition'])) ?>
                                    <span class="d-md-none"> &bull; <?= e($book['owner_name']) ?></span>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell"><?= e($book['category_name']) ?></td>
                            <td class="d-none d-md-table-cell text-break"><?= e($book['owner_name']) ?></td>
                            <td>
                                <span class="badge text-uppercase <?= e(statusBadgeClass($book['status'])) ?>">
                                    <?= e($book['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm"
                                        data-delete-book="<?= e((string) $book['id']) ?>"
                                        data-row="<?= e($rowId) ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card border-0 shadow-sm p-3 p-md-4">
    <h2 class="h5 fw-bold mb-3">All Users</h2>
    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th class="d-none d-sm-table-cell">Email</th>
                    <th>Role</th>
                    <th>Books</th>
                    <th class="d-none d-md-table-cell">Joined</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-3">No registered users yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold text-break">
                                <?= e($u['name']) ?>
                                <div class="small fw-normal text-muted text-break d-sm-none"><?= e($u['email']) ?></div>
                            </td>
                            <td class="d-none d-sm-table-cell text-break"><?= e($u['email']) ?></td>
                            <td>
                                <span class="badge text-uppercase <?= e($u['role'] === 'admin' ? 'bg-warning text-dark' : 'bg-secondary') ?>">
                                    <?= e($u['role']) ?>
                                </span>
                            </td>
                            <td><?= e((string) ((int) $u['book_count'])) ?></td>
                            <td class="d-none d-md-table-cell"><?= e(date('M j, Y', strtotime((string) $u['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
