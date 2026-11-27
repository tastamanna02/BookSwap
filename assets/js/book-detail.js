/**
 * Book Detail Swap Request Script (book.php)
 *
 * Validates the student's offered book selection and submits a swap request
 * to api/swap-request.php via Fetch POST with loading state and CSRF header.
 */
(function () {
    'use strict';

    const swapForm = document.getElementById('swap-form');
    if (!swapForm) {
        return;
    }

    const offeredSelect = document.getElementById('offered_book_id');
    const submitBtn = document.getElementById('swap-submit-btn');
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

        const result = await api('api/swap-request.php', {
            method: 'POST',
            data: {
                requested_book_id: requestedBookId,
                offered_book_id: offeredBookId,
            },
        });

        setLoading(submitBtn, false);

        if (result && result.ok) {
            BookSwap.showAlert(alertBox, result.message || 'Swap request sent!', 'success');
            swapForm.reset();
        } else {
            BookSwap.showAlert(alertBox, (result && result.message) || 'Something went wrong, try again.', 'danger');
        }
    });
})();
