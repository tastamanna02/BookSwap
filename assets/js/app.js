/**
 * Shared BookSwap Client Script
 *
 * Provides CSRF-protected Fetch API requests, safe DOM alerts (textContent),
 * loading spinner state, automatic .needs-check form validation, and
 * delegated [data-delete-book] deletion handling.
 */
'use strict';

// (1) Read CSRF token and optional BASE_URL from header meta tags
const CSRF = (document.querySelector('meta[name="csrf"]') || {}).content || '';
const BASE_URL = ((document.querySelector('meta[name="base-url"]') || {}).content || '').replace(/\/+$/, '');

function buildUrl(path) {
    const raw = String(path || '');
    if (/^https?:\/\//i.test(raw)) {
        return raw;
    }
    const clean = raw.replace(/^\/+/, '');
    return BASE_URL ? `${BASE_URL}/${clean}` : `/${clean}`;
}

// (2) Fetch JSON wrapper with automatic X-CSRF-Token header and friendly errors
async function api(url, { method = 'GET', data = null } = {}) {
    const upperMethod = String(method || 'GET').toUpperCase();
    const headers = {
        'Accept': 'application/json',
        'X-CSRF-Token': CSRF,
    };

    const options = {
        method: upperMethod,
        headers: headers,
    };

    if (data !== null && data !== undefined && upperMethod !== 'GET') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(buildUrl(url), options);
        const parsed = await response.json();
        if (parsed && typeof parsed.ok === 'undefined' && !response.ok) {
            parsed.ok = false;
        }
        return parsed;
    } catch (err) {
        return {
            ok: false,
            message: 'Something went wrong, try again.',
        };
    }
}

// (3) Safely build a dismissible Bootstrap alert inside #alerts using textContent
function showAlert(message, type = 'info') {
    const container = document.getElementById('alerts');
    if (!container) {
        return;
    }

    const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'danger';
    const box = document.createElement('div');
    box.className = `alert alert-${safeType} alert-dismissible fade show`;
    box.setAttribute('role', 'alert');

    const msgSpan = document.createElement('span');
    msgSpan.textContent = String(message || 'Something went wrong, try again.');

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn-close';
    closeBtn.setAttribute('data-bs-dismiss', 'alert');
    closeBtn.setAttribute('aria-label', 'Close');

    box.appendChild(msgSpan);
    box.appendChild(closeBtn);
    container.replaceChildren(box);
}

// (4) Toggle a loading spinner + "Working..." label on a button without innerHTML
const savedButtonNodes = new WeakMap();

function setLoading(button, on) {
    if (!button) {
        return;
    }

    if (on) {
        if (!savedButtonNodes.has(button)) {
            savedButtonNodes.set(button, Array.from(button.childNodes).map((n) => n.cloneNode(true)));
        }
        button.disabled = true;

        const spinner = document.createElement('span');
        spinner.className = 'spinner-border spinner-border-sm me-1';
        spinner.setAttribute('role', 'status');
        spinner.setAttribute('aria-hidden', 'true');

        const label = document.createTextNode('Working...');
        button.replaceChildren(spinner, label);
        return;
    }

    button.disabled = false;
    const original = savedButtonNodes.get(button);
    if (original) {
        button.replaceChildren(...original);
        savedButtonNodes.delete(button);
    }
}

// (5) Automatic client-side validation for every form.needs-check
(function initNeedsCheckForms() {
    function getFieldLabel(field) {
        if (field.id) {
            const lbl = document.querySelector(`label[for="${CSS.escape(field.id)}"]`);
            if (lbl) {
                const clone = lbl.cloneNode(true);
                clone.querySelectorAll('span, small').forEach((el) => el.remove());
                const text = clone.textContent.replace(/[*:\s]+$/g, '').trim();
                if (text) {
                    return text;
                }
            }
        }
        return field.name ? field.name.replace(/[_-]+/g, ' ') : 'This field';
    }

    function getFeedbackEl(field) {
        const wrapper = field.parentElement;
        return wrapper ? wrapper.querySelector('.invalid-feedback') : null;
    }

    function validateField(field) {
        if (field.disabled || ['hidden', 'submit', 'button', 'reset'].includes(field.type)) {
            return true;
        }

        const label = getFieldLabel(field);
        const value = field.value;
        const trimmed = value.trim();
        let errorMsg = '';

        // Required check
        if (field.hasAttribute('required')) {
            if (field.type === 'checkbox' || field.type === 'radio') {
                if (!field.checked) {
                    errorMsg = `${label} is required.`;
                }
            } else if (field.type === 'file') {
                if (!field.files || field.files.length === 0) {
                    errorMsg = `${label} can't be empty.`;
                }
            } else if (trimmed === '') {
                errorMsg = `${label} can't be empty.`;
            }
        }

        // Email format check
        if (!errorMsg && field.type === 'email' && trimmed !== '') {
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed)) {
                errorMsg = 'Please enter a valid email address.';
            }
        }

        // Minimum length check
        if (!errorMsg && field.hasAttribute('minlength') && value !== '') {
            const min = Number(field.getAttribute('minlength'));
            if (Number.isFinite(min) && value.length < min) {
                errorMsg = `${label} must be at least ${min} characters.`;
            }
        }

        // Password / field match check via data-match
        if (!errorMsg && field.hasAttribute('data-match')) {
            const selector = (field.getAttribute('data-match') || '').trim();
            if (selector !== '') {
                const target = document.querySelector(selector.startsWith('#') ? selector : `#${selector}`);
                if (target && value !== target.value) {
                    errorMsg = 'Passwords do not match.';
                }
            }
        }

        // File type & max size check via data-max-mb
        if (!errorMsg && field.type === 'file' && field.files && field.files.length > 0) {
            const file = field.files[0];
            const acceptAttr = field.getAttribute('accept') || 'image/jpeg,image/png,image/webp';
            const allowedTypes = acceptAttr.split(',').map((t) => t.trim()).filter(Boolean);

            if (allowedTypes.length > 0 && !allowedTypes.includes(file.type)) {
                errorMsg = `${label} must be a JPG, PNG, or WebP image.`;
            } else {
                const maxMb = Number(field.getAttribute('data-max-mb') || '2');
                if (Number.isFinite(maxMb) && file.size > maxMb * 1024 * 1024) {
                    errorMsg = `${label} must be ${maxMb} MB or smaller.`;
                }
            }
        }

        const feedback = getFeedbackEl(field);
        if (feedback) {
            feedback.textContent = errorMsg;
        }
        field.classList.toggle('is-invalid', errorMsg !== '');

        return errorMsg === '';
    }

    document.addEventListener('DOMContentLoaded', () => {
        const forms = document.querySelectorAll('form.needs-check');
        forms.forEach((form) => {
            const fields = Array.from(form.querySelectorAll('input, select, textarea'));

            fields.forEach((field) => {
                field.addEventListener('blur', () => {
                    validateField(field);
                });

                if (field.tagName === 'SELECT' || field.type === 'file') {
                    field.addEventListener('change', () => {
                        validateField(field);
                    });
                }

                // Re-validate dependent data-match fields when the source password changes
                if (field.id) {
                    field.addEventListener('input', () => {
                        const dependents = form.querySelectorAll(`[data-match="#${CSS.escape(field.id)}"], [data-match="${CSS.escape(field.id)}"]`);
                        dependents.forEach((dep) => {
                            if (dep.value !== '' || dep.classList.contains('is-invalid')) {
                                validateField(dep);
                            }
                        });
                    });
                }
            });

            form.addEventListener('submit', (event) => {
                let firstBad = null;
                fields.forEach((field) => {
                    const valid = validateField(field);
                    if (!valid && firstBad === null) {
                        firstBad = field;
                    }
                });

                if (firstBad !== null) {
                    event.preventDefault();
                    event.stopPropagation();
                    firstBad.focus();
                    return;
                }

                const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submitBtn && submitBtn.tagName === 'BUTTON') {
                    setLoading(submitBtn, true);
                }
            });
        });
    });
})();

// (6) Delegated click handler for [data-delete-book] buttons
document.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-delete-book]');
    if (!btn) {
        return;
    }

    const bookId = Number(btn.getAttribute('data-delete-book') || '0');
    if (bookId <= 0) {
        return;
    }

    if (!window.confirm('Are you sure you want to delete this book?')) {
        return;
    }

    setLoading(btn, true);

    const result = await api('api/book_delete.php', {
        method: 'POST',
        data: { book_id: bookId },
    });

    if (result && result.ok) {
        const rowId = (btn.getAttribute('data-row') || '').replace(/^#/, '');
        if (rowId !== '') {
            const rowEl = document.getElementById(rowId);
            if (rowEl) {
                rowEl.remove();
            }
        }
        showAlert(result.message || 'Book deleted successfully.', 'success');
    } else {
        setLoading(btn, false);
        showAlert((result && result.message) || 'Something went wrong, try again.', 'danger');
    }
});

// Compatibility helpers used by page-specific scripts (browse.js, book-detail.js, swaps.js)
window.BookSwap = {
    apiUrl: buildUrl,
    fetchJson: function (path, options) {
        const opts = options || {};
        return api(path, {
            method: opts.method || 'GET',
            data: typeof opts.body !== 'undefined' ? opts.body : null,
        });
    },
    createEl: function (tag, className, text) {
        const el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (typeof text !== 'undefined' && text !== null) {
            el.textContent = String(text);
        }
        return el;
    },
    showAlert: function (target, message, type) {
        if (target === 'alerts') {
            showAlert(message, type);
            return;
        }
        const el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) {
            return;
        }
        const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'danger';
        el.className = `alert alert-${safeType}`;
        el.replaceChildren(document.createTextNode(String(message || 'Something went wrong, try again.')));
        el.classList.remove('d-none');
    },
    hideAlert: function (target) {
        const el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) {
            return;
        }
        el.replaceChildren();
        if (el.id !== 'alerts') {
            el.classList.add('d-none');
        }
    },
    statusBadgeClass: function (status) {
        switch (status) {
            case 'available':
            case 'completed':
                return 'bg-success';
            case 'accepted':
                return 'bg-primary';
            case 'pending':
            case 'reserved':
                return 'bg-warning text-dark';
            case 'declined':
                return 'bg-danger';
            default:
                return 'bg-secondary';
        }
    },
};
