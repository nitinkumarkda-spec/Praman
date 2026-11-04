/* ===== js/pages/oic.js ===== */
/* PRAMAAN — OIC property routes point to unified Property Register */
(function (window) {
    'use strict';
    const P = window.Praman;
    if (P.registerPropertyScreens) {
        P.registerPropertyScreens('/oic/properties', null, null);
    }
})(window);