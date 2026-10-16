/* =====================================================================
   PRAMAN — UI behaviour: sidebar, dropdowns, toasts, confirm modal,
   form submit pipeline (validate -> confirm -> loading), table helpers.
   No inline scripts anywhere (strict CSP) — everything hooks via data-*.
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};

    /* ---------------- Toasts ---------------- */
    const TOAST_ICONS = {
        success: 'fa-circle-check',
        error: 'fa-circle-exclamation fa-beat',
        warning: 'fa-triangle-exclamation fa-beat',
        info: 'fa-circle-info'
    };

    Praman.toast = function (type, message, timeout) {
        const box = document.getElementById('toast-container');
        if (!box || !message) { return; }
        type = TOAST_ICONS[type] ? type : 'info';
        const t = document.createElement('div');
        t.className = 'toast toast-' + type;
        t.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const icon = document.createElement('i');
        icon.className = 'fa-solid ' + TOAST_ICONS[type];
        icon.setAttribute('aria-hidden', 'true');
        const msg = document.createElement('div');
        msg.className = 'toast-msg';
        msg.textContent = message;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'toast-close';
        close.setAttribute('aria-label', 'Dismiss');
        close.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i>';

        t.appendChild(icon); t.appendChild(msg); t.appendChild(close);
        box.appendChild(t);
        // stop the beat after a moment so it does not distract
        window.setTimeout(function () { icon.classList.remove('fa-beat'); }, 2400);

        function remove() {
            t.classList.add('is-leaving');
            window.setTimeout(function () { t.remove(); }, 260);
        }
        close.addEventListener('click', remove);
        window.setTimeout(remove, timeout || (type === 'error' ? 7000 : 4500));
    };

    /* ---------------- Confirm modal ---------------- */
    Praman.confirm = function (opts) {
        opts = opts || {};
        const modal = document.getElementById('confirm-modal');
        if (!modal) { return Promise.resolve(window.confirm(opts.message || 'Are you sure?')); }

        const title = modal.querySelector('#confirm-title');
        const message = modal.querySelector('#confirm-message');
        const ok = modal.querySelector('[data-confirm-ok]');
        const cancel = modal.querySelector('[data-confirm-cancel]');
        const variant = opts.variant || 'primary';

        title.textContent = opts.title || 'Please confirm';
        message.textContent = opts.message || 'Are you sure you want to continue?';
        ok.className = 'btn btn-' + variant;
        ok.querySelector('span').textContent = opts.okText || 'Yes, continue';
        cancel.querySelector('span').textContent = opts.cancelText || 'No, cancel';

        const lastFocus = document.activeElement;
        modal.hidden = false;
        document.body.classList.add('modal-open');
        window.requestAnimationFrame(function () { modal.classList.add('is-open'); ok.focus(); });

        return new Promise(function (resolve) {
            function close(result) {
                modal.classList.remove('is-open');
                document.body.classList.remove('modal-open');
                window.setTimeout(function () { modal.hidden = true; }, 180);
                ok.removeEventListener('click', onOk);
                cancel.removeEventListener('click', onCancel);
                modal.removeEventListener('click', onBackdrop);
                document.removeEventListener('keydown', onKey);
                if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
                resolve(result);
            }
            function onOk() { close(true); }
            function onCancel() { close(false); }
            function onBackdrop(e) { if (e.target === modal) { close(false); } }
            function onKey(e) {
                if (e.key === 'Escape') { close(false); }
                if (e.key === 'Tab') { // simple focus trap
                    const f = [cancel, ok];
                    const i = f.indexOf(document.activeElement);
                    e.preventDefault();
                    f[(i + (e.shiftKey ? f.length - 1 : 1)) % f.length].focus();
                }
            }
            ok.addEventListener('click', onOk);
            cancel.addEventListener('click', onCancel);
            modal.addEventListener('click', onBackdrop);
            document.addEventListener('keydown', onKey);
        });
    };

    /* ---------------- Button loading state ---------------- */
    Praman.setLoading = function (btn, loading) {
        if (!btn) { return; }
        if (loading) {
            if (btn.classList.contains('is-loading')) { return; }
            // NOTE: not "disabled" — a disabled submitter is dropped from the POST (name=value lost).
            // Double submits are blocked by form.dataset.submitting + pointer-events:none in CSS.
            btn.classList.add('is-loading');
            btn.setAttribute('aria-disabled', 'true');
            btn.setAttribute('aria-busy', 'true');
            const span = btn.querySelector('span');
            if (span) {
                btn.dataset.originalText = span.textContent;
                if (btn.dataset.loadingText) { span.textContent = btn.dataset.loadingText; }
            }
            const spin = document.createElement('i');
            spin.className = 'fa-solid fa-spinner fa-spin btn-spinner';
            spin.setAttribute('aria-hidden', 'true');
            btn.insertBefore(spin, btn.firstChild);
        } else {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-disabled');
            btn.removeAttribute('aria-busy');
            const spin = btn.querySelector('.btn-spinner');
            if (spin) { spin.remove(); }
            const span = btn.querySelector('span');
            if (span && btn.dataset.originalText !== undefined) { span.textContent = btn.dataset.originalText; }
        }
    };

    /* ---------------- Form submit pipeline ----------------
       1. data-validate  -> client validation
       2. data-confirm   -> confirm modal (message from attribute)
       3. loading state on the submit button, double-submit guard          */
    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get') { return; }

        if (form.dataset.submitting === '1') { e.preventDefault(); return; }

        // Page-specific pre-checks registered by forms.js (return false to stop)
        if (Praman.beforeSubmit && Praman.beforeSubmit(form, e.submitter) === false) {
            e.preventDefault();
            return;
        }

        if (form.hasAttribute('data-validate') && Praman.Validation && !Praman.Validation.validateForm(form)) {
            e.preventDefault();
            return;
        }

        const submitter = e.submitter || form.querySelector('[type="submit"]');
        const confirmMsg = (submitter && submitter.getAttribute('data-confirm')) || form.getAttribute('data-confirm');

        if (confirmMsg && form.dataset.confirmed !== '1') {
            e.preventDefault();
            const src = (submitter && submitter.hasAttribute('data-confirm')) ? submitter : form;
            Praman.confirm({
                title: src.getAttribute('data-confirm-title') || 'Please confirm',
                message: confirmMsg,
                okText: src.getAttribute('data-confirm-ok') || 'Yes, continue',
                variant: src.getAttribute('data-confirm-variant') || 'primary'
            }).then(function (yes) {
                if (!yes) { return; }
                form.dataset.confirmed = '1';
                if (form.requestSubmit) { form.requestSubmit(submitter && submitter.form === form ? submitter : undefined); }
                else { form.submit(); }
            });
            return;
        }

        form.dataset.confirmed = '';
        form.dataset.submitting = '1';
        Praman.setLoading(submitter, true);
    });

    // Restore buttons when the page is shown from the back/forward cache
    window.addEventListener('pageshow', function () {
        document.querySelectorAll('form[data-submitting="1"]').forEach(function (f) {
            f.dataset.submitting = '';
            f.querySelectorAll('.is-loading').forEach(function (b) { Praman.setLoading(b, false); });
        });
    });

    /* ---------------- Links / buttons with data-confirm (non-form) ---------------- */
    document.addEventListener('click', function (e) {
        const a = e.target.closest('a[data-confirm]');
        if (!a) { return; }
        e.preventDefault();
        Praman.confirm({
            title: a.getAttribute('data-confirm-title') || 'Please confirm',
            message: a.getAttribute('data-confirm'),
            okText: a.getAttribute('data-confirm-ok') || 'Yes, continue',
            variant: a.getAttribute('data-confirm-variant') || 'primary'
        }).then(function (yes) { if (yes) { window.location.href = a.href; } });
    });

    /* ---------------- Sidebar (off-canvas on small screens) ---------------- */
    function setSidebar(open) {
        document.body.classList.toggle('sidebar-open', open);
        const t = document.querySelector('[data-sidebar-toggle]');
        if (t) { t.setAttribute('aria-expanded', open ? 'true' : 'false'); }
    }
    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-sidebar-toggle]')) { setSidebar(!document.body.classList.contains('sidebar-open')); }
        else if (e.target.closest('[data-sidebar-close]')) { setSidebar(false); }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            setSidebar(false);
            document.querySelectorAll('[data-dropdown].open').forEach(function (d) { d.classList.remove('open'); });
        }
    });
    window.addEventListener('resize', function () { if (window.innerWidth >= 992) { setSidebar(false); } });

    /* ---------------- Dropdowns ---------------- */
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('[data-dropdown-toggle]');
        document.querySelectorAll('[data-dropdown].open').forEach(function (d) {
            if (!toggle || !d.contains(toggle)) {
                d.classList.remove('open');
                const t = d.querySelector('[data-dropdown-toggle]');
                if (t) { t.setAttribute('aria-expanded', 'false'); }
            }
        });
        if (toggle) {
            const dd = toggle.closest('[data-dropdown]');
            const open = !dd.classList.contains('open');
            dd.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    });

    /* ---------------- Alerts ---------------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-dismiss-alert]');
        if (btn) { btn.closest('.alert').remove(); }
    });
    function autoDismissAlerts() {
        document.querySelectorAll('.alert[data-flash="success"], .alert[data-flash="info"]').forEach(function (a) {
            window.setTimeout(function () {
                a.classList.add('anim-fade-in');
                a.remove();
            }, 6000);
        });
    }

    /* ---------------- Show / hide password ---------------- */
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-toggle-password]');
        if (!btn) { return; }
        const input = document.getElementById(btn.getAttribute('data-toggle-password'));
        if (!input) { return; }
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        const i = btn.querySelector('i');
        if (i) { i.classList.toggle('fa-eye', !show); i.classList.toggle('fa-eye-slash', show); }
    });

    /* ---------------- Auto-submit selects (page size, filters) ---------------- */
    document.addEventListener('change', function (e) {
        const el = e.target;
        if (el.matches('[data-auto-submit]') && el.form) {
            const page = el.form.querySelector('input[name="page"]');
            if (page) { page.value = '1'; }
            const reset = el.getAttribute('data-reset-on-change');
            if (reset) {
                const dep = el.form.querySelector('[name="' + CSS.escape(reset) + '"]');
                if (dep) { dep.value = ''; dep.disabled = true; }
            }
            el.form.submit();
        }
    });

    /* ---------------- Bulk-select tables ----------------
       <input type="checkbox" data-check-all="ids[]">  toggles all  input[name="ids[]"]
       Elements with data-bulk-count="ids[]" show the selected count;
       buttons with data-bulk-requires="ids[]" are disabled until something is selected. */
    function refreshBulk(name) {
        const boxes = document.querySelectorAll('input[type="checkbox"][name="' + CSS.escape(name) + '"]:not(:disabled)');
        const checked = Array.prototype.filter.call(boxes, function (b) { return b.checked; }).length;
        document.querySelectorAll('[data-check-all="' + CSS.escape(name) + '"]').forEach(function (all) {
            all.checked = boxes.length > 0 && checked === boxes.length;
            all.indeterminate = checked > 0 && checked < boxes.length;
        });
        document.querySelectorAll('[data-bulk-count="' + CSS.escape(name) + '"]').forEach(function (c) { c.textContent = String(checked); });
        document.querySelectorAll('[data-bulk-requires="' + CSS.escape(name) + '"]').forEach(function (b) { b.disabled = checked === 0; });
    }
    document.addEventListener('change', function (e) {
        const el = e.target;
        if (el.matches('[data-check-all]')) {
            const name = el.getAttribute('data-check-all');
            document.querySelectorAll('input[type="checkbox"][name="' + CSS.escape(name) + '"]:not(:disabled)')
                .forEach(function (b) { b.checked = el.checked; });
            refreshBulk(name);
        } else if (el.type === 'checkbox' && el.name && document.querySelector('[data-check-all="' + CSS.escape(el.name) + '"]')) {
            refreshBulk(el.name);
        }
    });

    /* ---------------- Progress bars (no inline styles: CSP) ---------------- */
    function paintBars() {
        document.querySelectorAll('[data-pct]').forEach(function (bar) {
            const pct = Math.max(0, Math.min(100, parseFloat(bar.getAttribute('data-pct')) || 0));
            window.requestAnimationFrame(function () { bar.style.width = pct + '%'; });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        autoDismissAlerts();
        paintBars();
        document.querySelectorAll('[data-check-all]').forEach(function (el) { refreshBulk(el.getAttribute('data-check-all')); });
    });
})(window, document);
