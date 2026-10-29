/* =====================================================================
   PRAMAAN — app loader ("gate opening"). Markup: app/Views/partials/loader.php,
   styles: css/loader.css.
     Praman.loader.done()        first screen is drawn → finish + open the gates
     Praman.loader.show(message) cover the screen again (sign in / out, login-as)
   The loader stays at least MIN_MS so the animation is never cut, and opens by
   itself after FAILSAFE_MS so a slow script can never lock the screen.
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};
    const el = document.getElementById('app-loader');
    const root = document.documentElement;
    const reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
    const MIN_MS = reduce ? 200 : 2300;      // first load: letters + tagline have time to play
    const SWITCH_MIN_MS = reduce ? 150 : 900; // sign in / out
    const FAILSAFE_MS = 9000;
    const OPEN_MS = reduce ? 320 : 1250;      // gate animation length (css/loader.css)
    const STEPS = [
        [0, 'Starting secure session…'], [22, 'Loading property records…'], [48, 'Checking verification sections…'],
        [74, 'Preparing your dashboard…'], [100, 'Verified — opening']
    ];

    if (!el) {
        Praman.loader = { done: function () {}, show: function () {}, hide: function () {} };
        return;
    }
    const bar = el.querySelector('[data-pl-bar]');
    const pct = el.querySelector('[data-pl-pct]');
    const msg = el.querySelector('[data-pl-msg]');

    let shownAt = Date.now();
    let minMs = MIN_MS;
    let value = 0;
    let target = 88;      // creeps towards this until done()
    let raf = 0;
    let finishing = false;
    let fixedMessage = null;
    let failsafe = 0;

    function setMessage(text) {
        if (msg.textContent === text) { return; }
        msg.classList.add('is-swap');
        window.setTimeout(function () { msg.textContent = text; msg.classList.remove('is-swap'); }, 180);
    }
    function paint() {
        const v = Math.min(100, Math.round(value));
        bar.style.width = v + '%';
        pct.textContent = v + '%';
        if (!fixedMessage || v === 100) {
            let text = STEPS[0][1];
            STEPS.forEach(function (s) { if (v >= s[0]) { text = s[1]; } });
            setMessage(text);
        }
    }
    function tick() {
        // ease towards the target: fast at first, slower near it
        value += Math.max(0.15, (target - value) * 0.035);
        if (value > target) { value = target; }
        paint();
        if (value < 100) { raf = window.requestAnimationFrame(tick); }
    }

    function open() {
        el.classList.add('is-done');
        window.setTimeout(function () {
            el.classList.add('is-opening');
            root.classList.remove('pl-lock');
            window.setTimeout(function () {
                el.hidden = true;
                el.classList.remove('is-done', 'is-opening');
                finishing = false;
            }, OPEN_MS);
        }, reduce ? 0 : 620); // let the tick stamp in and draw first
    }

    function done() {
        if (finishing || el.hidden) { return; }
        finishing = true;
        window.clearTimeout(failsafe);
        const wait = Math.max(0, minMs - (Date.now() - shownAt));
        window.setTimeout(function () {
            target = 100;
            window.cancelAnimationFrame(raf);
            const start = value;
            const t0 = performance.now();
            (function run(now) { // finish the bar in ~350 ms
                const k = Math.min(1, (now - t0) / 350);
                value = start + (100 - start) * k;
                paint();
                if (k < 1) { window.requestAnimationFrame(run); } else { open(); }
            })(t0);
        }, wait);
    }

    function start(message, min) {
        window.cancelAnimationFrame(raf);
        window.clearTimeout(failsafe);
        shownAt = Date.now();
        minMs = min;
        value = 0;
        target = 88;
        finishing = false;
        fixedMessage = message || null;
        el.classList.remove('is-done', 'is-opening');
        el.hidden = false;
        root.classList.add('pl-lock');
        msg.textContent = message || STEPS[0][1];
        paint();
        raf = window.requestAnimationFrame(tick);
        failsafe = window.setTimeout(done, FAILSAFE_MS);
    }

    Praman.loader = {
        done: done,
        /** Cover the screen (e.g. while switching user); call done() after the next screen is drawn. */
        show: function (message) { start(message, SWITCH_MIN_MS); },
        hide: done
    };

    // page load: play only when the server rendered it visible (guest); a signed-in refresh opens the dashboard directly
    if (!el.hidden) { start(null, MIN_MS); }
})(window, document);
