/**
 * Shared BookSwap Client Helpers
 *
 * Provides CSRF-protected Fetch API wrappers, safe DOM creation using
 * textContent (never innerHTML for user data), and basic form validation.
 */
(function () {
    'use strict';

    const csrfMeta = document.querySelector('meta[name="csrf"]');
    const baseMeta = document.querySelector('meta[name="base-url"]');

    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';
    const baseUrl = baseMeta ? (baseMeta.getAttribute('content') || '').replace(/\/+$/, '') : '';

    /**
     * Build a full relative URL using the server-detected BASE_URL.
     */
    function apiUrl(path) {
        const clean = String(path || '').replace(/^\/+/, '');
        return baseUrl ? `${baseUrl}/${clean}` : `/${clean}`;
    }

    /**
     * Perform a Fetch request returning parsed JSON with automatic CSRF header on POST.
     */
    async function fetchJson(path, options) {
        const opts = Object.assign({ method: 'GET' }, options || {});
        const headers = new Headers(opts.headers || {});
        headers.set('Accept', 'application/json');

        if (opts.method.toUpperCase() === 'POST') {
            headers.set('X-CSRF-Token', csrfToken);
            if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
                headers.set('Content-Type', 'application/json');
                opts.body = JSON.stringify(opts.body);
            }
        }

        opts.headers = headers;

        try {
            const response = await fetch(apiUrl(path), opts);
            const data = await response.json();
            if (!response.ok && data && typeof data.ok === 'undefined') {
                data.ok = false;
            }
            return data;
        } catch (err) {
            return {
                ok: false,
                message: 'Something went wrong, try again.',
            };
        }
    }

    /**
     * Create an HTML element safely using textContent so user data never runs as HTML.
     */
    function createEl(tag, className, text) {
        const el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        if (typeof text !== 'undefined' && text !== null) {
            el.textContent = String(text);
        }
        return el;
    }

    /**
     * Show a friendly Bootstrap alert in #alerts or an inline alert element without innerHTML.
     */
    function showAlert(target, message, type) {
        const el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) {
            return;
        }

        const safeType = ['success', 'danger', 'warning', 'info'].includes(type) ? type : 'danger';
        const safeText = String(message || 'Something went wrong, try again.');

        if (el.id === 'alerts') {
            const box = createEl('div', `alert alert-${safeType} alert-dismissible fade show`);
            box.setAttribute('role', 'alert');
            box.appendChild(document.createTextNode(safeText));

            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'btn-close';
            closeBtn.setAttribute('data-bs-dismiss', 'alert');
            closeBtn.setAttribute('aria-label', 'Close');
            box.appendChild(closeBtn);

            el.replaceChildren(box);
            return;
        }

        el.className = `alert alert-${safeType}`;
        el.replaceChildren(document.createTextNode(safeText));
        el.classList.remove('d-none');
    }

    /**
     * Clear or hide an alert container cleanly.
     */
    function hideAlert(target) {
        const el = typeof target === 'string' ? document.getElementById(target) : target;
        if (!el) {
            return;
        }

        el.replaceChildren();
        if (el.id !== 'alerts') {
            el.classList.add('d-none');
        }
    }

    /**
     * Map book/swap statuses to Bootstrap badge classes.
     */
    function statusBadgeClass(status) {
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
    }

    /**
     * Validate a form's inputs and populate empty .invalid-feedback elements safely.
     */
    function validateFormFields(form) {
        const pass = form.querySelector('input[name="password"], input[name="new_password"]');
        const confirm = form.querySelector('input[name="confirm_password"]');
        if (pass && confirm) {
            if (confirm.value !== '' && pass.value !== confirm.value) {
                confirm.setCustomValidity('Passwords do not match.');
            } else {
                confirm.setCustomValidity('');
            }
        }

        const fileInput = form.querySelector('input[type="file"][name="image"]');
        if (fileInput) {
            if (fileInput.files && fileInput.files.length > 0) {
                const file = fileInput.files[0];
                const allowed = ['image/jpeg', 'image/png', 'image/webp'];
                if (!allowed.includes(file.type)) {
                    fileInput.setCustomValidity('Please upload a JPG, PNG, or WebP image.');
                } else if (file.size > 2 * 1024 * 1024) {
                    fileInput.setCustomValidity('Image must be 2 MB or smaller.');
                } else {
                    fileInput.setCustomValidity('');
                }
            } else {
                fileInput.setCustomValidity('');
            }
        }

        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(function (field) {
            const group = field.parentElement;
            const feedback = group ? group.querySelector('.invalid-feedback') : null;
            if (feedback) {
                feedback.textContent = field.validity.valid ? '' : field.validationMessage;
            }
        });
    }

    /**
     * Attach standard client-side validation to forms marked with .needs-check or .js-validate-form.
     */
    function initFormValidation() {
        const forms = document.querySelectorAll('.needs-check, .js-validate-form');
        forms.forEach(function (form) {
            form.addEventListener('input', function () {
                if (form.classList.contains('was-validated')) {
                    validateFormFields(form);
                }
            });

            form.addEventListener('submit', function (event) {
                validateFormFields(form);

                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                form.classList.add('was-validated');
            });
        });
    }

    document.addEventListener('DOMContentLoaded', initFormValidation);

    window.BookSwap = {
        apiUrl: apiUrl,
        fetchJson: fetchJson,
        createEl: createEl,
        showAlert: showAlert,
        hideAlert: hideAlert,
        statusBadgeClass: statusBadgeClass,
    };
})();
