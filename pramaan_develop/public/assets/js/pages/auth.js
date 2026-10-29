/* ===== js/pages/auth.js ===== */
/* PRAMAAN — Login + Change Password (app/Views/auth/*) */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth;

    /** Sign-in form (flash + form) — shown in the sign-in popup of the landing page (js/pages/landing.js). */
    P.views.login = function () {
        return (window.PramanFlashHtml ? window.PramanFlashHtml() : '') +
            '<form method="post" data-action="login" novalidate data-validate autocomplete="off">' +
            '<div class="form-group"><label class="form-label" for="username">Username <span class="required">*</span></label>' +
            '<div class="input-icon">' + icon('fa-user') + '<input type="text" id="username" name="username" class="form-control" maxlength="50" autocomplete="username" placeholder="Enter your username" data-rules="required|max:50|alpha_dash" data-label="Username"></div>' +
            '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="password">Password <span class="required">*</span></label>' +
            '<div class="input-icon input-password">' + icon('fa-lock') + '<input type="password" id="password" name="password" class="form-control" maxlength="72" autocomplete="current-password" placeholder="Enter your password" data-rules="required" data-label="Password">' +
            '<button type="button" class="toggle-password" data-toggle-password="password" aria-label="Show password">' + icon('fa-eye') + '</button></div>' +
            '<div class="lp-caps" data-caps-warning hidden>' + icon('fa-triangle-exclamation') + ' Caps Lock is on</div>' +
            '<div class="invalid-feedback"></div></div>' +
            '<button type="submit" class="btn btn-primary btn-block btn-lg" data-loading-text="Signing in…">' + icon('fa-right-to-bracket') + ' <span>Sign in</span></button></form>';
    };

    // sign-in: warn when Caps Lock is on while typing the password
    ['keydown', 'keyup'].forEach(function (type) {
        document.addEventListener(type, function (ev) {
            if (!ev.target || ev.target.id !== 'password' || !ev.getModifierState) { return; }
            const box = ev.target.closest('form').querySelector('[data-caps-warning]');
            if (box) { box.hidden = !ev.getModifierState('CapsLock'); }
        });
    });

    /* ---------------- Change password ---------------- */
    P.route('/change-password', null, function () {
        const min = P.SECURITY.password_min;
        const field = function (id, text, ic, rules, extra) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + text + ' <span class="required">*</span></label>' +
                '<div class="input-icon input-password">' + icon(ic) + '<input type="password" id="' + id + '" name="' + id + '" maxlength="72" class="form-control" autocomplete="new-password" data-rules="' + rules + '" data-label="' + text + '"' + (extra || '') + '>' +
                '<button type="button" class="toggle-password" data-toggle-password="' + id + '" aria-label="Show password">' + icon('fa-eye') + '</button></div>';
        };
        return {
            title: 'Change Password',
            html: P.pageHeader(icon('fa-key') + ' Change Password', 'Keep your account secure with a strong password.') +
                '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">Set a new password</h3></div><div class="card-body">' +
                '<form method="post" data-action="change-password" novalidate data-validate autocomplete="off">' +
                field('new_password', 'New password', 'fa-key', 'required|password', ' data-strength-meter="pw-strength"') +
                '<div class="strength" id="pw-strength" aria-live="polite"><div class="strength-bar"><span class="strength-fill"></span></div><small class="strength-text">Enter a new password</small></div>' +
                '<div class="invalid-feedback"></div></div>' +
                field('confirm_password', 'Confirm new password', 'fa-key', 'required|same:new_password') + '<div class="invalid-feedback"></div></div>' +
                '<div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Update password</span></button></div>' +
                '</form></div></div>' +
                // live rules panel — ticks turn green while typing (own id, so validation.js does not reset the extra rules)
                '<div class="card pw-live"><div class="card-header"><h3 class="card-title">' + icon('fa-list-check') + ' Password rules</h3>' +
                '<span class="badge badge-muted" data-pw-score>0 / 8</span></div><div class="card-body">' +
                '<div class="pw-progress"><span data-pw-bar></span></div><p class="pw-status" data-pw-status>Start typing your new password…</p>' +
                '<p class="pw-group">New password</p><ul class="check-list pw-rules" id="pw-rules-live">' +
                '<li data-rule="length">' + icon('fa-circle-xmark') + '<span>At least ' + min + ' characters <small data-pw-len>(0)</small></span></li>' +
                '<li data-rule="upper">' + icon('fa-circle-xmark') + '<span>One uppercase letter (A–Z)</span></li>' +
                '<li data-rule="lower">' + icon('fa-circle-xmark') + '<span>One lowercase letter (a–z)</span></li>' +
                '<li data-rule="digit">' + icon('fa-circle-xmark') + '<span>One digit (0–9)</span></li>' +
                '<li data-rule="special">' + icon('fa-circle-xmark') + '<span>One special character (e.g. @ # $ %)</span></li>' +
                '<li data-rule="differs">' + icon('fa-circle-xmark') + '<span>Different from the current password</span></li>' +
                '<li data-rule="nouser">' + icon('fa-circle-xmark') + '<span>Does not contain your username (<span class="mono">' + e(P.Auth.user().username) + '</span>)</span></li></ul>' +
                '<p class="pw-group">Confirm password</p><ul class="check-list pw-rules">' +
                '<li data-rule="match">' + icon('fa-circle-xmark') + '<span>Confirm password matches the new password</span></li></ul></div></div></div>',
            after: function (root) {
                const form = root.querySelector('form[data-action="change-password"]');
                const card = root.querySelector('.pw-live');
                const username = P.Auth.user().username.toLowerCase();
                const states = ['', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
                function check() {
                    const v = form.new_password.value;
                    const c = form.confirm_password.value;
                    // "different from the current password" is checked by the server (the browser never has the password)
                    const r = {
                        length: v.length >= min, upper: /[A-Z]/.test(v), lower: /[a-z]/.test(v), digit: /\d/.test(v), special: /[^A-Za-z0-9]/.test(v),
                        differs: v !== '', nouser: v !== '' && v.toLowerCase().indexOf(username) === -1, match: c !== '' && c === v
                    };
                    let ok = 0;
                    card.querySelectorAll('[data-rule]').forEach(function (li) {
                        const pass = !!r[li.getAttribute('data-rule')];
                        if (pass) { ok++; }
                        if (li.classList.contains('ok') !== pass) {
                            li.classList.toggle('ok', pass);
                            li.classList.remove('pw-pop'); void li.offsetWidth; li.classList.add('pw-pop');
                        }
                        const i = li.querySelector('i');
                        i.classList.toggle('fa-circle-check', pass);
                        i.classList.toggle('fa-circle-xmark', !pass);
                    });
                    card.querySelector('[data-pw-len]').textContent = '(' + v.length + ')';
                    const score = card.querySelector('[data-pw-score]');
                    score.textContent = ok + ' / 8';
                    score.className = 'badge ' + (ok === 8 ? 'badge-success' : ok >= 5 ? 'badge-warning' : 'badge-muted');
                    const barEl = card.querySelector('[data-pw-bar]');
                    barEl.style.width = (ok * 100 / 8) + '%';
                    barEl.className = ok === 8 ? 'ok' : ok >= 5 ? 'mid' : 'low';
                    const strength = ['length', 'upper', 'lower', 'digit', 'special'].filter(function (k) { return r[k]; }).length;
                    card.querySelector('[data-pw-status]').innerHTML = v === '' ? 'Start typing your new password…'
                        : ok === 8 ? icon('fa-shield-halved') + ' <strong>All rules met</strong> — you can update the password.'
                            : 'Strength: <strong>' + states[strength] + '</strong> · ' + (8 - ok) + ' rule(s) left';
                }
                form.addEventListener('input', check);
                check();
            }
        };
    });

})(window, document);
