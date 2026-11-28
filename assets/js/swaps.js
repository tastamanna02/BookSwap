/**
 * Swaps Page Script (swaps.js)
 *
 * Shows any post-reload swap status message saved in sessionStorage, and
 * handles [data-swap][data-do] action buttons via Fetch POST to api/swap.php.
 */
(function () {
    'use strict';

    const FLASH_KEY = 'bookswap_swap_flash';

    document.addEventListener('DOMContentLoaded', function () {
        const pendingMsg = window.sessionStorage.getItem(FLASH_KEY);
        if (pendingMsg) {
            window.sessionStorage.removeItem(FLASH_KEY);
            showAlert(pendingMsg, 'success');
        }
    });

    document.addEventListener('click', async function (event) {
        const btn = event.target.closest('[data-swap][data-do]');
        if (!btn) {
            return;
        }

        const swapId = Number(btn.getAttribute('data-swap') || '0');
        const action = btn.getAttribute('data-do') || '';
        if (swapId <= 0 || !action) {
            return;
        }

        const ask = btn.getAttribute('data-ask');
        if (ask && !window.confirm(ask)) {
            return;
        }

        setLoading(btn, true);

        const result = await api('api/swap.php', {
            method: 'POST',
            data: { swap_id: swapId, action: action },
        });

        if (result && result.ok) {
            window.sessionStorage.setItem(FLASH_KEY, result.message || 'Swap updated.');
            window.location.reload();
        } else {
            setLoading(btn, false);
            showAlert((result && result.message) || 'Something went wrong, try again.', 'danger');
        }
    });
})();
