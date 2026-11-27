/**
 * My Books Page Script (my-books.php)
 *
 * Handles owner book deletion via Fetch POST (api/book-delete.php)
 * with CSRF protection and updates the DOM cleanly without page reload.
 */
(function () {
    'use strict';

    const listContainer = document.getElementById('my-books-list');
    if (!listContainer) {
        return;
    }

    listContainer.addEventListener('click', async function (event) {
        const btn = event.target.closest('.js-delete-my-book');
        if (!btn) {
            return;
        }

        const bookId = Number(btn.getAttribute('data-book-id') || '0');
        const bookTitle = btn.getAttribute('data-book-title') || 'this book';
        if (bookId <= 0) {
            return;
        }

        if (!window.confirm('Delete "' + bookTitle + '"? Any pending requests for it will be cancelled.')) {
            return;
        }

        btn.disabled = true;
        btn.textContent = 'Deleting...';

        const result = await BookSwap.fetchJson('api/book-delete.php', {
            method: 'POST',
            body: { book_id: bookId },
        });

        if (result && result.ok) {
            const cardCol = btn.closest('.js-book-item');
            if (cardCol) {
                cardCol.remove();
            }
            BookSwap.showAlert('alerts', result.message || 'Book deleted.', 'success');

            if (listContainer.querySelectorAll('.js-book-item').length === 0) {
                const emptyDiv = BookSwap.createEl('div', 'col-12');
                const box = BookSwap.createEl('div', 'card border-0 shadow-sm p-4 text-center text-muted');
                box.appendChild(BookSwap.createEl('p', 'mb-3', 'You have not listed any books yet.'));
                const addLink = BookSwap.createEl('a', 'btn btn-primary btn-sm', 'Add Your First Book');
                addLink.href = BookSwap.apiUrl('book-form.php');
                box.appendChild(addLink);
                emptyDiv.appendChild(box);
                listContainer.appendChild(emptyDiv);
            }
        } else {
            btn.disabled = false;
            btn.textContent = 'Delete';
            BookSwap.showAlert('alerts', (result && result.message) || 'Something went wrong, try again.', 'danger');
        }
    });
})();
