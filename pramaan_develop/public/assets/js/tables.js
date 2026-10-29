/* ===== js/tables.js ===== */
/* PRAMAAN — mobile tables: show a "swipe" hint above tables that scroll sideways; hide it once scrolled to the end. */
(function (window, document) {
    'use strict';
    const P = window.Praman;

    function sync(wrap) {
        const hint = wrap.previousElementSibling && wrap.previousElementSibling.classList.contains('table-scroll-hint') ? wrap.previousElementSibling : null;
        if (!hint) { return; }
        const overflow = wrap.scrollWidth - wrap.clientWidth > 4;
        const atEnd = wrap.scrollLeft + wrap.clientWidth >= wrap.scrollWidth - 4;
        hint.hidden = !overflow || atEnd;
    }
    function init(root) {
        root.querySelectorAll('.table-wrap').forEach(function (wrap) {
            if (!wrap.querySelector('table')) { return; }
            if (!(wrap.previousElementSibling && wrap.previousElementSibling.classList.contains('table-scroll-hint'))) {
                const hint = document.createElement('div');
                hint.className = 'table-scroll-hint';
                hint.innerHTML = '<span>Swipe to see more columns</span> <i class="fa-solid fa-hand-point-right" aria-hidden="true"></i>';
                wrap.parentNode.insertBefore(hint, wrap);
            }
            wrap.addEventListener('scroll', function () { sync(wrap); }, { passive: true });
            sync(wrap);
        });
    }
    const prev = P.initPage;
    P.initPage = function (root) {
        if (prev) { prev(root); }
        init(root || document);
    };
    window.addEventListener('resize', function () { document.querySelectorAll('.table-wrap').forEach(sync); });
})(window, document);
