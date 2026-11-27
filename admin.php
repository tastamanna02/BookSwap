<?php
/**
 * Admin Dashboard Page
 *
 * Strictly protected by Auth::requireAdmin() on the server. Displays platform
 * metrics, all listed books with admin delete capability, and recent swaps.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Models\Book;
use App\Models\Swap;
use App\Models\User;

Auth::requireAdmin();

$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$totalUsers = User::countAll();
$bookStats = Book::getStatsForAdmin();
$swapStats = Swap::getStatsForAdmin();
$allBooks = Book::listAllForAdmin($search);
$recentSwaps = Swap::listRecentForAdmin(15);

$pageTitle = 'Admin Dashboard';
$pageScripts = ['assets/js/admin.js'];
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1">Admin Dashboard</h1>
    <p class="text-muted small mb-0">Monitor student activity and moderate any book listing.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 stat-card">
            <span class="text-muted small">Registered Users</span>
            <span class="h4 fw-bold mb-0"><?= e((string) $totalUsers) ?></span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 stat-card">
            <span class="text-muted small">Total Books (<?= e((string) $bookStats['available_books']) ?> available)</span>
            <span class="h4 fw-bold mb-0"><?= e((string) $bookStats['total_books']) ?></span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 stat-card">
            <span class="text-muted small">Open Swaps (Pending / Accepted)</span>
            <span class="h4 fw-bold mb-0">
                <?= e((string) ($swapStats['pending_swaps'] + $swapStats['accepted_swaps'])) ?>
            </span>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm p-3 stat-card">
            <span class="text-muted small">Completed Swaps</span>
            <span class="h4 fw-bold mb-0"><?= e((string) $swapStats['completed_swaps']) ?></span>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm p-4 mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="h5 fw-bold mb-0">All Book Listings</h2>
        <form method="GET" action="<?= e(url('admin.php')) ?>" class="d-flex gap-2">
            <input type="text"
                   name="q"
                   maxlength="100"
                   class="form-control form-control-sm"
                   placeholder="Filter title, author, owner..."
                   value="<?= e($search) ?>">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(url('admin.php')) ?>" class="btn btn-outline-secondary btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Title &amp; Author</th>
                    <th>Category</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody id="admin-books-body">
                <?php if (empty($allBooks)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">No books found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allBooks as $book): ?>
                        <tr>
                            <td>#<?= e((string) $book['id']) ?></td>
                            <td>
                                <a href="<?= e(url('book.php?id=' . (int) $book['id'])) ?>" class="fw-semibold text-decoration-none">
                                    <?= e($book['title']) ?>
                                </a>
                                <div class="text-muted"><?= e($book['author']) ?> &bull; <?= e($book['book_condition']) ?></div>
                            </td>
                            <td><?= e($book['category']) ?></td>
                            <td>
                                <?= e($book['owner_name']) ?>
                                <div class="text-muted"><?= e($book['owner_email']) ?></div>
                            </td>
                            <td>
                                <span class="badge text-uppercase <?= e(statusBadgeClass($book['status'])) ?>">
                                    <?= e($book['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button type="button"
                                        class="btn btn-outline-danger btn-sm js-admin-delete-book"
                                        data-book-id="<?= e((string) $book['id']) ?>"
                                        data-book-title="<?= e($book['title']) ?>">
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

<div class="card border-0 shadow-sm p-4">
    <h2 class="h5 fw-bold mb-3">Recent Swaps Overview</h2>
    <div class="table-responsive">
        <table class="table table-sm align-middle small mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Requester</th>
                    <th>Offered Book</th>
                    <th>Owner</th>
                    <th>Requested Book</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentSwaps)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-3">No swap requests yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentSwaps as $swap): ?>
                        <tr>
                            <td>#<?= e((string) $swap['id']) ?></td>
                            <td><?= e($swap['requester_name']) ?></td>
                            <td><?= e($swap['offered_book_title']) ?></td>
                            <td><?= e($swap['owner_name']) ?></td>
                            <td><?= e($swap['requested_book_title']) ?></td>
                            <td>
                                <span class="badge text-uppercase <?= e(statusBadgeClass($swap['status'])) ?>">
                                    <?= e($swap['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
