/**
 * Book Detail Swap Proposal Script (assets/js/book.js)
 *
 * Submits #swap-form via Fetch POST to api/swap.php with { action: 'request',
 * requested_book_id, offered_book_id }, shows a loading button, displays the
 * response alert, and redirects to swaps.php after ~1 second on success.
 */
(function () {
    'use strict';

    const swapForm = document.getElementById('swap-form');
    if (!swapForm) {
        return;
    }

    const offeredSelect = document.getElementById('offered_book_id');
    const submitBtn = swapForm.querySelector('button[type="submit"]');
    const alertBox = document.getElementById('swap-form-alert');

    swapForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        BookSwap.hideAlert(alertBox);

        const requestedBookId = Number(swapForm.getAttribute('data-book-id') || '0');
        const offeredBookId = Number(offeredSelect ? offeredSelect.value : '0');

        if (offeredBookId <= 0) {
            BookSwap.showAlert(alertBox, 'Please choose one of your available books to offer.', 'warning');
            if (offeredSelect) offeredSelect.focus();
            return;
        }

        setLoading(submitBtn, true);

        const result = await api('api/swap.php', {
            method: 'POST',
            data: {
                action: 'request',
                requested_book_id: requestedBookId,
                offered_book_id: offeredBookId,
            },
        });

        if (result && result.ok) {
            const msg = result.message || 'Swap request sent!';
            showAlert(msg, 'success');
            BookSwap.showAlert(alertBox, msg, 'success');
            setTimeout(function () {
                window.location.href = buildUrl('swaps.php');
            }, 1000);
        } else {
            setLoading(submitBtn, false);
            const errMsg = (result && result.message) || 'Something went wrong, try again.';
            showAlert(errMsg, 'danger');
            BookSwap.showAlert(alertBox, errMsg, 'danger');
        }
    });
})();
