/* ===== js/pages/landing.js ===== */
/* PRAMAAN — landing page (#/ and #/login for guests): white glass header with a "Sign in" button,
   light hero (headline + sign-in CTA on the left, 4-photo slider of Kota on the right), slim footer bar.
   The sign-in form (P.views.login from js/pages/auth.js) lives in a popup that opens from the header
   or hero button — and by itself when a flash message is waiting ("Please login to continue", "logged out" …).
   Styles are in css/landing.css + css/landing-photo.css (lp- classes). */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB;
    const reduceMotion = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const S = function (k, d) { return DB.setting(k, d); };
    /** Organisation website from App Settings (only http/https links). */
    function site() { const u = String(S('org_website', '')); return /^https?:\/\//i.test(u) ? u : '#/'; }
    // KDA emblem for the header and footer — the same embedded image as the favicon (<link id="app-icon"> in <head>)
    const EMBLEM = '<img src="' + e((document.getElementById('app-icon') || {}).href || '') + '" alt="" width="57" height="57">';

    // hero slider: active slides from Super Admin → App Settings, in order: [image URL, tooltip]
    function shots() {
        return (DB.app().slides || []).filter(function (s) { return s.is_active && /^[A-Za-z0-9_.-]+\.(jpe?g|png)$/.test(s.image); }).map(function (s) {
            return [P.BASE + '/assets/img/slides/' + s.image, s.title + (s.credit ? ' — ' + s.credit : '')];
        });
    }

    /* ---------------- Header ---------------- */
    function header() {
        return '<header class="lp-header" data-lp-header><div class="lp-container lp-header-inner">' +
            '<a class="lp-brand" href="#/" data-lp-top><span class="lp-brand-logo">' + EMBLEM + '</span><span class="lp-brand-text"><span class="lp-brand-name">' + e(P.APP.name) + '</span>' +
            '<span class="lp-brand-sub">' + e(P.APP.tagline) + '</span></span></a>' +
            '<div class="lp-header-actions"><span class="lp-header-note">' + icon('fa-lock') + ' ' + e(S('header_note', '')) + '</span>' +
            '<button type="button" class="lp-btn lp-btn-primary" data-lp-signin aria-haspopup="dialog" aria-controls="lp-signin">' + icon('fa-right-to-bracket') + ' <span>Sign in</span></button></div>' +
            '</div></header>';
    }

    /* ---------------- Hero: copy on the left, photo slider on the right ---------------- */
    const pad2 = function (n) { return (n < 10 ? '0' : '') + n; };
    function slider() {
        const SHOTS = shots();
        const n = SHOTS.length;
        if (!n) { return ''; }
        return '<div class="lp-slider anim-fade-up lp-d2" data-lp-slider data-dir="none" tabindex="0" role="region" aria-roledescription="carousel" aria-label="Photos of Kota">' +
            '<div class="lp-slider-stage"><div class="lp-slider-frame" data-lp-frame>' +
            SHOTS.map(function (s, i) {
                return '<div class="lp-slide' + (i ? '' : ' is-active') + '" title="' + e(s[1]) + '"' + (i ? ' aria-hidden="true"' : '') + '><span class="lp-slide-img" data-css-img="' + e(s[0]) + '"></span></div>';
            }).join('') +
            '<span class="lp-slider-count" aria-hidden="true"><strong data-lp-count>01</strong>/ ' + pad2(n) + '</span>' +
            '<span class="lp-slider-arrows"><button type="button" class="lp-slider-btn" data-lp-prev aria-label="Previous photo">' + icon('fa-arrow-left') + '</button>' +
            '<button type="button" class="lp-slider-btn" data-lp-next aria-label="Next photo">' + icon('fa-arrow-right') + '</button></span>' +
            '</div></div>' +
            '<div class="lp-slider-thumbs">' + SHOTS.map(function (s, i) {
                return '<button type="button" class="lp-thumb' + (i ? '' : ' is-active') + '" data-lp-go="' + i + '" aria-label="Show photo ' + (i + 1) + ' of ' + n + '"' + (i ? '' : ' aria-current="true"') + '>' +
                    '<span class="lp-thumb-img" data-css-img="' + e(s[0]) + '"></span><span class="lp-thumb-bar"><span></span></span></button>';
            }).join('') + '</div></div>';
    }
    // autoplay = the active thumbnail's progress bar (CSS): when it fills up we move on, and pausing it pauses the slider
    function initSlider(root) {
        const el = root.querySelector('[data-lp-slider]');
        if (!el) { return; }
        const slides = el.querySelectorAll('.lp-slide');
        const thumbs = el.querySelectorAll('.lp-thumb');
        const count = el.querySelector('[data-lp-count]');
        const n = slides.length;
        let cur = 0;
        function go(to, dir) {
            to = (to + n) % n;
            if (to === cur) { return; }
            el.setAttribute('data-dir', dir < 0 ? 'prev' : 'next');
            slides.forEach(function (s) { s.classList.remove('is-leaving'); });
            slides[cur].classList.replace('is-active', 'is-leaving');
            slides[cur].setAttribute('aria-hidden', 'true');
            slides[to].classList.add('is-active');
            slides[to].removeAttribute('aria-hidden');
            thumbs[cur].classList.remove('is-active');
            thumbs[cur].removeAttribute('aria-current');
            thumbs[to].classList.add('is-active');
            thumbs[to].setAttribute('aria-current', 'true');
            count.textContent = pad2(to + 1);
            count.classList.remove('is-flip');
            void count.offsetWidth;   // restart the number flip
            count.classList.add('is-flip');
            cur = to;
        }
        el.addEventListener('animationend', function (ev) {
            if (ev.animationName === 'lpProgress' && !reduceMotion) { go(cur + 1, 1); }
            // the old photo has drifted out under the wipe — hide it again
            if (/^lpOut/.test(ev.animationName)) { ev.target.classList.remove('is-leaving'); }
        });
        el.addEventListener('click', function (ev) {
            const t = ev.target.closest('[data-lp-prev], [data-lp-next], [data-lp-go]');
            if (!t) { return; }
            if (t.hasAttribute('data-lp-prev')) { go(cur - 1, -1); }
            else if (t.hasAttribute('data-lp-next')) { go(cur + 1, 1); }
            else { const i = Number(t.getAttribute('data-lp-go')); go(i, i < cur ? -1 : 1); }
        });
        el.addEventListener('keydown', function (ev) {
            if (ev.key === 'ArrowLeft') { ev.preventDefault(); go(cur - 1, -1); }
            if (ev.key === 'ArrowRight') { ev.preventDefault(); go(cur + 1, 1); }
        });
        // pause while the pointer or keyboard focus is on the slider
        const pause = function (on) { el.classList.toggle('is-paused', on); };
        el.addEventListener('mouseenter', function () { pause(true); });
        el.addEventListener('mouseleave', function () { pause(el.contains(document.activeElement)); });
        el.addEventListener('focusin', function () { pause(true); });
        el.addEventListener('focusout', function (ev) { if (!el.contains(ev.relatedTarget)) { pause(el.matches(':hover')); } });
        // swipe on touch screens
        const frame = el.querySelector('[data-lp-frame]');
        let x0 = null;
        frame.addEventListener('pointerdown', function (ev) { x0 = ev.clientX; });
        frame.addEventListener('pointercancel', function () { x0 = null; });
        frame.addEventListener('pointerup', function (ev) {
            if (x0 === null) { return; }
            const dx = ev.clientX - x0;
            x0 = null;
            if (Math.abs(dx) > 40) { go(cur + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1); }
        });
    }
    function hero() {
        const sections = DB.count('roles', { is_active: 1 });
        return '<main class="lp-hero" id="lp-main"><div class="lp-container lp-hero-grid"><div class="lp-copy">' +
            '<p class="lp-eyebrow anim-fade-up"><span class="lp-eyebrow-tag">' + icon('fa-shield-halved') + ' ' + e(P.APP.org_short) + '</span>' + e(S('hero_eyebrow', '')) + '</p>' +
            '<h1 class="lp-title anim-fade-up lp-d1">' + e(S('hero_line1', '')) + '<br><span class="lp-accent">' + e(S('hero_line2', '')) + '<br>' + e(S('hero_line3', '')) + '</span></h1>' +
            '<p class="lp-lead anim-fade-up lp-d2"><strong>' + e(P.APP.name) + '</strong> brings Data Entry, OIC Auction and ' + (sections ? 'all ' + sections : 'every') +
            ' verification sections of the ' + e(P.APP.org) + ' onto one secure, fully audited workflow.</p>' +
            '<div class="lp-cta anim-fade-up lp-d3">' +
            '<button type="button" class="lp-btn lp-btn-primary lp-btn-lg" data-lp-signin aria-haspopup="dialog" aria-controls="lp-signin">' + icon('fa-right-to-bracket') + ' <span>Sign in to ' + e(P.APP.name) + '</span></button>' +
            '</div></div>' +
            slider() +
            '</div></main>';
    }

    /* ---------------- Sign-in popup ---------------- */
    function signinModal() {
        return '<div class="lp-modal" id="lp-signin" hidden data-lp-modal><div class="lp-modal-backdrop" data-lp-close></div>' +
            '<div class="lp-modal-dialog lp-card" role="dialog" aria-modal="true" aria-labelledby="lp-signin-title" tabindex="-1">' +
            '<button type="button" class="lp-modal-close" data-lp-close aria-label="Close">' + icon('fa-xmark') + '</button>' +
            // left: brand panel
            '<aside class="lp-auth-side"><div class="lp-auth-brand"><span class="lp-auth-logo">' + EMBLEM + '</span>' +
            '<span class="lp-auth-name"><strong>' + e(P.APP.name) + '</strong><small>' + e(P.APP.tagline) + '</small></span>' +
            '<p class="lp-auth-org">' + e(P.APP.org) + '</p></div>' +
            '<ul class="lp-auth-points">' +
            '<li>' + icon('fa-shield-halved') + '<div><strong>Secure, role-based access</strong><span>Every panel sees only its own work. Sessions are encrypted and time out.</span></div></li>' +
            '<li>' + icon('fa-list-check') + '<div><strong>Multi-section verification</strong><span>All sections clear a property before it is auction-ready.</span></div></li>' +
            '<li>' + icon('fa-clock-rotate-left') + '<div><strong>Fully audited</strong><span>Every action is recorded with user, time and details.</span></div></li></ul>' +
            '<span class="lp-auth-note">' + icon('fa-lock') + ' ' + e(S('header_note', '')) + '</span></aside>' +
            // right: the form
            '<div class="lp-auth-form"><div class="lp-card-head"><span class="lp-card-eyebrow">' + icon('fa-hand') + ' Welcome back</span>' +
            '<h2 id="lp-signin-title">Sign in to your account</h2><p>Use your <strong>' + e(P.APP.name) + '</strong> username and password.</p></div>' +
            P.views.login() +
            '<p class="lp-auth-secure">' + icon('fa-circle-check') + ' Encrypted session · repeated wrong passwords lock the account</p></div></div></div>';
    }

    /* ---------------- Footer: brand · copyright · KDA website ---------------- */
    function footer() {
        return '<footer class="lp-footer" role="contentinfo"><div class="lp-container lp-footer-bar">' +
            '<p class="lp-footer-copy"><span>&copy; ' + new Date().getFullYear() + ' <strong>' + e(P.APP.org) + '</strong>,</span> <span>' + e(S('copyright_note', '')) + '</span></p>' +
            '<a class="lp-footer-link" href="' + e(site()) + '" target="_blank" rel="noopener noreferrer">' + e(site().replace(/^https?:\/\//i, '').replace(/\/$/, '')) + ' ' + icon('fa-arrow-up-right-from-square') + '</a>' +
            '</div></footer>';
    }

    /* ---------------- Behaviour ---------------- */
    function headerEl() { return document.querySelector('[data-lp-header]'); }
    function syncHeader() {
        const h = headerEl();
        if (h) { h.classList.toggle('is-solid', window.scrollY > 12); }
    }

    let lastFocus = null;
    let closeTimer = 0;
    function modalEl() { return document.querySelector('[data-lp-modal]'); }
    function openModal() {
        const m = modalEl();
        if (!m || m.classList.contains('is-open')) { return; }
        window.clearTimeout(closeTimer);
        lastFocus = document.activeElement;
        m.hidden = false;
        void m.offsetWidth;   // start the transition from the closed state
        m.classList.add('is-open');
        document.documentElement.classList.add('lp-lock');
        // mouse/keyboard: cursor straight into Username; touch: focus the dialog so the keyboard does not pop up
        const fine = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        const input = m.querySelector('#username');
        (fine && input ? input : m.querySelector('.lp-modal-dialog')).focus({ preventScroll: true });
    }
    function closeModal() {
        const m = modalEl();
        if (!m || !m.classList.contains('is-open')) { return; }
        m.classList.remove('is-open');
        document.documentElement.classList.remove('lp-lock');
        closeTimer = window.setTimeout(function () { m.hidden = true; }, reduceMotion ? 0 : 320);
        if (lastFocus && lastFocus !== document.body && document.contains(lastFocus) && lastFocus.focus) { lastFocus.focus({ preventScroll: true }); }
        else if (m.contains(document.activeElement)) { document.activeElement.blur(); }   // opened by itself: do not leave focus in the hidden form
        // #/login → #/ so a reload does not reopen it (replaceState fires no hashchange)
        if (window.location.hash.indexOf('#/login') === 0 && window.history.replaceState) { window.history.replaceState(null, '', '#/'); }
    }

    window.addEventListener('scroll', syncHeader, { passive: true });
    // leaving the landing page (e.g. signed in) must not keep the page scroll locked
    window.addEventListener('hashchange', function () {
        if (!document.querySelector('.lp-modal.is-open')) { document.documentElement.classList.remove('lp-lock'); }
    });
    document.addEventListener('click', function (ev) {
        if (!headerEl()) { return; }
        if (ev.target.closest('[data-lp-signin]')) { ev.preventDefault(); openModal(); return; }
        if (ev.target.closest('[data-lp-close]')) { ev.preventDefault(); closeModal(); return; }
        if (ev.target.closest('[data-lp-top]')) { ev.preventDefault(); window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' }); }
    });
    // Esc closes; Tab stays inside the popup
    document.addEventListener('keydown', function (ev) {
        const m = document.querySelector('.lp-modal.is-open');
        if (!m) { return; }
        if (document.querySelector('#confirm-modal:not([hidden])')) { return; }   // a confirm dialog on top handles its own keys
        if (ev.key === 'Escape') { ev.preventDefault(); closeModal(); return; }
        if (ev.key !== 'Tab') { return; }
        const f = Array.prototype.filter.call(m.querySelectorAll('a[href], button:not([disabled]), input:not([disabled])'), function (el) { return el.offsetParent !== null; });
        if (!f.length) { return; }
        const first = f[0], last = f[f.length - 1], active = document.activeElement;
        if (ev.shiftKey && (active === first || !m.contains(active) || active === m.querySelector('.lp-modal-dialog'))) { ev.preventDefault(); last.focus(); }
        else if (!ev.shiftKey && (active === last || !m.contains(active))) { ev.preventDefault(); first.focus(); }
    });

    P.views.landing = function () {
        return {
            title: P.APP.name + ' | ' + P.APP.org,
            html: '<div class="lp">' + header() + hero() + footer() + signinModal() + '</div>',
            after: function (root) {
                syncHeader();
                initSlider(root);
                // a waiting flash message (rendered inside the popup) opens it straight away
                if (root.querySelector('[data-lp-modal] [data-flash]')) { openModal(); }
            }
        };
    };
})(window, document);
