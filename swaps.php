<?php
/**
 * My Swaps & Swap History Page
 *
 * Displays incoming swap requests, outgoing swap requests, and completed,
 * declined, or cancelled history. Actions use Fetch POST via assets/js/swaps.js.
 */

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'bootstrap.php';

use App\Core\Auth;
use App\Models\Swap;

Auth::requireLogin();

$userId = (int) Auth::id();
$grouped = Swap::getUserSwapsGrouped($userId);

/**
 * Helper to render one swap card with escaped HTML output.
 */
$renderSwapCard = static function (array $swap, string $mode) use ($userId): string {
    $isOwner = ((int) $swap['owner_id'] === $userId);
    $heading = $mode === 'incoming'
        ? $swap['requester_name'] . ' wants your book'
        : ($mode === 'outgoing'
            ? 'You requested ' . $swap['owner_name'] . "'s book"
            : $swap['requester_name'] . ' ⇄ ' . $swap['owner_name']);

    $badgeClass = statusBadgeClass($swap['status']);
    $dateText = !empty($swap['completed_at'])
        ? 'Completed: ' . $swap['completed_at']
        : 'Created: ' . $swap['created_at'];

    ob_start();
    ?>
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                <h3 class="h6 mb-0"><?= e($heading) ?></h3>
                <span class="badge text-uppercase <?= e($badgeClass) ?>"><?= e($swap['status']) ?></span>
            </div>

            <div class="row g-2 bg-light rounded p-2 mb-3 small">
                <div class="col-md-6">
                    <span class="text-muted d-block">Requested Book (<?= e($swap['owner_name']) ?>):</span>
                    <strong><?= e($swap['requested_book_title']) ?></strong>
                </div>
                <div class="col-md-6">
                    <span class="text-muted d-block">Offered Book (<?= e($swap['requester_name']) ?>):</span>
                    <strong><?= e($swap['offered_book_title']) ?></strong>
                </div>
            </div>

            <?php if (!empty($swap['message'])): ?>
                <p class="small text-secondary mb-2">Note: &ldquo;<?= e($swap['message']) ?>&rdquo;</p>
            <?php endif; ?>

            <?php if ($swap['status'] === 'accepted'): ?>
                <?php
                $myConfirmed = $isOwner ? ((int) $swap['owner_confirmed'] === 1) : ((int) $swap['requester_confirmed'] === 1);
                $partnerConfirmed = $isOwner ? ((int) $swap['requester_confirmed'] === 1) : ((int) $swap['owner_confirmed'] === 1);
                ?>
                <div class="alert alert-info py-2 px-3 small mb-3">
                    Dual Confirmation Status &mdash;
                    <?= $myConfirmed ? 'You: Confirmed' : 'You: Pending confirmation' ?> |
                    <?= $partnerConfirmed ? 'Partner: Confirmed' : 'Partner: Pending confirmation' ?>
                </div>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <span class="small text-muted"><?= e($dateText) ?></span>

                <div class="d-flex flex-wrap gap-2">
                    <?php if ($swap['status'] === 'pending' && $mode === 'incoming'): ?>
                        <button type="button" class="btn btn-success btn-sm js-swap-action"
                                data-swap-id="<?= e((string) $swap['id']) ?>" data-action="accept">Accept</button>
                        <button type="button" class="btn btn-outline-danger btn-sm js-swap-action"
                                data-swap-id="<?= e((string) $swap['id']) ?>" data-action="decline">Decline</button>
                    <?php elseif ($swap['status'] === 'pending' && $mode === 'outgoing'): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm js-swap-action"
                                data-swap-id="<?= e((string) $swap['id']) ?>" data-action="cancel">Cancel Request</button>
                    <?php elseif ($swap['status'] === 'accepted'): ?>
                        <?php $alreadyConfirmed = $isOwner ? ((int) $swap['owner_confirmed'] === 1) : ((int) $swap['requester_confirmed'] === 1); ?>
                        <?php if (!$alreadyConfirmed): ?>
                            <button type="button" class="btn btn-primary btn-sm js-swap-action"
                                    data-swap-id="<?= e((string) $swap['id']) ?>" data-action="confirm">Confirm Completed</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger btn-sm js-swap-action"
                                data-swap-id="<?= e((string) $swap['id']) ?>" data-action="cancel">Cancel Swap</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};

$pageTitle = 'My Swaps';
$pageScripts = ['assets/js/swaps.js'];
require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
?>

<div class="mb-4">
    <h1 class="h3 fw-bold text-primary mb-1">My Swaps &amp; History</h1>
    <p class="text-muted small mb-0">
        Accept or decline offers, confirm exchanges once you meet, and review your full swap history.
        A swap completes only when <strong>both</strong> students confirm.
    </p>
</div>

<div id="swaps-root" data-current-user-id="<?= e((string) $userId) ?>">
    <ul class="nav nav-tabs mb-3" id="swapTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="incoming-tab" data-bs-toggle="tab" data-bs-target="#incoming-pane"
                    type="button" role="tab">
                Incoming <span id="incoming-count" class="badge bg-primary ms-1"><?= count($grouped['incoming']) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="outgoing-tab" data-bs-toggle="tab" data-bs-target="#outgoing-pane"
                    type="button" role="tab">
                Outgoing <span id="outgoing-count" class="badge bg-secondary ms-1"><?= count($grouped['outgoing']) ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history-pane"
                    type="button" role="tab">
                Swap History <span id="history-count" class="badge bg-success ms-1"><?= count($grouped['history']) ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="incoming-pane" role="tabpanel">
            <div id="incoming-list">
                <?php if (empty($grouped['incoming'])): ?>
                    <div class="card border-0 shadow-sm p-4 text-center text-muted">No incoming swap requests right now.</div>
                <?php else: ?>
                    <?php foreach ($grouped['incoming'] as $swap): ?>
                        <?= $renderSwapCard($swap, 'incoming') ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="outgoing-pane" role="tabpanel">
            <div id="outgoing-list">
                <?php if (empty($grouped['outgoing'])): ?>
                    <div class="card border-0 shadow-sm p-4 text-center text-muted">You have no outgoing swap requests.</div>
                <?php else: ?>
                    <?php foreach ($grouped['outgoing'] as $swap): ?>
                        <?= $renderSwapCard($swap, 'outgoing') ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="history-pane" role="tabpanel">
            <div id="history-list">
                <?php if (empty($grouped['history'])): ?>
                    <div class="card border-0 shadow-sm p-4 text-center text-muted">
                        Your completed, declined, and cancelled swaps will appear here.
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped['history'] as $swap): ?>
                        <?= $renderSwapCard($swap, 'history') ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
