/**
 * Browse, Live Search & Pagination Script (index.php)
 *
 * Fetches api/books.php on load, debounces search typing by 300ms, ignores stale
 * out-of-order responses with a request counter, renders Bootstrap cards safely
 * using createElement + textContent (never innerHTML), and builds ellipsis pagination.
 */
(function () {
    'use strict';

    const form = document.getElementById('search-form');
    const qInput = document.getElementById('q');
    const categorySelect = document.getElementById('category');
    const countEl = document.getElementById('count');
    const spinnerEl = document.getElementById('spinner');
    const gridEl = document.getElementById('grid');
    const pagerEl = document.getElementById('pager');

    if (!form || !gridEl || !pagerEl) {
        return;
    }

    const currentUserId = Number(gridEl.getAttribute('data-current-user-id') || '0');
    let currentPage = Math.max(1, Number(new URLSearchParams(window.location.search).get('page') || '1'));
    let requestCounter = 0;
    let debounceTimer = null;

    function formatCondition(cond) {
        const labels = { new: 'Like new', good: 'Good', fair: 'Fair', worn: 'Worn' };
        return labels[cond] || (cond ? cond.charAt(0).toUpperCase() + cond.slice(1) : '');
    }

    function createCard(book) {
        const col = document.createElement('div');
        col.className = 'col-sm-6 col-lg-4 col-xl-3';

        const card = document.createElement('div');
        card.className = 'card h-100 shadow-sm book-card';

        // Cover image (lazy-loaded) or placeholder
        const coverBox = document.createElement('div');
        coverBox.className = 'book-cover-box';
        if (book.image) {
            const img = document.createElement('img');
            img.className = 'book-cover-img';
            img.loading = 'lazy';
            img.src = buildUrl('uploads/' + encodeURIComponent(book.image));
            img.alt = String(book.title || 'Book cover');
            coverBox.appendChild(img);
        } else {
            const placeholder = document.createElement('div');
            placeholder.className = 'book-cover-placeholder';
            placeholder.textContent = String(book.title || '');
            coverBox.appendChild(placeholder);
        }
        card.appendChild(coverBox);

        const body = document.createElement('div');
        body.className = 'card-body d-flex flex-column';

        // Badges: category + condition (+ optional Your Listing)
        const badges = document.createElement('div');
        badges.className = 'd-flex flex-wrap gap-1 mb-2';

        const catBadge = document.createElement('span');
        catBadge.className = 'badge bg-light text-dark border';
        catBadge.textContent = String(book.category_name || 'Uncategorized');
        badges.appendChild(catBadge);

        const condBadge = document.createElement('span');
        condBadge.className = 'badge bg-info text-dark';
        condBadge.textContent = formatCondition(book.book_condition);
        badges.appendChild(condBadge);

        if (currentUserId > 0 && Number(book.owner_id) === currentUserId) {
            const ownBadge = document.createElement('span');
            ownBadge.className = 'badge bg-secondary';
            ownBadge.textContent = 'Your Listing';
            badges.appendChild(ownBadge);
        }
        body.appendChild(badges);

        const titleEl = document.createElement('h3');
        titleEl.className = 'h6 card-title mb-1';
        titleEl.textContent = String(book.title || '');
        body.appendChild(titleEl);

        const authorEl = document.createElement('p');
        authorEl.className = 'text-muted small mb-2';
        authorEl.textContent = 'by ' + String(book.author || '');
        body.appendChild(authorEl);

        const ownerEl = document.createElement('p');
        ownerEl.className = 'small text-secondary mb-3 mt-auto';
        ownerEl.textContent = 'Listed by ' + String(book.owner_name || '');
        body.appendChild(ownerEl);

        const link = document.createElement('a');
        link.className = 'btn btn-outline-primary btn-sm w-100';
        link.href = buildUrl('book.php?id=' + Number(book.id));
        link.textContent = 'View Details';
        body.appendChild(link);

        card.appendChild(body);
        col.appendChild(card);
        return col;
    }

    function renderGrid(items) {
        gridEl.replaceChildren();

        if (!items || items.length === 0) {
            const col = document.createElement('div');
            col.className = 'col-12';
            const box = document.createElement('div');
            box.className = 'card border-0 shadow-sm p-4 text-center text-muted';
            const msg = document.createElement('p');
            msg.className = 'mb-0 fw-semibold';
            msg.textContent = 'No books match your search';
            box.appendChild(msg);
            col.appendChild(box);
            gridEl.appendChild(col);
            return;
        }

        items.forEach(function (book) {
            gridEl.appendChild(createCard(book));
        });
    }

    function buildPageNumbers(page, pages) {
        const set = new Set([1, pages]);
        for (let i = page - 1; i <= page + 1; i++) {
            if (i >= 1 && i <= pages) {
                set.add(i);
            }
        }
        return Array.from(set).sort(function (a, b) { return a - b; });
    }

    function makePagerItem(label, disabled, active, onClick) {
        const li = document.createElement('li');
        li.className = 'page-item';
        if (disabled) li.classList.add('disabled');
        if (active) {
            li.classList.add('active');
            li.setAttribute('aria-current', 'page');
        }

        if (disabled || active) {
            const span = document.createElement('span');
            span.className = 'page-link';
            span.textContent = label;
            li.appendChild(span);
        } else {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-link';
            btn.textContent = label;
            btn.addEventListener('click', onClick);
            li.appendChild(btn);
        }

        return li;
    }

    function renderPager(page, pages, total) {
        pagerEl.replaceChildren();
        if (total === 0) {
            return;
        }

        // Prev button
        pagerEl.appendChild(makePagerItem('Prev', page <= 1, false, function () {
            loadBooks(page - 1, true);
        }));

        // Numbered pages with ellipsis gaps
        const nums = buildPageNumbers(page, pages);
        let prevNum = null;
        nums.forEach(function (num) {
            if (prevNum !== null && num - prevNum > 1) {
                pagerEl.appendChild(makePagerItem('...', true, false, null));
            }
            pagerEl.appendChild(makePagerItem(String(num), false, num === page, function () {
                loadBooks(num, true);
            }));
            prevNum = num;
        });

        // Next button
        pagerEl.appendChild(makePagerItem('Next', page >= pages, false, function () {
            loadBooks(page + 1, true);
        }));
    }

    async function loadBooks(page, scrollToTop) {
        currentPage = Math.max(1, Number(page || 1));
        const myRequestId = ++requestCounter;

        if (spinnerEl) {
            spinnerEl.classList.remove('d-none');
        }
        if (gridEl) {
            gridEl.setAttribute('aria-busy', 'true');
        }
        if (countEl) {
            countEl.textContent = 'Loading books...';
        }

        const params = new URLSearchParams();
        const qVal = qInput ? qInput.value.trim() : '';
        const catVal = categorySelect ? Number(categorySelect.value || '0') : 0;

        if (qVal !== '') params.set('q', qVal);
        if (catVal > 0) params.set('category_id', String(catVal));
        params.set('page', String(currentPage));

        const result = await api('api/books.php?' + params.toString());

        // Ignore stale responses if a newer request was already fired
        if (myRequestId !== requestCounter) {
            return;
        }

        if (spinnerEl) {
            spinnerEl.classList.add('d-none');
        }
        if (gridEl) {
            gridEl.setAttribute('aria-busy', 'false');
        }

        if (!result || !result.ok) {
            if (countEl) {
                countEl.textContent = 'Could not load books.';
            }
            showAlert((result && result.message) || 'Something went wrong, try again.', 'danger');
            return;
        }

        const items = Array.isArray(result.items) ? result.items : [];
        const total = Number(result.total || 0);
        const activePage = Number(result.page || 1);
        const pages = Number(result.pages || 1);

        currentPage = activePage;

        if (countEl) {
            countEl.textContent = total === 1 ? '1 book found' : total + ' books found';
        }

        renderGrid(items);
        renderPager(activePage, pages, total);

        if (scrollToTop) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearTimeout(debounceTimer);
        loadBooks(1, false);
    });

    if (qInput) {
        qInput.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                loadBooks(1, false);
            }, 300);
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function () {
            clearTimeout(debounceTimer);
            loadBooks(1, false);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        loadBooks(currentPage, false);
    });
})();
