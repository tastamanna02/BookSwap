/**
 * Swaps & Swap History Page Script (swaps.php)
 *
 * Submits swap actions (accept, decline, cancel, confirm) via Fetch POST,
 * then fetches updated swap lists from api/swaps.php and re-renders DOM safely.
 */
(function () {
    'use strict';

    const root = document.getElementById('swaps-root');
    if (!root) {
        return;
    }

    const currentUserId = Number(root.getAttribute('data-current-user-id') || '0');
    const incomingList = document.getElementById('incoming-list');
    const outgoingList = document.getElementById('outgoing-list');
    const historyList = document.getElementById('history-list');
    const incomingBadge = document.getElementById('incoming-count');
    const outgoingBadge = document.getElementById('outgoing-count');
    const historyBadge = document.getElementById('history-count');

    function makeActionButton(swapId, action, label, className) {
        const btn = BookSwap.createEl('button', className, label);
        btn.type = 'button';
        btn.classList.add('js-swap-action');
        btn.setAttribute('data-swap-id', String(swapId));
        btn.setAttribute('data-action', action);
        return btn;
    }

    function buildSwapCard(swap, mode) {
        const card = BookSwap.createEl('div', 'card shadow-sm mb-3');
        const body = BookSwap.createEl('div', 'card-body');

        const header = BookSwap.createEl('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-2');
        const headingText = mode === 'incoming'
            ? swap.requester_name + ' wants your book'
            : (mode === 'outgoing'
                ? 'You requested ' + swap.owner_name + "'s book"
                : swap.requester_name + ' \u21C4 ' + swap.owner_name);
        header.appendChild(BookSwap.createEl('h3', 'h6 mb-0', headingText));

        const badge = BookSwap.createEl(
            'span',
            'badge text-uppercase ' + BookSwap.statusBadgeClass(swap.status),
            swap.status
        );
        header.appendChild(badge);
        body.appendChild(header);

        // Book exchange summary
        const exchangeRow = BookSwap.createEl('div', 'row g-2 bg-light rounded p-2 mb-3 small');
        const colLeft = BookSwap.createEl('div', 'col-md-6');
        colLeft.appendChild(BookSwap.createEl('span', 'text-muted d-block', 'Requested Book (' + swap.owner_name + '):'));
        colLeft.appendChild(BookSwap.createEl('strong', '', swap.requested_book_title));

        const colRight = BookSwap.createEl('div', 'col-md-6');
        colRight.appendChild(BookSwap.createEl('span', 'text-muted d-block', 'Offered Book (' + swap.requester_name + '):'));
        colRight.appendChild(BookSwap.createEl('strong', '', swap.offered_book_title));

        exchangeRow.appendChild(colLeft);
        exchangeRow.appendChild(colRight);
        body.appendChild(exchangeRow);

        // Dual confirmation status for accepted swaps
        if (swap.status === 'accepted') {
            const isOwner = Number(swap.owner_id) === currentUserId;
            const myConfirmed = isOwner ? Number(swap.owner_confirmed) === 1 : Number(swap.requester_confirmed) === 1;
            const partnerConfirmed = isOwner ? Number(swap.requester_confirmed) === 1 : Number(swap.owner_confirmed) === 1;

            const statusBox = BookSwap.createEl('div', 'alert alert-info py-2 px-3 small mb-3');
            const myText = myConfirmed ? 'You: Confirmed' : 'You: Pending confirmation';
            const partnerText = partnerConfirmed ? 'Partner: Confirmed' : 'Partner: Pending confirmation';
            statusBox.textContent = 'Dual Confirmation Status \u2014 ' + myText + ' | ' + partnerText;
            body.appendChild(statusBox);
        }

        // Timestamp info and action buttons
        const dateText = swap.completed_at
            ? 'Completed: ' + swap.completed_at
            : 'Created: ' + swap.created_at;
        const footer = BookSwap.createEl('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2');
        footer.appendChild(BookSwap.createEl('span', 'small text-muted', dateText));

        const actions = BookSwap.createEl('div', 'd-flex flex-wrap gap-2');
        if (swap.status === 'pending' && mode === 'incoming') {
            actions.appendChild(makeActionButton(swap.id, 'accept', 'Accept', 'btn btn-success btn-sm'));
            actions.appendChild(makeActionButton(swap.id, 'decline', 'Decline', 'btn btn-outline-danger btn-sm'));
        } else if (swap.status === 'pending' && mode === 'outgoing') {
            actions.appendChild(makeActionButton(swap.id, 'cancel', 'Cancel Request', 'btn btn-outline-secondary btn-sm'));
        } else if (swap.status === 'accepted') {
            const isOwner = Number(swap.owner_id) === currentUserId;
            const alreadyConfirmed = isOwner
                ? Number(swap.owner_confirmed) === 1
                : Number(swap.requester_confirmed) === 1;

            if (!alreadyConfirmed) {
                actions.appendChild(makeActionButton(swap.id, 'confirm', 'Confirm Completed', 'btn btn-primary btn-sm'));
            }
            actions.appendChild(makeActionButton(swap.id, 'cancel', 'Cancel Swap', 'btn btn-outline-danger btn-sm'));
        }

        footer.appendChild(actions);
        body.appendChild(footer);
        card.appendChild(body);
        return card;
    }

    function renderSection(container, items, mode, emptyMessage) {
        if (!container) {
            return;
        }
        container.replaceChildren();
        if (!items || items.length === 0) {
            const empty = BookSwap.createEl('div', 'card border-0 shadow-sm p-4 text-center text-muted', emptyMessage);
            container.appendChild(empty);
            return;
        }
        items.forEach(function (swap) {
            container.appendChild(buildSwapCard(swap, mode));
        });
    }

    async function refreshSwaps() {
        const data = await BookSwap.fetchJson('api/swaps.php');
        if (!data || !data.ok) {
            BookSwap.showAlert('global-alert', (data && data.message) || 'Something went wrong, try again.', 'danger');
            return;
        }

        const incoming = Array.isArray(data.incoming) ? data.incoming : [];
        const outgoing = Array.isArray(data.outgoing) ? data.outgoing : [];
        const history = Array.isArray(data.history) ? data.history : [];

        if (incomingBadge) incomingBadge.textContent = String(incoming.length);
        if (outgoingBadge) outgoingBadge.textContent = String(outgoing.length);
        if (historyBadge) historyBadge.textContent = String(history.length);

        renderSection(incomingList, incoming, 'incoming', 'No incoming swap requests right now.');
        renderSection(outgoingList, outgoing, 'outgoing', 'You have no outgoing swap requests.');
        renderSection(historyList, history, 'history', 'Your completed, declined, and cancelled swaps will appear here.');
    }

    root.addEventListener('click', async function (event) {
        const btn = event.target.closest('.js-swap-action');
        if (!btn) {
            return;
        }

        const swapId = Number(btn.getAttribute('data-swap-id') || '0');
        const action = btn.getAttribute('data-action') || '';
        if (swapId <= 0 || !action) {
            return;
        }

        if (action === 'decline' || action === 'cancel') {
            const verb = action === 'decline' ? 'decline' : 'cancel';
            if (!window.confirm('Are you sure you want to ' + verb + ' this swap?')) {
                return;
            }
        }

        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = 'Processing...';

        const result = await BookSwap.fetchJson('api/swap-action.php', {
            method: 'POST',
            body: { swap_id: swapId, action: action },
        });

        if (result && result.ok) {
            BookSwap.showAlert('global-alert', result.message || 'Swap updated.', 'success');
            await refreshSwaps();
        } else {
            btn.disabled = false;
            btn.textContent = originalText;
            BookSwap.showAlert('global-alert', (result && result.message) || 'Something went wrong, try again.', 'danger');
        }
    });
})();
