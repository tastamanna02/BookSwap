<?php
/**
 * My Swaps & Swap History Page
 *
 * Loads Swap::forUser() and splits swaps into four groups:
 * incoming (pending owner), outgoing (pending requester), active (accepted),
 * and history (completed, declined, cancelled). Actions are handled by swaps.js.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

Auth::requireLogin();

$userId = (int) Auth::id();
$allSwaps = Swap::forUser($userId);

$groups = [
    'incoming' => [],
    'outgoing' => [],
    'active'   => [],
    'history'  => [],
];

foreach ($allSwaps as $swap) {
    $status = (string) $swap['status'];

    if ($status === 'pending') {
        if ((int) $swap['owner_id'] === $userId) {
            $groups['incoming'][] = $swap;
        } else {
            $groups['outgoing'][] = $swap;
        }
    } elseif ($status === 'accepted') {
        $groups['active'][] = $swap;
    } else {
        $groups['history'][] = $swap;
    }
}

/**
 * Render a single swap card cleanly with all user data escaped via e().
 */
$renderSwapCard = static function (array $swap, string $group) use ($userId): string {
    $isOwner = ((int) $swap['owner_id'] === $userId);
    $giveBook = $isOwner ? (string) $swap['requested_book_title'] : (string) $swap['offered_book_title'];
    $getBook = $isOwner ? (string) $swap['offered_book_title'] : (string) $swap['requested_book_title'];
    $otherName = $isOwner ? (string) $swap['requester_name'] : (string) $swap['owner_name'];
    $rawDate = !empty($swap['completed_at']) ? (string) $swap['completed_at'] : (string) $swap['created_at'];
    $dateLabel = !empty($swap['completed_at']) ? 'Completed' : 'Requested';
    $formattedDate = date('M j, Y', strtotime($rawDate));

    $swapId = (int) $swap['id'];
    $status = (string) $swap['status'];
    $iConfirmed = $isOwner ? ((int) $swap['owner_confirmed'] === 1) : ((int) $swap['requester_confirmed'] === 1);
    $theyConfirmed = $isOwner ? ((int) $swap['requester_confirmed'] === 1) : ((int) $swap['owner_confirmed'] === 1);

    ob_start();
    ?>
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <h3 class="h6 fw-bold mb-0">
                    You give <span class="text-primary"><?= e($giveBook) ?></span>
                    &harr;
                    you get <span class="text-success"><?= e($getBook) ?></span>
                </h3>
                <?php if ($group === 'history'): ?>
                    <span class="badge text-uppercase <?= e(statusBadgeClass($status)) ?>">
                        <?= e($status) ?>
                    </span>
                <?php else: ?>
                    <span class="badge text-uppercase <?= e(statusBadgeClass($status)) ?>">
                        <?= e($status) ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="small text-muted mb-3">
                <span><strong>With:</strong> <?= e($otherName) ?></span>
                <span class="mx-2">&bull;</span>
                <span><strong><?= e($dateLabel) ?>:</strong> <?= e($formattedDate) ?></span>
            </div>

            <?php if ($group === 'active'): ?>
                <div class="alert alert-info py-2 px-3 small mb-3 d-flex flex-wrap gap-3">
                    <span>
                        <?= $iConfirmed ? '&#10004;' : '&#9203;' ?>
                        <strong>You:</strong> <?= $iConfirmed ? 'Confirmed' : 'Pending' ?>
                    </span>
                    <span>
                        <?= $theyConfirmed ? '&#10004;' : '&#9203;' ?>
                        <strong><?= e($otherName) ?>:</strong> <?= $theyConfirmed ? 'Confirmed' : 'Pending' ?>
                    </span>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap gap-2">
                <?php if ($group === 'incoming'): ?>
                    <button type="button"
                            class="btn btn-success btn-sm"
                            data-swap="<?= e((string) $swapId) ?>"
                            data-do="accept">
                        Accept
                    </button>
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            data-swap="<?= e((string) $swapId) ?>"
                            data-do="decline"
                            data-ask="Decline this swap request?">
                        Decline
                    </button>
                <?php elseif ($group === 'outgoing'): ?>
                    <button type="button"
                            class="btn btn-outline-secondary btn-sm"
                            data-swap="<?= e((string) $swapId) ?>"
                            data-do="cancel"
                            data-ask="Cancel this pending swap request?">
                        Cancel
                    </button>
                <?php elseif ($group === 'active'): ?>
                    <?php if (!$iConfirmed): ?>
                        <button type="button"
                                class="btn btn-primary btn-sm"
                                data-swap="<?= e((string) $swapId) ?>"
                                data-do="confirm"
                                data-ask="Confirm that both books have been exchanged?">
                            Confirm swap done
                        </button>
                    <?php endif; ?>
                    <button type="button"
                            class="btn btn-outline-danger btn-sm"
                            data-swap="<?= e((string) $swapId) ?>"
                            data-do="cancel"
                            data-ask="Cancel this accepted swap? Both books will become available again.">
                        Cancel
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

$title = 'My Swaps';
$pageScript = 'assets/js/swaps.js';
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 fw-bold text-primary mb-1">My Swaps</h1>
        <p class="text-muted small mb-0">
            Manage incoming requests, track outgoing proposals, and confirm completed exchanges.
        </p>
    </div>
    <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-primary btn-sm">Browse Books</a>
</div>

<?php if (empty($allSwaps)): ?>
    <div class="card border-0 shadow-sm p-5 text-center text-muted">
        <h2 class="h5 text-dark mb-2">No swaps yet</h2>
        <p class="mb-3">Browse books listed by other students and propose your first book-for-book exchange.</p>
        <div>
            <a href="<?= e(url('index.php')) ?>" class="btn btn-primary btn-sm">Browse Available Books</a>
        </div>
    </div>
<?php else: ?>
    <div id="swaps-root" class="row g-4">
        <div class="col-12">
            <h2 class="h5 fw-bold mb-3">
                Incoming Requests
                <span class="badge bg-danger ms-1"><?= e((string) count($groups['incoming'])) ?></span>
            </h2>
            <?php if (empty($groups['incoming'])): ?>
                <div class="card border-0 shadow-sm p-3 text-muted small">No incoming pending requests.</div>
            <?php else: ?>
                <?php foreach ($groups['incoming'] as $swap): ?>
                    <?= $renderSwapCard($swap, 'incoming') ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <h2 class="h5 fw-bold mb-3">
                Active Swaps
                <span class="badge bg-primary ms-1"><?= e((string) count($groups['active'])) ?></span>
            </h2>
            <?php if (empty($groups['active'])): ?>
                <div class="card border-0 shadow-sm p-3 text-muted small">No accepted swaps awaiting confirmation.</div>
            <?php else: ?>
                <?php foreach ($groups['active'] as $swap): ?>
                    <?= $renderSwapCard($swap, 'active') ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <h2 class="h5 fw-bold mb-3">
                Outgoing Requests
                <span class="badge bg-secondary ms-1"><?= e((string) count($groups['outgoing'])) ?></span>
            </h2>
            <?php if (empty($groups['outgoing'])): ?>
                <div class="card border-0 shadow-sm p-3 text-muted small">You have no pending outgoing requests.</div>
            <?php else: ?>
                <?php foreach ($groups['outgoing'] as $swap): ?>
                    <?= $renderSwapCard($swap, 'outgoing') ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <h2 class="h5 fw-bold mb-3">
                Swap History
                <span class="badge bg-success ms-1"><?= e((string) count($groups['history'])) ?></span>
            </h2>
            <?php if (empty($groups['history'])): ?>
                <div class="card border-0 shadow-sm p-3 text-muted small">Completed, declined, and cancelled swaps will appear here.</div>
            <?php else: ?>
                <?php foreach ($groups['history'] as $swap): ?>
                    <?= $renderSwapCard($swap, 'history') ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
