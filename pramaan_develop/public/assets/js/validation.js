/* ===== js/validation.js ===== */
/* =====================================================================
   PRAMAAN — inline form validation (mirrors App\Core\Validator).
   This is ONLY for user convenience; the server always re-validates.

   <input data-rules="required|min:3|max:50" data-label="Scheme name">
   Forms opt in with  <form data-validate novalidate>.
   Extra form-level checks:  Praman.Validation.addCheck(form, fn)  where
   fn(form) returns {field: element|null, message: string} or null.
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};
    // minimum length from App Settings (the server checks the same value)
    const minPw = function () { return Number(window.PramanDB ? window.PramanDB.setting('password_min_length', 10) : 10); };
    const formChecks = new WeakMap();

    function label(el) {
        return el.getAttribute('data-label') || el.name || 'This field';
    }

    function valueOf(el) {
        if (el.type === 'checkbox') { return el.checked ? (el.value || '1') : ''; }
        if (el.type === 'radio') {
            const form = el.form || document;
            const checked = form.querySelector('input[type="radio"][name="' + CSS.escape(el.name) + '"]:checked');
            return checked ? checked.value : '';
        }
        if (el.tagName === 'SELECT' && el.multiple) {
            return Array.prototype.filter.call(el.options, function (o) { return o.selected; }).length ? 'x' : '';
        }
        return (el.value || '').trim();
    }

    function passwordError(v, lbl) {
        if (v.length < minPw()) { return lbl + ' must be at least ' + minPw() + ' characters.'; }
        if (v.length > 72) { return lbl + ' may not be more than 72 characters.'; }
        if (!/[A-Z]/.test(v) || !/[a-z]/.test(v) || !/\d/.test(v) || !/[^A-Za-z0-9]/.test(v)) {
            return lbl + ' must contain an uppercase letter, a lowercase letter, a digit and a special character.';
        }
        return null;
    }

    const rules = {
        min: function (v, a, l) { return v.length < +a ? l + ' must be at least ' + a + ' characters.' : null; },
        max: function (v, a, l) { return v.length > +a ? l + ' may not be more than ' + a + ' characters.' : null; },
        email: function (v, a, l) { return /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v) ? null : l + ' must be a valid email address.'; },
        mobile: function (v, a, l) { return /^[6-9]\d{9}$/.test(v) ? null : l + ' must be a valid 10-digit mobile number.'; },
        integer: function (v, a, l) { return /^-?\d+$/.test(v) ? null : l + ' must be a whole number.'; },
        numeric: function (v, a, l) { return v !== '' && !isNaN(v) ? null : l + ' must be a number.'; },
        decimal: function (v, a, l) {
            const d = +(a || 2);
            return new RegExp('^\\d{1,10}(\\.\\d{1,' + d + '})?$').test(v) ? null : l + ' must be a number with up to ' + d + ' decimal places.';
        },
        positive: function (v, a, l) { return !isNaN(v) && parseFloat(v) > 0 ? null : l + ' must be greater than zero.'; },
        between: function (v, a, l) {
            const p = a.split(',');
            const n = parseFloat(v);
            return !isNaN(n) && n >= +p[0] && n <= +p[1] ? null : l + ' must be between ' + p[0] + ' and ' + p[1] + '.';
        },
        in: function (v, a, l) { return a.split(',').indexOf(v) !== -1 ? null : 'Please select a valid ' + l + '.'; },
        code: function (v, a, l) { return /^[A-Z][A-Z0-9_]{1,39}$/.test(v) ? null : l + ' must start with a letter and contain only CAPITAL letters, digits and underscore.'; },
        alpha_dash: function (v, a, l) { return /^[A-Za-z0-9._-]+$/.test(v) ? null : l + ' may contain only letters, digits, dot, dash and underscore.'; },
        name: function (v, a, l) { return /^[\p{L}\p{M} .'()\/-]+$/u.test(v) ? null : l + ' contains invalid characters.'; },
        text: function (v, a, l) { return /[<>]/.test(v) ? l + ' may not contain < or > characters.' : null; },
        ucode: function (v, a, l) { return /^[A-Z0-9][A-Z0-9\/_-]{1,29}$/.test(v) ? null : l + ' may contain only CAPITAL letters, digits, / _ - (2–30 characters).'; },
        plotno: function (v, a, l) { return /^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/.test(v) ? null : l + ' may contain only letters, digits, space, / _ - (max 30).'; },
        year: function (v, a, l) { return /^\d{4}$/.test(v) && +v >= 1950 && +v <= 2100 ? null : l + ' must be a valid year (1950–2100).'; },
        password: function (v, a, l) { return passwordError(v, l); },
        url: function (v, a, l) { return /^https?:\/\/[^\s<>"']+\.[^\s<>"']+$/i.test(v) ? null : l + ' must be a valid web address (https://…).'; },
        csvlist: function (v, a, l) { return /^[a-z0-9_.-]+(\s*,\s*[a-z0-9_.-]+)*$/i.test(v) ? null : l + ': use usernames separated by commas.'; },
        date: function (v, a, l) { return /^\d{4}-\d{2}-\d{2}$/.test(v) && !isNaN(Date.parse(v)) ? null : l + ' must be a valid date.'; },
        notfuture: function (v, a, l) { return v <= (window.PramanDB ? window.PramanDB.today() : v) ? null : l + ' cannot be in the future.'; },
        same: function (v, a, l, el) {
            const form = el.form || document;
            const other = form.querySelector('[name="' + CSS.escape(a) + '"]');
            return other && other.value === el.value ? null : l + ' does not match.';
        }
    };

    function feedbackFor(el) {
        const group = el.closest('.form-group') || el.parentElement;
        return group ? group.querySelector('.invalid-feedback') : null;
    }

    function setError(el, message) {
        const fb = feedbackFor(el);
        if (message) {
            el.classList.add('is-invalid');
            el.classList.remove('is-valid');
            el.setAttribute('aria-invalid', 'true');
            if (fb) { fb.textContent = message; }
        } else {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
            if (valueOf(el) !== '' && el.type !== 'checkbox' && el.type !== 'radio') { el.classList.add('is-valid'); }
            if (fb) { fb.textContent = ''; }
        }
    }

    /** Validate one element. Returns error message or null. */
    function validateField(el) {
        const spec = el.getAttribute('data-rules');
        if (!spec || el.disabled) { setError(el, null); return null; }
        const list = spec.split('|');
        const v = valueOf(el);
        const l = label(el);
        let msg = null;

        if (v === '') {
            if (list.indexOf('required') !== -1) {
                msg = (el.type === 'checkbox' || el.tagName === 'SELECT' || el.type === 'radio')
                    ? 'Please select ' + l + '.' : l + ' is required.';
            }
        } else {
            for (let i = 0; i < list.length && !msg; i++) {
                const parts = list[i].split(':');
                const name = parts.shift();
                const arg = parts.join(':');
                if (rules[name]) { msg = rules[name](v, arg, l, el); }
            }
        }
        setError(el, msg);
        return msg;
    }

    /** Validate a whole form. Returns true when valid; focuses the first invalid field. */
    function validateForm(form) {
        let first = null;
        let firstMsg = null;
        form.querySelectorAll('[data-rules]').forEach(function (el) {
            const msg = validateField(el);
            if (msg && !first) { first = el; firstMsg = msg; }
        });
        (formChecks.get(form) || []).forEach(function (fn) {
            const res = fn(form);
            if (res && res.message) {
                if (res.field) { setError(res.field, res.message); }
                if (!first) { first = res.field || form; firstMsg = res.message; }
            }
        });
        if (first) {
            if (first.focus && first !== form) { first.focus({ preventScroll: false }); }
            const target = first.closest ? (first.closest('.form-group') || first) : first;
            target.classList.remove('anim-shake');
            void target.offsetWidth;
            target.classList.add('anim-shake');
            if (Praman.toast) { Praman.toast('error', firstMsg || 'Please correct the highlighted errors.'); }
            return false;
        }
        return true;
    }

    function addCheck(form, fn) {
        const list = formChecks.get(form) || [];
        list.push(fn);
        formChecks.set(form, list);
    }

    /* Live validation: on blur, and on input once a field has been marked invalid */
    document.addEventListener('blur', function (e) {
        const el = e.target;
        if (el && el.matches && el.matches('form[data-validate] [data-rules]')) { validateField(el); }
    }, true);
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (el && el.matches && el.matches('form[data-validate] [data-rules]') && el.classList.contains('is-invalid')) {
            validateField(el);
        }
    });
    document.addEventListener('change', function (e) {
        const el = e.target;
        if (el && el.matches && el.matches('form[data-validate] [data-rules]')) { validateField(el); }
    });

    /* Password strength meter + rule checklist */
    function strength(v) {
        let s = 0;
        if (v.length >= minPw()) { s++; }
        if (/[A-Z]/.test(v)) { s++; }
        if (/[a-z]/.test(v)) { s++; }
        if (/\d/.test(v)) { s++; }
        if (/[^A-Za-z0-9]/.test(v)) { s++; }
        return s;
    }
    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!el || !el.hasAttribute || !el.hasAttribute('data-strength-meter')) { return; }
        const box = document.getElementById(el.getAttribute('data-strength-meter'));
        const v = el.value;
        const s = strength(v);
        if (box) {
            const fill = box.querySelector('.strength-fill');
            const text = box.querySelector('.strength-text');
            const colors = ['#b91c1c', '#b91c1c', '#b45309', '#b45309', '#1d4ed8', '#15803d'];
            const names = ['Enter a new password', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
            fill.style.width = (s * 20) + '%';
            fill.style.background = colors[s];
            text.textContent = v === '' ? names[0] : names[s];
        }
        const list = document.getElementById('pw-rules');
        if (list) {
            const checks = {
                length: v.length >= minPw(), upper: /[A-Z]/.test(v), lower: /[a-z]/.test(v),
                digit: /\d/.test(v), special: /[^A-Za-z0-9]/.test(v)
            };
            list.querySelectorAll('[data-rule]').forEach(function (li) {
                const ok = !!checks[li.getAttribute('data-rule')];
                li.classList.toggle('ok', ok);
                const icon = li.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-circle-check', ok);
                    icon.classList.toggle('fa-circle-xmark', !ok);
                }
            });
        }
    });

    Praman.Validation = {
        validateField: validateField,
        validateForm: validateForm,
        setError: setError,
        addCheck: addCheck
    };
})(window, document);
