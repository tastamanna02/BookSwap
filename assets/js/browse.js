/**
 * Browse & Live Search Script (index.php)
 *
 * Uses the Fetch API to query api/books.php whenever the student searches
 * or changes filters, and builds book cards safely with textContent.
 */
(function () {
    'use strict';

    const form = document.getElementById('search-form');
    const queryInput = document.getElementById('search-query');
    const categorySelect = document.getElementById('search-category');
    const conditionSelect = document.getElementById('search-condition');
    const resetBtn = document.getElementById('reset-filters');
    const grid = document.getElementById('books-grid');
    const countLabel = document.getElementById('books-count');

    if (!form || !grid) {
        return;
    }

    const currentUserId = Number(grid.getAttribute('data-current-user-id') || '0');
    let debounceTimer = null;

    function buildBookCard(book) {
        const col = BookSwap.createEl('div', 'col-sm-6 col-lg-4 col-xl-3');
        const card = BookSwap.createEl('div', 'card h-100 shadow-sm book-card');

        // Cover area
        const coverBox = BookSwap.createEl('div', 'book-cover-box');
        if (book.cover_image) {
            const img = document.createElement('img');
            img.className = 'book-cover-img';
            img.src = BookSwap.apiUrl('uploads/' + encodeURIComponent(book.cover_image));
            img.alt = String(book.title || 'Book cover');
            coverBox.appendChild(img);
        } else {
            coverBox.appendChild(BookSwap.createEl('div', 'book-cover-placeholder', book.title));
        }
        card.appendChild(coverBox);

        // Card body
        const body = BookSwap.createEl('div', 'card-body d-flex flex-column');
        const badges = BookSwap.createEl('div', 'd-flex flex-wrap gap-1 mb-2');
        badges.appendChild(BookSwap.createEl('span', 'badge bg-light text-dark border', book.category));
        badges.appendChild(BookSwap.createEl('span', 'badge bg-info text-dark', book.book_condition));
        if (currentUserId > 0 && Number(book.user_id) === currentUserId) {
            badges.appendChild(BookSwap.createEl('span', 'badge bg-secondary', 'Your Listing'));
        }
        body.appendChild(badges);

        const title = BookSwap.createEl('h2', 'h6 card-title mb-1', book.title);
        const author = BookSwap.createEl('p', 'text-muted small mb-2', 'by ' + book.author);
        const ownerText = book.owner_university
            ? 'Listed by ' + book.owner_name + ' (' + book.owner_university + ')'
            : 'Listed by ' + book.owner_name;
        const owner = BookSwap.createEl('p', 'small text-secondary mb-3 mt-auto', ownerText);

        const actionLink = BookSwap.createEl('a', 'btn btn-outline-primary btn-sm w-100', 'View Details');
        actionLink.href = BookSwap.apiUrl('book.php?id=' + Number(book.id));

        body.appendChild(title);
        body.appendChild(author);
        body.appendChild(owner);
        body.appendChild(actionLink);
        card.appendChild(body);
        col.appendChild(card);

        return col;
    }

    function renderBooks(books) {
        grid.replaceChildren();

        if (countLabel) {
            const label = books.length === 1 ? '1 book available' : books.length + ' books available';
            countLabel.textContent = label;
        }

        if (books.length === 0) {
            const emptyCol = BookSwap.createEl('div', 'col-12');
            const emptyBox = BookSwap.createEl('div', 'card border-0 shadow-sm p-4 text-center text-muted');
            emptyBox.appendChild(BookSwap.createEl('p', 'mb-2 fw-semibold', 'No matching books found.'));
            emptyBox.appendChild(BookSwap.createEl('p', 'mb-0 small', 'Try clearing your filters or searching another title or author.'));
            emptyCol.appendChild(emptyBox);
            grid.appendChild(emptyCol);
            return;
        }

        books.forEach(function (book) {
            grid.appendChild(buildBookCard(book));
        });
    }

    async function loadFilteredBooks() {
        const params = new URLSearchParams();
        if (queryInput && queryInput.value.trim() !== '') {
            params.set('q', queryInput.value.trim());
        }
        if (categorySelect && categorySelect.value !== '') {
            params.set('category', categorySelect.value);
        }
        if (conditionSelect && conditionSelect.value !== '') {
            params.set('condition', conditionSelect.value);
        }

        const qs = params.toString();
        const endpoint = qs ? 'api/books.php?' + qs : 'api/books.php';
        const result = await BookSwap.fetchJson(endpoint);

        if (!result || !result.ok) {
            BookSwap.showAlert('global-alert', (result && result.message) || 'Something went wrong, try again.', 'danger');
            return;
        }

        BookSwap.hideAlert('global-alert');
        renderBooks(Array.isArray(result.books) ? result.books : []);
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadFilteredBooks();
    });

    if (queryInput) {
        queryInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(loadFilteredBooks, 300);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', loadFilteredBooks);
    }

    if (conditionSelect) {
        conditionSelect.addEventListener('change', loadFilteredBooks);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            if (queryInput) queryInput.value = '';
            if (categorySelect) categorySelect.value = '';
            if (conditionSelect) conditionSelect.value = '';
            loadFilteredBooks();
        });
    }
})();
