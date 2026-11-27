/**
 * Book Detail Swap Request Script (book.php)
 *
 * Validates the student's offered book selection and submits a swap request
 * to api/swap-request.php via Fetch POST with the X-CSRF-Token header.
 */
(function () {
    'use strict';

    const swapForm = document.getElementById('swap-request-form');
    if (!swapForm) {
        return;
    }

    const offeredSelect = document.getElementById('offered_book_id');
    const submitBtn = document.getElementById('swap-submit-btn');
    const alertBox = document.getElementById('swap-form-alert');

    swapForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        BookSwap.hideAlert(alertBox);

        const requestedBookId = Number(swapForm.getAttribute('data-requested-book-id') || '0');
        const offeredBookId = Number(offeredSelect ? offeredSelect.value : '0');

        if (offeredBookId <= 0) {
            BookSwap.showAlert(alertBox, 'Please choose one of your available books to offer.', 'warning');
            return;
        }

        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sending...';
        }

        const result = await BookSwap.fetchJson('api/swap-request.php', {
            method: 'POST',
            body: {
                requested_book_id: requestedBookId,
                offered_book_id: offeredBookId,
            },
        });

        if (result && result.ok) {
            BookSwap.showAlert(alertBox, result.message || 'Swap request sent!', 'success');
            swapForm.reset();
        } else {
            BookSwap.showAlert(alertBox, (result && result.message) || 'Something went wrong, try again.', 'danger');
        }

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Send Swap Request';
        }
    });
})();
