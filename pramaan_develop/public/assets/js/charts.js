/* ===== js/charts.js ===== */
/* =====================================================================
   PRAMAAN — chart helpers (Chart.js 4, served locally).
   P.chartCard() renders the card + canvas, P.makeChart() draws it after
   the page is in the DOM. Charts are destroyed before every re-render.
   ===================================================================== */
(function (window) {
    'use strict';
    const P = window.Praman;
    const live = [];

    P.COLORS = {
        navy: '#4a72b0', gold: '#e3b55b', green: '#4fae8a', red: '#e07a7a', amber: '#f0a85c', blue: '#6b9bd8',
        slate: '#b8c2d1', purple: '#9b8ad6', teal: '#5bb3b0', pink: '#e49ab8', dark: '#7d8aa0', sky: '#7cc4e4'
    };
    const C = P.COLORS;
    P.SERIES = [C.navy, C.gold, C.green, C.blue, C.purple, C.teal, C.red, C.amber, C.pink, C.sky, C.slate, C.dark];
    P.STATUS_COLORS = {
        LISTED: C.blue, UNDER_VERIFICATION: C.amber, AUCTION_READY: C.green, NOT_READY: C.red, EXPIRED: '#f97316',
        SENT_FOR_AUCTION: C.navy, SOLD: C.purple, WITHHELD: C.slate, ARCHIVED: '#dde3ea'
    };

    /** Card with a canvas. opts: { height, sub, href, wide } */
    P.chartCard = function (id, title, ic, opts) {
        opts = opts || {};
        return '<div class="card chart-card' + (opts.wide ? ' chart-wide' : '') + '"><div class="card-header"><h3 class="card-title">' + P.icon(ic) + ' ' + P.e(title) + '</h3>' +
            (opts.sub ? '<span class="text-muted small">' + opts.sub + '</span>' : '') +
            (opts.href ? '<a href="' + P.e(opts.href) + '" class="small">View ' + P.icon('fa-arrow-right') + '</a>' : '') +
            '</div><div class="card-body"><div class="chart-box" data-css-h="' + (opts.height || 260) + '"><canvas id="' + id + '" role="img" aria-label="' + P.e(title) + '"></canvas>' +
            '<p class="chart-empty" hidden>' + P.icon('fa-chart-pie') + ' No data yet</p></div></div></div>';
    };

    P.makeChart = function (id, cfg, isEmpty) {
        const canvas = document.getElementById(id);
        if (!canvas) { return null; }
        if (isEmpty) {
            canvas.hidden = true;
            canvas.parentNode.querySelector('.chart-empty').hidden = false;
            return null;
        }
        if (!window.Chart) {
            const msg = canvas.parentNode.querySelector('.chart-empty');
            msg.hidden = false;
            msg.innerHTML = P.icon('fa-wifi') + ' Charts need internet (Chart.js CDN).';
            canvas.hidden = true;
            return null;
        }
        const chart = new window.Chart(canvas, cfg);
        live.push(chart);
        return chart;
    };
    P.destroyCharts = function () {
        while (live.length) { try { live.pop().destroy(); } catch (e) { /* ignore */ } }
    };

    /* ---------- ready-made configs ---------- */
    if (window.Chart) {
        window.Chart.defaults.font.family = getComputedStyle(document.body).fontFamily || 'system-ui, sans-serif';
        window.Chart.defaults.font.size = 12;
        window.Chart.defaults.color = '#64748b';
        window.Chart.defaults.borderColor = '#eef2f7';
        window.Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(30, 41, 59, .92)';
        window.Chart.defaults.plugins.tooltip.cornerRadius = 6;
    }
    function base(extra) {
        return Object.assign({
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 14 } }, tooltip: { padding: 10 } }
        }, extra || {});
    }
    P.pie = function (id, labels, data, colors, doughnut, onClick) {
        const total = data.reduce(function (s, v) { return s + v; }, 0);
        return P.makeChart(id, {
            type: doughnut === false ? 'pie' : 'doughnut',
            data: { labels: labels, datasets: [{ data: data, backgroundColor: colors || P.SERIES, borderColor: '#fff', borderWidth: 3, hoverOffset: 6 }] },
            options: base({
                cutout: doughnut === false ? 0 : '62%',
                onClick: onClick ? function (ev, els) { if (els.length) { onClick(els[0].index); } } : undefined,
                onHover: onClick ? function (ev, els) { ev.native.target.style.cursor = els.length ? 'pointer' : 'default'; } : undefined,
                plugins: {
                    legend: { position: 'right', labels: { usePointStyle: true, boxWidth: 8, padding: 10 } },
                    tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + c.parsed + ' (' + (total ? Math.round(c.parsed * 100 / total) : 0) + '%)'; } } }
                }
            })
        }, total === 0);
    };
    P.bars = function (id, labels, datasets, opts) {
        opts = opts || {};
        const empty = datasets.every(function (d) { return d.data.every(function (v) { return !v; }); });
        return P.makeChart(id, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: datasets.map(function (d, i) {
                    return Object.assign({ backgroundColor: P.SERIES[i], borderRadius: 4, maxBarThickness: 28 }, d);
                })
            },
            options: base({
                indexAxis: opts.horizontal ? 'y' : 'x',
                onClick: opts.onClick ? function (ev, els) { if (els.length) { opts.onClick(els[0].index); } } : undefined,
                scales: {
                    x: { stacked: !!opts.stacked, grid: { display: !!opts.horizontal }, ticks: { precision: 0 } },
                    y: { stacked: !!opts.stacked, beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { precision: 0 } }
                },
                plugins: { legend: { display: datasets.length > 1, position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } }
            })
        }, empty);
    };
    P.lines = function (id, labels, datasets) {
        const empty = datasets.every(function (d) { return d.data.every(function (v) { return !v; }); });
        return P.makeChart(id, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets.map(function (d, i) {
                    const c = d.color || P.SERIES[i];
                    return Object.assign({ borderColor: c, backgroundColor: c + '1a', fill: true, tension: .35, pointRadius: 2, pointHoverRadius: 6, borderWidth: 2 }, d);
                })
            },
            options: base({
                interaction: { mode: 'index', intersect: false },
                scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: { color: '#eef2f7' }, ticks: { precision: 0 } } }
            })
        }, empty);
    };

    /** Last n days as ['YYYY-MM-DD', ...] and short labels. */
    P.lastDays = function (n) {
        const keys = [], labels = [];
        for (let i = n - 1; i >= 0; i--) {
            const k = P.DB.today(-i);
            keys.push(k);
            const d = new Date(k + 'T00:00:00');
            labels.push(d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }));
        }
        return { keys: keys, labels: labels };
    };
    P.countByDay = function (rows, field, keys, filter) {
        const m = {};
        rows.forEach(function (r) { if (!filter || filter(r)) { const k = String(r[field] || '').slice(0, 10); m[k] = (m[k] || 0) + 1; } });
        return keys.map(function (k) { return m[k] || 0; });
    };
})(window);
