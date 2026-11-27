/**
 * Admin Dashboard Script (admin.php)
 *
 * Allows admins to delete any book via Fetch POST (api/admin-delete-book.php)
 * with X-CSRF-Token and updates the table row cleanly in the DOM.
 */
(function () {
    'use strict';

    const tableBody = document.getElementById('admin-books-body');
    if (!tableBody) {
        return;
    }

    tableBody.addEventListener('click', async function (event) {
        const btn = event.target.closest('.js-admin-delete-book');
        if (!btn) {
            return;
        }

        const bookId = Number(btn.getAttribute('data-book-id') || '0');
        const bookTitle = btn.getAttribute('data-book-title') || 'this book';
        if (bookId <= 0) {
            return;
        }

        if (!window.confirm('Admin action: permanently delete "' + bookTitle + '"? Any active swap will be cancelled.')) {
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Deleting...';

        const result = await BookSwap.fetchJson('api/admin-delete-book.php', {
            method: 'POST',
            body: { book_id: bookId },
        });

        if (result && result.ok) {
            const row = btn.closest('tr');
            if (row) {
                row.remove();
            }
            BookSwap.showAlert('global-alert', result.message || 'Book deleted by admin.', 'success');
        } else {
            btn.disabled = false;
            btn.textContent = 'Delete';
            BookSwap.showAlert('global-alert', (result && result.message) || 'Something went wrong, try again.', 'danger');
        }
    });
})();
