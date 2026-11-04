/* ===== js/pages/reports.js ===== */
/* =====================================================================
   PRAMAAN — Reports & Analytics Module
   - Tabbed / Wizard Structure:
     1. Auction Ready Properties Report
     2. Scheme & Sector Inventory Report
     3. Verification Cell & SLA Performance Report
     4. Objections & Rejection Analysis Report
     5. Custom Report Builder (Wizard / Interactive Generator)
   - Tabular and Cards Views toggle
   - Graphically represented with Chart.js charts
   - Export options: CSV Download, Print / Save PDF
   ===================================================================== */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups, C = P.COLORS;
    const ST = P.ST;

    /* ---------------- Global Export Utilities ---------------- */
    function downloadCsv(filename, headers, rows) {
        let csv = '\uFEFF'; // UTF-8 BOM so Excel opens Hindi & symbols cleanly
        csv += headers.map(function (h) { return '"' + String(h || '').replace(/"/g, '""') + '"'; }).join(',') + '\r\n';
        rows.forEach(function (row) {
            csv += row.map(function (cell) { return '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"'; }).join(',') + '\r\n';
        });
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.setAttribute('download', filename.replace(/\s+/g, '_') + '.csv');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    function printReportSheet(title, subtitle, kpisHtml, tableHtml) {
        const u = Auth.user() || { name: 'Admin', role_name: 'Admin' };
        const d = new Date();
        const dateStr = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' + d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });

        const oldPrint = document.getElementById('report-print-target');
        if (oldPrint) { oldPrint.remove(); }

        const container = document.createElement('div');
        container.id = 'report-print-target';
        container.className = 'report-print-sheet';
        container.innerHTML = '<div class="report-print-header">' +
            '<div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid #0f2b52;padding-bottom:10px;margin-bottom:14px;">' +
            '<div><h2 style="margin:0;color:#0f2b52;font-size:20px;">' + e(P.APP.name) + ' — ' + e(P.APP.tagline) + '</h2>' +
            '<h3 style="margin:4px 0 0;font-size:16px;color:#1e293b;">' + e(title) + '</h3>' +
            (subtitle ? '<p style="margin:2px 0 0;font-size:12px;color:#64748b;">' + e(subtitle) + '</p>' : '') + '</div>' +
            '<div style="text-align:right;font-size:11.5px;color:#64748b;">' +
            '<div>Generated: <strong>' + dateStr + '</strong></div>' +
            '<div>By: <strong>' + e(u.name) + '</strong> (' + e(u.role_name || 'Staff') + ')</div></div></div>' +
            (kpisHtml ? '<div style="margin-bottom:14px;">' + kpisHtml + '</div>' : '') +
            '<div class="table-wrap">' + tableHtml + '</div></div>';

        document.body.appendChild(container);
        window.print();
        window.setTimeout(function () { container.remove(); }, 1000);
    }

    /* ---------------- View Mode Buttons (Table / Cards) ---------------- */
    function renderViewToggle(currentView, queryObj, path) {
        const isCards = currentView === 'cards';
        return '<div class="view-toggle" role="group" aria-label="View mode">' +
            '<a href="' + P.url(path, Object.assign({}, queryObj, { view: 'table' })) + '" class="view-toggle-btn' + (!isCards ? ' active' : '') + '" title="Tabular View">' + icon('fa-table') + ' <span>Table</span></a>' +
            '<a href="' + P.url(path, Object.assign({}, queryObj, { view: 'cards' })) + '" class="view-toggle-btn' + (isCards ? ' active' : '') + '" title="Cards View">' + icon('fa-grip') + ' <span>Cards</span></a>' +
            '</div>';
    }

    /* ---------------- Export Buttons ---------------- */
    function renderExportButtons(btnIdPrefix) {
        return '<div class="report-actions">' +
            '<button type="button" class="btn btn-light btn-sm" id="' + btnIdPrefix + '-csv-btn">' + icon('fa-file-csv') + ' <span>Export CSV</span></button>' +
            '<button type="button" class="btn btn-light btn-sm" id="' + btnIdPrefix + '-print-btn">' + icon('fa-print') + ' <span>Print / PDF</span></button>' +
            '</div>';
    }

    /* ---------------- Tab Navigation Bar ---------------- */
    const TABS = [
        { id: 'auction', label: 'Auction Ready', icon: 'fa-gavel', sub: 'Cleared plots' },
        { id: 'inventory', label: 'Scheme Inventory', icon: 'fa-map-location-dot', sub: 'Land distribution' },
        { id: 'sla', label: 'Verification & SLA', icon: 'fa-clock-rotate-left', sub: 'Cell performance' },
        { id: 'deficiency', label: 'Objection Analysis', icon: 'fa-triangle-exclamation', sub: 'Reasons for rejection' },
        { id: 'custom', label: 'Custom Report Builder', icon: 'fa-wand-magic-sparkles', sub: 'Interactive wizard' }
    ];

    function renderTabs(activeTab, queryObj) {
        return '<nav class="report-tabs" aria-label="Reports navigation">' +
            TABS.map(function (tab) {
                const on = tab.id === activeTab;
                const href = P.url('/admin/reports', Object.assign({}, queryObj, { tab: tab.id, page: 1 }));
                return '<a href="' + href + '" class="report-tab' + (on ? ' active' : '') + '">' +
                    icon(tab.icon) + ' <span>' + tab.label + '</span>' + '</a>';
            }).join('') + '</nav>';
    }

    /* =========================================================================
       REPORT 1: Auction Ready Properties Report
       ========================================================================= */
    function reportAuctionReady(ctx) {
        const q = ctx.query;
        const viewMode = q.view || 'table';

        // Filter only properties that reached Auction Ready, Sent for Auction, or Sold
        let allReady = P.propertyRows().filter(function (p) {
            return p.status_id === ST.AUCTION_READY || p.status_id === ST.SENT_FOR_AUCTION || p.status_id === ST.SOLD;
        });

        // Apply filters
        if (q.scheme_id) { allReady = allReady.filter(function (p) { return String(p.scheme_id) === q.scheme_id; }); }
        if (q.sector_id) { allReady = allReady.filter(function (p) { return String(p.sector_id) === q.sector_id; }); }
        if (q.type) { allReady = allReady.filter(function (p) { return String(p.plot_type_id) === q.type; }); }
        if (q.validity === 'DUE') {
            allReady = allReady.filter(function (p) { return DB.Verification.isDue(p); });
        } else if (q.validity === 'ACTIVE') {
            allReady = allReady.filter(function (p) { return !DB.Verification.isDue(p) && (!p.valid_till || p.valid_till >= DB.today()); });
        }

        // Aggregate KPIs
        const totalCount = allReady.length;
        const totalAreaSqm = allReady.reduce(function (sum, p) { return sum + (Number(p.area_sqm) || 0); }, 0);
        const totalReserveVal = allReady.reduce(function (sum, p) { return sum + (Number(p.reserve_price) || 0); }, 0);
        const dueCount = allReady.filter(function (p) { return DB.Verification.isDue(p); }).length;

        // Scheme & Sector Filter Dropdowns
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name; });
        const sectors = {};
        if (q.scheme_id) { DB.Scheme.sectors(q.scheme_id).forEach(function (s) { sectors[s.sector_id] = s.sector_name; }); }

        const filterBar = '<form method="get" data-path="/admin/reports" class="card ins-filter-card mb-3"><div class="card-body">' +
            '<input type="hidden" name="tab" value="auction">' +
            '<input type="hidden" name="view" value="' + e(viewMode) + '">' +
            '<div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">' +
            '<div class="form-group"><label class="form-label">Scheme</label><select name="scheme_id" class="form-select" data-auto-submit data-reset-on-change="sector_id">' + L.options(schemes, q.scheme_id, 'All Schemes') + '</select></div>' +
            '<div class="form-group"><label class="form-label">Sector</label><select name="sector_id" class="form-select" data-auto-submit' + (!Object.keys(sectors).length ? ' disabled' : '') + '>' + L.options(sectors, q.sector_id, !Object.keys(sectors).length ? 'Select scheme first' : 'All Sectors') + '</select></div>' +
            '<div class="form-group"><label class="form-label">Plot Type</label><select name="type" class="form-select" data-auto-submit>' + L.options(L.PROPERTY_TYPES, q.type, 'All Plot Types') + '</select></div>' +
            '<div class="form-group"><label class="form-label">Validity Status</label><select name="validity" class="form-select" data-auto-submit>' + L.options({ ACTIVE: 'Valid & Ready', DUE: '⚠ Re-verify Due (<30 days)' }, q.validity, 'All Validity') + '</select></div>' +
            '</div></div></form>';

        // Stat Grid
        const statsHtml = '<div class="stat-grid dash-stats mb-3">' +
            P.statCard('green', 'fa-gavel', totalCount, 'Auction Ready Plots', 'cleared for auction', P.url('/admin/reports', Object.assign({}, q, { validity: '' }))) +
            P.statCard('gold', 'fa-ruler-combined', P.fmt_num(totalAreaSqm, 2) + ' sq.m', 'Total Land Area', 'across ' + totalCount + ' plots', '') +
            P.statCard('blue', 'fa-indian-rupee-sign', P.fmt_rupees(totalReserveVal), 'Total Reserve Value', 'evaluated price', '') +
            P.statCard('red', 'fa-calendar-day', dueCount, 'Re-verify Due', 'validity expires soon', P.url('/admin/reports', Object.assign({}, q, { validity: 'DUE' }))) +
            '</div>';

        // Charts Grid
        const chartsHtml = '<div class="grid-2 mb-3">' +
            P.chartCard('rep-ch-auction-scheme', 'Auction Ready Plots by Scheme', 'fa-chart-column', { height: 250 }) +
            P.chartCard('rep-ch-auction-type', 'Land Use / Plot Type Share', 'fa-chart-pie', { height: 250 }) +
            '</div>';

        // Data Rows / Content
        let contentHtml = '';
        if (viewMode === 'cards') {
            contentHtml = '<div class="report-cards-grid">' +
                (allReady.length ? allReady.map(function (p) {
                    const due = DB.Verification.isDue(p);
                    const viewUrl = P.BASE + '/admin/masters/properties/' + p.property_id;
                    return '<div class="report-prop-card' + (due ? ' is-due' : ' is-ready') + '">' +
                        '<div class="report-card-top">' +
                        '<div><span class="report-card-plot">Plot ' + e(p.plot_no) + '</span>' + (p.is_corner ? ' <span class="badge badge-warning">Corner</span>' : '') + '</div>' +
                        P.status_badge(p.status_id) + '</div>' +
                        '<div class="report-card-scheme">' + e(p.scheme_name) + '</div>' +
                        '<div class="report-card-sector">Sector: ' + e(p.sector_name || 'Main') + ' · ' + e(p.type_name) + '</div>' +
                        '<dl class="report-card-details">' +
                        '<div class="report-card-detail"><dt>Area</dt><dd>' + P.areaCell(p) + '</dd></div>' +
                        '<div class="report-card-detail"><dt>Reserve Price</dt><dd>' + (p.reserve_price ? P.fmt_rupees(p.reserve_price) : '—') + '</dd></div>' +
                        '<div class="report-card-detail"><dt>Valid Till</dt><dd>' + (p.valid_till ? P.fmt_date(p.valid_till) : '—') + '</dd></div>' +
                        '<div class="report-card-detail"><dt>Round</dt><dd>Round R' + (p.round_no || 1) + '</dd></div>' +
                        '</dl>' +
                        '<div class="report-card-foot">' +
                        '<span class="text-muted small">' + (due ? '<strong class="text-danger">Re-verify due</strong>' : 'Ready') + '</span>' +
                        '<a href="' + viewUrl + '" class="btn btn-light btn-sm">' + icon('fa-eye') + ' <span>View Details</span></a>' +
                        '</div></div>';
                }).join('') : P.empty_state(4, true, 'No auction ready properties matched the filters')) +
                '</div>';
        } else {
            contentHtml = '<div class="table-wrap"><table class="table table-stack report-table" id="auction-report-table"><thead><tr>' +
                '<th class="cell-no">#</th><th>Plot No.</th><th>Scheme</th><th>Sector</th><th>Plot Type</th>' +
                '<th>Area</th><th>Reserve Price (₹)</th><th>Round</th><th>Valid Till</th><th>Validity Status</th><th class="text-right">Action</th>' +
                '</tr></thead><tbody>' +
                (allReady.length ? allReady.map(function (p, i) {
                    const due = DB.Verification.isDue(p);
                    const viewUrl = P.BASE + '/admin/masters/properties/' + p.property_id;
                    return '<tr class="' + (due ? 'row-warning' : 'row-success') + '">' +
                        '<td class="cell-no">' + (i + 1) + '</td>' +
                        '<td><a href="' + viewUrl + '"><strong>' + e(p.plot_no) + '</strong></a>' + (p.is_corner ? ' <span class="badge badge-warning">Corner</span>' : '') + '</td>' +
                        '<td>' + e(p.scheme_name) + '</td><td>' + e(p.sector_name || '—') + '</td><td>' + e(p.type_name) + '</td>' +
                        '<td>' + P.areaCell(p) + '</td>' +
                        '<td><strong>' + (p.reserve_price ? P.fmt_rupees(p.reserve_price) : '—') + '</strong></td>' +
                        '<td>R' + (p.round_no || 1) + '</td>' +
                        '<td>' + (p.valid_till ? P.fmt_date(p.valid_till) : '—') + '</td>' +
                        '<td>' + (due ? '<span class="badge badge-danger">Re-verify Due</span>' : '<span class="badge badge-success">Valid</span>') + '</td>' +
                        '<td class="text-right"><a href="' + viewUrl + '" class="btn btn-light btn-sm btn-icon-only" title="View details">' + icon('fa-eye') + '</a></td>' +
                        '</tr>';
                }).join('') : P.empty_state(11, true, 'No auction ready properties matched')) +
                '</tbody>' +
                (allReady.length ? '<tfoot><tr><th colspan="5">Total (' + totalCount + ' plots)</th>' +
                    '<th>' + P.fmt_num(totalAreaSqm, 2) + ' sq.m</th><th>' + P.fmt_rupees(totalReserveVal) + '</th>' +
                    '<th colspan="4" class="text-right">' + dueCount + ' due for re-verify</th></tr></tfoot>' : '') +
                '</table></div>';
        }

        const toolbarHtml = '<div class="report-controls">' +
            '<div><strong>' + totalCount + '</strong> properties matched · <strong>' + P.fmt_num(totalAreaSqm, 2) + ' sq.m</strong> land</div>' +
            '<div class="report-actions">' +
            renderViewToggle(viewMode, q, '/admin/reports') +
            renderExportButtons('rep-auction') +
            '</div></div>';

        return {
            title: 'Auction Ready Properties Report',
            html: filterBar + statsHtml + chartsHtml + '<div class="card p-3">' + toolbarHtml + contentHtml + '</div>',
            after: function () {
                // Draw Charts
                const schemeMap = {};
                const typeMap = {};
                allReady.forEach(function (p) {
                    schemeMap[p.scheme_name] = (schemeMap[p.scheme_name] || 0) + 1;
                    typeMap[p.type_name] = (typeMap[p.type_name] || 0) + 1;
                });
                const schLabels = Object.keys(schemeMap);
                const schData = schLabels.map(function (k) { return schemeMap[k]; });
                P.bars('rep-ch-auction-scheme', schLabels, [{ label: 'Ready Plots', data: schData, backgroundColor: C.green }]);

                const typLabels = Object.keys(typeMap);
                const typData = typLabels.map(function (k) { return typeMap[k]; });
                P.pie('rep-ch-auction-type', typLabels, typData, [C.navy, C.gold, C.green, C.blue, C.purple, C.teal, C.red], true);

                // Wire Export buttons
                const csvBtn = document.getElementById('rep-auction-csv-btn');
                if (csvBtn) {
                    csvBtn.onclick = function () {
                        const headers = ['S.No', 'Plot No.', 'Corner', 'Scheme', 'Sector', 'Plot Type', 'Area Value', 'Unit', 'Area Sq.m', 'Reserve Price', 'Round', 'Valid Till', 'Status'];
                        const rows = allReady.map(function (p, i) {
                            return [
                                i + 1, p.plot_no, p.is_corner ? 'Yes' : 'No', p.scheme_name, p.sector_name, p.type_name,
                                p.area_value, L.unitName(p.unit_id), p.area_sqm, p.reserve_price, 'R' + (p.round_no || 1), p.valid_till,
                                DB.Verification.isDue(p) ? 'Re-verify Due' : 'Auction Ready'
                            ];
                        });
                        downloadCsv('Auction_Ready_Properties_' + DB.today(), headers, rows);
                    };
                }
                const printBtn = document.getElementById('rep-auction-print-btn');
                if (printBtn) {
                    printBtn.onclick = function () {
                        const tableElem = document.getElementById('auction-report-table');
                        const tableHtml = tableElem ? tableElem.outerHTML : contentHtml;
                        printReportSheet('Auction Ready Properties Report', 'Kota Development Authority — Land Disposal & Verification Summary', statsHtml, tableHtml);
                    };
                }
            }
        };
    }

    /* =========================================================================
       REPORT 2: Scheme & Sector Inventory Report
       ========================================================================= */
    function reportInventory(ctx) {
        const q = ctx.query;
        const schemes = DB.all('schemes');
        const sectors = DB.all('sectors');
        const properties = P.propertyRows();

        const rows = schemes.map(function (s) {
            const schSectors = sectors.filter(function (sec) { return sec.scheme_id === s.scheme_id; });
            const schProps = properties.filter(function (p) { return p.scheme_id === s.scheme_id; });
            const total = schProps.length;
            const listed = schProps.filter(function (p) { return p.status_id === ST.LISTED; }).length;
            const underVf = schProps.filter(function (p) { return p.status_id === ST.UNDER_VERIFICATION; }).length;
            const ready = schProps.filter(function (p) { return p.status_id === ST.AUCTION_READY; }).length;
            const notReady = schProps.filter(function (p) { return p.status_id === ST.NOT_READY; }).length;
            const expired = schProps.filter(function (p) { return p.status_id === ST.EXPIRED; }).length;
            const sold = schProps.filter(function (p) { return p.status_id === ST.SOLD || p.status_id === ST.SENT_FOR_AUCTION; }).length;
            const readyPct = total ? Math.round((ready / total) * 100) : 0;
            return {
                scheme: s, sectorsCount: schSectors.length, total: total,
                listed: listed, underVf: underVf, ready: ready, notReady: notReady,
                expired: expired, sold: sold, readyPct: readyPct
            };
        });

        // Totals
        const grandTotalPlots = properties.length;
        const grandReadyPlots = properties.filter(function (p) { return p.status_id === ST.AUCTION_READY; }).length;
        const grandOverallPct = grandTotalPlots ? Math.round((grandReadyPlots / grandTotalPlots) * 100) : 0;

        const statsHtml = '<div class="stat-grid dash-stats mb-3">' +
            P.statCard('navy', 'fa-map', schemes.length, 'Total Schemes', 'active land projects', '') +
            P.statCard('blue', 'fa-table-cells', sectors.length, 'Total Sectors', 'subdivisions mapped', '') +
            P.statCard('gold', 'fa-building', grandTotalPlots, 'Registered Plots', 'inventory volume', '') +
            P.statCard('green', 'fa-percent', grandOverallPct + '%', 'Auction Ready Rate', grandReadyPlots + ' cleared plots', '') +
            '</div>';

        const chartsHtml = '<div class="grid-2 mb-3">' +
            P.chartCard('rep-ch-inv-scheme', 'Plot Inventory by Scheme', 'fa-chart-column', { height: 260 }) +
            P.chartCard('rep-ch-inv-status', 'Overall Land Status Breakdown', 'fa-chart-pie', { height: 260 }) +
            '</div>';

        const tableHtml = '<div class="table-wrap"><table class="table table-stack report-table" id="inv-report-table"><thead><tr>' +
            '<th class="cell-no">#</th><th>Scheme Name</th><th>Scheme Type</th><th>Sectors</th>' +
            '<th>Total Plots</th><th>Listed (New)</th><th>Under Verification</th><th>Auction Ready</th>' +
            '<th>Not Ready</th><th>Expired</th><th>Sold / Sent</th><th>Readiness %</th>' +
            '</tr></thead><tbody>' +
            rows.map(function (r, i) {
                return '<tr>' +
                    '<td class="cell-no">' + (i + 1) + '</td>' +
                    '<td><a href="' + P.BASE + '/admin/masters/schemes/' + r.scheme.scheme_id + '"><strong>' + e(r.scheme.scheme_name) + '</strong></a></td>' +
                    '<td>' + (L.SCHEME_TYPES[r.scheme.scheme_type] || r.scheme.scheme_type) + '</td>' +
                    '<td>' + r.sectorsCount + '</td>' +
                    '<td><strong>' + r.total + '</strong></td>' +
                    '<td>' + (r.listed ? '<span class="badge badge-info">' + r.listed + '</span>' : '0') + '</td>' +
                    '<td>' + (r.underVf ? '<span class="badge badge-warning">' + r.underVf + '</span>' : '0') + '</td>' +
                    '<td>' + (r.ready ? '<span class="badge badge-success">' + r.ready + '</span>' : '0') + '</td>' +
                    '<td>' + (r.notReady ? '<span class="badge badge-danger">' + r.notReady + '</span>' : '0') + '</td>' +
                    '<td>' + r.expired + '</td><td>' + r.sold + '</td>' +
                    '<td><strong>' + r.readyPct + '%</strong></td>' +
                    '</tr>';
            }).join('') +
            '</tbody><tfoot><tr><th colspan="3">Total (' + schemes.length + ' Schemes)</th><th>' + sectors.length + '</th>' +
            '<th>' + grandTotalPlots + '</th><th>' + properties.filter(function (p) { return p.status_id === ST.LISTED; }).length + '</th>' +
            '<th>' + properties.filter(function (p) { return p.status_id === ST.UNDER_VERIFICATION; }).length + '</th>' +
            '<th>' + grandReadyPlots + '</th>' +
            '<th>' + properties.filter(function (p) { return p.status_id === ST.NOT_READY; }).length + '</th>' +
            '<th>' + properties.filter(function (p) { return p.status_id === ST.EXPIRED; }).length + '</th>' +
            '<th>' + properties.filter(function (p) { return p.status_id === ST.SOLD || p.status_id === ST.SENT_FOR_AUCTION; }).length + '</th>' +
            '<th>' + grandOverallPct + '%</th></tr></tfoot></table></div>';

        const toolbarHtml = '<div class="report-controls">' +
            '<div><strong>' + schemes.length + '</strong> Schemes · <strong>' + sectors.length + '</strong> Sectors · <strong>' + grandTotalPlots + '</strong> Total Plots</div>' +
            renderExportButtons('rep-inv') +
            '</div>';

        return {
            title: 'Scheme & Sector Inventory Report',
            html: statsHtml + chartsHtml + '<div class="card p-3">' + toolbarHtml + tableHtml + '</div>',
            after: function () {
                const schLabels = rows.map(function (r) { return r.scheme.scheme_name; });
                P.bars('rep-ch-inv-scheme', schLabels, [
                    { label: 'Auction Ready', data: rows.map(function (r) { return r.ready; }), backgroundColor: C.green },
                    { label: 'Under Verification', data: rows.map(function (r) { return r.underVf; }), backgroundColor: C.amber },
                    { label: 'Listed (New)', data: rows.map(function (r) { return r.listed; }), backgroundColor: C.blue },
                    { label: 'Not Ready', data: rows.map(function (r) { return r.notReady; }), backgroundColor: C.red }
                ], { stacked: true });

                const stCounts = {};
                properties.forEach(function (p) { stCounts[p.status_id] = (stCounts[p.status_id] || 0) + 1; });
                const stKeys = Object.keys(stCounts);
                P.pie('rep-ch-inv-status', stKeys.map(function (k) { return P.statusName(k); }), stKeys.map(function (k) { return stCounts[k]; }), stKeys.map(function (k) { return P.STATUS_COLORS[k] || C.slate; }), true);

                const csvBtn = document.getElementById('rep-inv-csv-btn');
                if (csvBtn) {
                    csvBtn.onclick = function () {
                        const headers = ['S.No', 'Scheme Name', 'Scheme Type', 'Sectors Count', 'Total Plots', 'Listed', 'Under Verification', 'Auction Ready', 'Not Ready', 'Expired', 'Sold/Sent', 'Readiness %'];
                        const dataRows = rows.map(function (r, i) {
                            return [i + 1, r.scheme.scheme_name, r.scheme.scheme_type, r.sectorsCount, r.total, r.listed, r.underVf, r.ready, r.notReady, r.expired, r.sold, r.readyPct + '%'];
                        });
                        downloadCsv('Scheme_Sector_Inventory_' + DB.today(), headers, dataRows);
                    };
                }
                const printBtn = document.getElementById('rep-inv-print-btn');
                if (printBtn) {
                    printBtn.onclick = function () {
                        printReportSheet('Scheme & Sector Land Inventory Report', 'Kota Development Authority — Master Land Asset Distribution', statsHtml, tableHtml);
                    };
                }
            }
        };
    }

    /* =========================================================================
       REPORT 3: Verification Cell & SLA Performance Report
       ========================================================================= */
    function reportSLA(ctx) {
        const roles = DB.all('roles').sort(P.by(function (r) { return r.sort_order; }));
        const tasks = DB.all('tasks');
        const users = DB.all('users');
        const todayStr = DB.today();

        const roleStats = roles.map(function (r) {
            const roleTasks = tasks.filter(function (t) { return t.role_id === r.role_id; });
            const total = roleTasks.length;
            const approved = roleTasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length;
            const rejected = roleTasks.filter(function (t) { return t.task_status === 'REJECTED'; }).length;
            const pending = roleTasks.filter(function (t) { return t.task_status === 'PENDING'; }).length;
            const overdue = roleTasks.filter(function (t) { return t.task_status === 'PENDING' && t.sla_due_date && t.sla_due_date < todayStr; }).length;
            const officers = users.filter(function (u) { return u.role_id === r.role_id && u.is_active; }).length;
            const closed = approved + rejected;
            const compliancePct = total ? Math.round(((total - overdue) / total) * 100) : 100;
            return {
                role: r, officers: officers, total: total, approved: approved,
                rejected: rejected, pending: pending, overdue: overdue,
                closed: closed, compliancePct: compliancePct
            };
        });

        // Totals
        const totalTasks = tasks.length;
        const totalApproved = tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length;
        const totalRejected = tasks.filter(function (t) { return t.task_status === 'REJECTED'; }).length;
        const totalOverdue = tasks.filter(function (t) { return t.task_status === 'PENDING' && t.sla_due_date && t.sla_due_date < todayStr; }).length;

        const statsHtml = '<div class="stat-grid dash-stats mb-3">' +
            P.statCard('blue', 'fa-clipboard-list', totalTasks, 'Tasks Assigned', 'across all rounds', '') +
            P.statCard('green', 'fa-circle-check', totalApproved, 'Tasks Approved', Math.round((totalApproved / (totalTasks || 1)) * 100) + '% clearance rate', '') +
            P.statCard('red', 'fa-circle-xmark', totalRejected, 'Tasks Rejected', 'objections raised', '') +
            P.statCard('amber', 'fa-hourglass-half', totalOverdue, 'Overdue Tasks', 'exceeded SLA days', '') +
            '</div>';

        const chartsHtml = '<div class="grid-2 mb-3">' +
            P.chartCard('rep-ch-sla-tasks', 'Task Outcomes by Verification Section', 'fa-chart-column', { height: 260 }) +
            P.chartCard('rep-ch-sla-compliance', 'SLA Compliance Rate (%) by Section', 'fa-chart-line', { height: 260 }) +
            '</div>';

        const tableHtml = '<div class="table-wrap"><table class="table table-stack report-table" id="sla-report-table"><thead><tr>' +
            '<th class="cell-no">#</th><th>Section / Role Name</th><th>Active Officers</th><th>Tasks Assigned</th>' +
            '<th>Approved</th><th>Rejected</th><th>Pending</th><th>Overdue Tasks</th><th>SLA Compliance %</th>' +
            '</tr></thead><tbody>' +
            roleStats.map(function (s, i) {
                return '<tr>' +
                    '<td class="cell-no">' + (i + 1) + '</td>' +
                    '<td><strong>' + e(s.role.role_name) + '</strong>' + (s.role.is_mandatory ? ' <span class="badge badge-info">Mandatory</span>' : '') + '</td>' +
                    '<td>' + s.officers + ' officer(s)</td>' +
                    '<td><strong>' + s.total + '</strong></td>' +
                    '<td>' + (s.approved ? '<span class="badge badge-success">' + s.approved + '</span>' : '0') + '</td>' +
                    '<td>' + (s.rejected ? '<span class="badge badge-danger">' + s.rejected + '</span>' : '0') + '</td>' +
                    '<td>' + (s.pending ? '<span class="badge badge-warning">' + s.pending + '</span>' : '0') + '</td>' +
                    '<td>' + (s.overdue ? '<strong class="text-danger">' + s.overdue + '</strong>' : '<span class="text-muted">0</span>') + '</td>' +
                    '<td><strong>' + s.compliancePct + '%</strong></td>' +
                    '</tr>';
            }).join('') +
            '</tbody><tfoot><tr><th colspan="3">Total Across All Sections</th>' +
            '<th>' + totalTasks + '</th><th>' + totalApproved + '</th><th>' + totalRejected + '</th>' +
            '<th>' + tasks.filter(function (t) { return t.task_status === 'PENDING'; }).length + '</th>' +
            '<th>' + totalOverdue + '</th>' +
            '<th>' + Math.round(((totalTasks - totalOverdue) / (totalTasks || 1)) * 100) + '%</th></tr></tfoot></table></div>';

        const toolbarHtml = '<div class="report-controls">' +
            '<div>Verification SLA &amp; Task Clearance Performance across <strong>' + roles.length + '</strong> sections</div>' +
            renderExportButtons('rep-sla') +
            '</div>';

        return {
            title: 'Verification SLA & Performance Report',
            html: statsHtml + chartsHtml + '<div class="card p-3">' + toolbarHtml + tableHtml + '</div>',
            after: function () {
                const rLabels = roleStats.map(function (s) { return s.role.role_name.replace(/\s+Cell$/i, ''); });
                P.bars('rep-ch-sla-tasks', rLabels, [
                    { label: 'Approved', data: roleStats.map(function (s) { return s.approved; }), backgroundColor: C.green },
                    { label: 'Rejected', data: roleStats.map(function (s) { return s.rejected; }), backgroundColor: C.red },
                    { label: 'Pending', data: roleStats.map(function (s) { return s.pending; }), backgroundColor: C.amber }
                ], { stacked: true });

                P.bars('rep-ch-sla-compliance', rLabels, [
                    { label: 'SLA Compliance %', data: roleStats.map(function (s) { return s.compliancePct; }), backgroundColor: C.blue }
                ]);

                const csvBtn = document.getElementById('rep-sla-csv-btn');
                if (csvBtn) {
                    csvBtn.onclick = function () {
                        const headers = ['S.No', 'Section Name', 'Active Officers', 'Tasks Assigned', 'Approved', 'Rejected', 'Pending', 'Overdue', 'Compliance %'];
                        const dataRows = roleStats.map(function (s, i) {
                            return [i + 1, s.role.role_name, s.officers, s.total, s.approved, s.rejected, s.pending, s.overdue, s.compliancePct + '%'];
                        });
                        downloadCsv('Verification_SLA_Performance_' + DB.today(), headers, dataRows);
                    };
                }
                const printBtn = document.getElementById('rep-sla-print-btn');
                if (printBtn) {
                    printBtn.onclick = function () {
                        printReportSheet('Verification Cell & SLA Performance Report', 'Kota Development Authority — Workflow Turnaround Analysis', statsHtml, tableHtml);
                    };
                }
            }
        };
    }

    /* =========================================================================
       REPORT 4: Deficiency & Rejection Analysis Report
       ========================================================================= */
    function reportDeficiencies(ctx) {
        const q = ctx.query;
        const rejectedTasks = DB.all('tasks').filter(function (t) { return t.task_status === 'REJECTED'; });
        const rounds = DB.all('rounds');
        const properties = P.propertyRows();

        const rejections = rejectedTasks.map(function (t) {
            const round = rounds.find(function (r) { return r.round_id === t.round_id; }) || {};
            const prop = properties.find(function (p) { return p.property_id === round.property_id; }) || {};
            const role = DB.find('roles', t.role_id) || {};
            const officer = t.responded_by ? DB.find('users', t.responded_by) : null;
            return {
                task: t, round: round, prop: prop, role: role, officer: officer,
                date: t.responded_at ? t.responded_at.slice(0, 10) : ''
            };
        });

        // Filter
        let filtered = rejections;
        if (q.scheme_id) { filtered = filtered.filter(function (x) { return String(x.prop.scheme_id) === q.scheme_id; }); }
        if (q.role_id) { filtered = filtered.filter(function (x) { return String(x.role.role_id) === q.role_id; }); }

        // Counts
        const sectionDefectMap = {};
        rejections.forEach(function (x) {
            const name = x.role.role_name || 'Other';
            sectionDefectMap[name] = (sectionDefectMap[name] || 0) + 1;
        });

        const topSection = Object.keys(sectionDefectMap).sort(function (a, b) { return sectionDefectMap[b] - sectionDefectMap[a]; })[0] || 'None';

        const statsHtml = '<div class="stat-grid dash-stats mb-3">' +
            P.statCard('red', 'fa-circle-xmark', rejections.length, 'Total Objections Raised', 'in verification rounds', '') +
            P.statCard('amber', 'fa-building', new Set(rejections.map(function (x) { return x.prop.property_id; })).size, 'Plots Affected', 'marked Not Ready', '') +
            P.statCard('navy', 'fa-shield-halved', topSection, 'Top Objecting Section', (sectionDefectMap[topSection] || 0) + ' objections logged', '') +
            P.statCard('blue', 'fa-rotate', properties.filter(function (p) { return p.status_id === ST.NOT_READY; }).length, 'Pending Correction', 'awaiting re-verification', '') +
            '</div>';

        const chartHtml = '<div class="card p-3 mb-3">' +
            P.chartCard('rep-ch-defect-section', 'Objections / Rejections by Verification Section', 'fa-chart-column', { height: 240 }) +
            '</div>';

        const tableHtml = '<div class="table-wrap"><table class="table table-stack report-table" id="def-report-table"><thead><tr>' +
            '<th class="cell-no">#</th><th>Plot No.</th><th>Scheme / Sector</th><th>Round</th><th>Rejecting Section</th>' +
            '<th>Responding Officer</th><th>Specific Objection / Remark</th><th>Date</th><th>Current Status</th>' +
            '</tr></thead><tbody>' +
            (filtered.length ? filtered.map(function (x, i) {
                const viewUrl = P.BASE + '/admin/masters/properties/' + x.prop.property_id;
                return '<tr class="row-danger">' +
                    '<td class="cell-no">' + (i + 1) + '</td>' +
                    '<td><a href="' + viewUrl + '"><strong>Plot ' + e(x.prop.plot_no) + '</strong></a></td>' +
                    '<td>' + e(x.prop.scheme_name) + (x.prop.sector_name ? ' / ' + e(x.prop.sector_name) : '') + '</td>' +
                    '<td>R' + (x.round.round_no || 1) + '</td>' +
                    '<td><strong>' + e(x.role.role_name) + '</strong></td>' +
                    '<td>' + (x.officer ? e(x.officer.name) + (x.officer.mobile ? ' (' + e(x.officer.mobile) + ')' : '') : '—') + '</td>' +
                    '<td><span style="color:#b42318;font-weight:500;">“' + e(x.task.remarks || 'Rejected without remark') + '”</span></td>' +
                    '<td>' + (x.date ? P.fmt_date(x.date) : '—') + '</td>' +
                    '<td>' + P.status_badge(x.prop.status_id) + '</td>' +
                    '</tr>';
            }).join('') : P.empty_state(9, true, 'No objections logged')) +
            '</tbody></table></div>';

        const toolbarHtml = '<div class="report-controls">' +
            '<div>Total <strong>' + filtered.length + '</strong> objections / defect remarks recorded</div>' +
            renderExportButtons('rep-def') +
            '</div>';

        return {
            title: 'Deficiency & Rejection Analysis Report',
            html: statsHtml + chartHtml + '<div class="card p-3">' + toolbarHtml + tableHtml + '</div>',
            after: function () {
                const secLabels = Object.keys(sectionDefectMap);
                const secData = secLabels.map(function (k) { return sectionDefectMap[k]; });
                P.bars('rep-ch-defect-section', secLabels, [
                    { label: 'Objections Raised', data: secData, backgroundColor: C.red }
                ]);

                const csvBtn = document.getElementById('rep-def-csv-btn');
                if (csvBtn) {
                    csvBtn.onclick = function () {
                        const headers = ['S.No', 'Plot No.', 'Scheme', 'Sector', 'Round', 'Rejecting Section', 'Officer Name', 'Objection Remark', 'Date', 'Status'];
                        const dataRows = filtered.map(function (x, i) {
                            return [i + 1, x.prop.plot_no, x.prop.scheme_name, x.prop.sector_name, 'R' + (x.round.round_no || 1), x.role.role_name, x.officer ? x.officer.name : '', x.task.remarks, x.date, P.statusName(x.prop.status_id)];
                        });
                        downloadCsv('Deficiency_Rejection_Analysis_' + DB.today(), headers, dataRows);
                    };
                }
                const printBtn = document.getElementById('rep-def-print-btn');
                if (printBtn) {
                    printBtn.onclick = function () {
                        printReportSheet('Deficiency & Rejection Analysis Report', 'Kota Development Authority — Objections and Defect Audit', statsHtml, tableHtml);
                    };
                }
            }
        };
    }

    /* =========================================================================
       REPORT 5: Custom Report Builder (Interactive Wizard)
       ========================================================================= */
    function reportCustomBuilder(ctx) {
        const q = ctx.query;
        const viewMode = q.view || 'table';

        let rows = P.propertyRows();

        // Filters applied
        if (q.scheme_id) { rows = rows.filter(function (r) { return String(r.scheme_id) === q.scheme_id; }); }
        if (q.sector_id) { rows = rows.filter(function (r) { return String(r.sector_id) === q.sector_id; }); }
        if (q.type) { rows = rows.filter(function (r) { return String(r.plot_type_id) === q.type; }); }
        if (q.status) { rows = rows.filter(function (r) { return String(r.status_id) === q.status; }); }
        if (q.corner === '1') { rows = rows.filter(function (r) { return Number(r.is_corner) === 1; }); }
        else if (q.corner === '0') { rows = rows.filter(function (r) { return Number(r.is_corner) === 0; }); }
        if (q.from) { rows = rows.filter(function (r) { return r.created_at && r.created_at.slice(0, 10) >= q.from; }); }
        if (q.to) { rows = rows.filter(function (r) { return r.created_at && r.created_at.slice(0, 10) <= q.to; }); }

        // Available Columns definition
        const ALL_COLS = [
            { id: 'plot', label: 'Plot No.', label_hi: 'भूखंड संख्या', icon: 'fa-hashtag', isDefault: true, sortKey: 'plot' },
            { id: 'scheme', label: 'Scheme', label_hi: 'योजना', icon: 'fa-map', isDefault: true, sortKey: 'scheme' },
            { id: 'sector', label: 'Sector', label_hi: 'सेक्टर', icon: 'fa-layer-group', isDefault: true, sortKey: 'sector' },
            { id: 'type', label: 'Plot Type', label_hi: 'प्रकार', icon: 'fa-tag', isDefault: true, sortKey: 'type' },
            { id: 'area', label: 'Area', label_hi: 'क्षेत्रफल', icon: 'fa-ruler-combined', isDefault: true, sortKey: 'area' },
            { id: 'status', label: 'Status', label_hi: 'स्थिति', icon: 'fa-traffic-light', isDefault: true, sortKey: 'status' },
            { id: 'price', label: 'Reserve Price (₹)', label_hi: 'आरक्षित मूल्य', icon: 'fa-indian-rupee-sign', isDefault: true, sortKey: 'price' },
            { id: 'created', label: 'Created At', label_hi: 'पंजीकरण', icon: 'fa-calendar', isDefault: false, sortKey: 'created' }
        ];

        // Parse Active Columns
        let activeColIds;
        if (q.cols) {
            activeColIds = q.cols.split(',').filter(function (id) {
                return ALL_COLS.some(function (c) { return c.id === id; });
            });
            if (!activeColIds.length) {
                activeColIds = ALL_COLS.filter(function (c) { return c.isDefault; }).map(function (c) { return c.id; });
            }
        } else if (q.c_plot !== undefined || q.c_scheme !== undefined || q.c_price !== undefined) {
            activeColIds = [];
            if (q.c_plot !== '0') activeColIds.push('plot');
            if (q.c_scheme !== '0') activeColIds.push('scheme');
            if (q.c_sector !== '0') activeColIds.push('sector');
            if (q.c_type !== '0') activeColIds.push('type');
            if (q.c_area !== '0') activeColIds.push('area');
            if (q.c_status !== '0') activeColIds.push('status');
            if (q.c_price !== '0') activeColIds.push('price');
            if (q.c_roles === '1') activeColIds.push('roles');
            if (q.c_created === '1') activeColIds.push('created');
        } else {
            activeColIds = ALL_COLS.filter(function (c) { return c.isDefault; }).map(function (c) { return c.id; });
        }

        // Summary Aggregates
        const totalCount = rows.length;
        const totalAreaSqm = rows.reduce(function (sum, p) { return sum + (Number(p.area_sqm) || 0); }, 0);
        const totalReserveVal = rows.reduce(function (sum, p) { return sum + (Number(p.reserve_price) || 0); }, 0);
        const avgValuation = totalCount > 0 ? (totalReserveVal / totalCount) : 0;

        // Schemes & Sectors map
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name; });
        const sectors = {};
        if (q.scheme_id) { DB.Scheme.sectors(q.scheme_id).forEach(function (s) { sectors[s.sector_id] = s.sector_name; }); }

        // Pagination setup
        const sortable = {
            plot: function (r) { return r.plot_no; },
            scheme: function (r) { return r.scheme_name; },
            sector: function (r) { return r.sector_name || ''; },
            type: function (r) { return r.type_name; },
            area: function (r) { return Number(r.area_sqm) || 0; },
            status: function (r) { return r.status_id; },
            price: function (r) { return Number(r.reserve_price) || 0; },
            created: function (r) { return r.created_at || ''; }
        };

        const p = P.paginate(rows, ctx, {
            sort: 'created',
            dir: 'desc',
            filters: ['tab', 'view', 'scheme_id', 'sector_id', 'type', 'status', 'corner', 'from', 'to', 'cols'],
            search: function (r) {
                return [r.plot_no, r.scheme_name, r.sector_name, r.type_name];
            },
            sortable: sortable
        });

        // 1. Dedicated Interactive Guide Box (कस्टम रिपोर्ट निर्माण मार्गदर्शिका)
        const guideHtml = '<div class="report-guide-card">' +
            '<div class="report-guide-header">' +
            '<div class="report-guide-title-box">' +
            '<div class="report-guide-icon">' + icon('fa-book-open') + '</div>' +
            '<div><h3 class="report-guide-title">कस्टम रिपोर्ट निर्माण मार्गदर्शिका (Custom Report Builder Guide)</h3>' +
            '<p class="report-guide-subtitle">आवश्यकतानुसार विशिष्ट मापदंड, फ़िल्टर व कॉलम चुनकर कस्टम रिपोर्ट तैयार करने की संपूर्ण कार्यप्रणाली</p></div>' +
            '</div>' +
            '<button type="button" class="btn btn-light btn-sm" id="btn-toggle-guide">' +
            '<i class="fa-solid fa-chevron-up" id="guide-toggle-icon"></i> <span id="guide-toggle-text">गाइड छिपाएं / Hide Guide</span>' +
            '</button></div>' +
            '<div class="report-guide-body" id="report-guide-body">' +

            // Guide Card 1
            '<div class="report-guide-step-card">' +
            '<div class="report-guide-step-card-head">' +
            '<div class="guide-step-badge">1</div>' +
            '<h4 class="guide-step-title">फ़िल्टर मापदंड चयन (Step 1: Apply Filters)</h4></div>' +
            '<p class="guide-step-desc">आवश्यकतानुसार भूखंडों को विशिष्ट मापदंडों के आधार पर छांटें:</p>' +
            '<ul class="guide-step-list">' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>योजना व सेक्टर:</strong> किसी एक योजना (जैसे सूर्य नगर, आनंद विहार) या उसके सेक्टर का चयन करें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>भूखंड प्रकार:</strong> आवासीय (Residential), व्यावसायिक, मिश्रित, संस्थान आदि में से चुनें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>सत्यापन स्थिति:</strong> Draft, Under Verification, Objection, या Auction Ready भूखंड अलग करें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>कॉर्नर व दिनांक:</strong> केवल कॉर्नर प्लॉट अथवा पंजीकरण अवधि (From/To Date) फ़िल्टर करें।</span></li>' +
            '</ul></div>' +

            // Guide Card 2
            '<div class="report-guide-step-card">' +
            '<div class="report-guide-step-card-head">' +
            '<div class="guide-step-badge">2</div>' +
            '<h4 class="guide-step-title">कॉलम चयन (Step 2: Choose Columns)</h4></div>' +
            '<p class="guide-step-desc">निर्धारित करें कि रिपोर्ट व एक्सेल निर्यात में कौन-से कॉलम दिखेंगे:</p>' +
            '<ul class="guide-step-list">' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>उपलब्ध 9 कॉलम:</strong> भूखंड संख्या, योजना, सेक्टर, प्रकार, क्षेत्रफल, स्थिति, आरक्षित मूल्य, शाखाएं, दिनांक।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>कॉलम पिल:</strong> जिस कॉलम को शामिल करना हो उसके बॉक्स पर टिक लगाएं।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>त्वरित विकल्प:</strong> <em>Select All</em> से सभी 9 कॉलम या <em>Default</em> से 7 मुख्य कॉलम तुरंत सक्रिय करें।</span></li>' +
            '</ul></div>' +

            // Guide Card 3
            '<div class="report-guide-step-card">' +
            '<div class="report-guide-step-card-head">' +
            '<div class="guide-step-badge">3</div>' +
            '<h4 class="guide-step-title">दृश्य व विश्लेषण (Step 3: Views &amp; KPIs)</h4></div>' +
            '<p class="guide-step-desc">विभिन्न प्रारूपों में डेटा का सूक्ष्म निरीक्षण करें:</p>' +
            '<ul class="guide-step-list">' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>सारणी दृश्य (Table View):</strong> विस्तृत तालिका, कॉलम अनुसार आरोही/अवरोही (Asc/Desc) सॉर्टिंग व त्वरित सत्यापन।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>कार्ड दृश्य (Cards View):</strong> प्रत्येक भूखंड को उसके कॉर्नर स्टेटस व आरक्षित मूल्य सहित कार्ड ग्रिड में देखें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>KPI सारांश:</strong> कुल मैच भूखंड, कुल क्षेत्रफल (sq.m), व कुल आरक्षित मूल्यांकन ₹ स्वतः दिखाई देते हैं।</span></li>' +
            '</ul></div>' +

            // Guide Card 4
            '<div class="report-guide-step-card">' +
            '<div class="report-guide-step-card-head">' +
            '<div class="guide-step-badge">4</div>' +
            '<h4 class="guide-step-title">निर्यात एवं प्रिंट (Step 4: Export &amp; Print)</h4></div>' +
            '<p class="guide-step-desc">अंतिम रिपोर्ट को आधिकारिक उपयोग हेतु सहेजें:</p>' +
            '<ul class="guide-step-list">' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>Export CSV:</strong> छांटे गए सम्पूर्ण रिकॉर्ड्स को Microsoft Excel स्प्रेडशीट में UTF-8 स्वरूप में डाउनलोड करें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>Print / PDF:</strong> कोटा विकास प्राधिकरण (KDA) के अधिकृत हेडर व सारांश सहित सीधे प्रिंट या PDF सेव करें।</span></li>' +
            '<li>' + icon('fa-circle-check') + ' <span><strong>रीसेट:</strong> सभी फ़िल्टर हटाकर डिफ़ॉल्ट स्थिति पर लौटने हेतु <em>Reset Filters</em> का उपयोग करें।</span></li>' +
            '</ul></div>' +

            '</div></div>';

        // 2. Wizard Form (Step 1 Filters & Step 2 Columns)
        const wizardFormHtml = '<div class="card mb-4 report-wizard-card"><div class="card-body">' +
            '<form method="get" data-path="/admin/reports" id="custom-report-form">' +
            '<input type="hidden" name="tab" value="custom">' +
            '<input type="hidden" name="view" value="' + e(viewMode) + '">' +
            '<input type="hidden" name="cols" id="custom-cols-input" value="' + e(activeColIds.join(',')) + '">' +

            // Step 1: Filter Criteria
            '<div class="wizard-step-section">' +
            '<div class="wizard-step-bar">' +
            '<span class="wizard-step-num">Step 1</span>' +
            '<span class="wizard-step-name">फ़िल्टर मापदंड तय करें (Filter Parameters)</span>' +
            '<span class="wizard-step-hint">चयनित मापदंडों के अनुसार डेटा छांटा जाएगा</span>' +
            '</div>' +
            '<div class="form-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">' +
            '<div class="form-group"><label class="form-label">' + icon('fa-map') + ' योजना (Scheme)</label><select name="scheme_id" class="form-select" data-auto-submit data-reset-on-change="sector_id">' + L.options(schemes, q.scheme_id, 'All Schemes (सभी योजनाएं)') + '</select></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-layer-group') + ' सेक्टर (Sector)</label><select name="sector_id" class="form-select" data-auto-submit' + (!Object.keys(sectors).length ? ' disabled' : '') + '>' + L.options(sectors, q.sector_id, !Object.keys(sectors).length ? 'Select scheme first' : 'All Sectors (सभी सेक्टर)') + '</select></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-tag') + ' भूखंड प्रकार (Plot Type)</label><select name="type" class="form-select" data-auto-submit>' + L.options(L.PROPERTY_TYPES, q.type, 'All Plot Types (सभी प्रकार)') + '</select></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-traffic-light') + ' स्थिति (Status)</label><select name="status" class="form-select" data-auto-submit>' + L.options(L.PROPERTY_STATUSES, q.status, 'All Statuses (सभी स्थितियां)') + '</select></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-gem') + ' कॉर्नर प्लॉट (Corner)</label><select name="corner" class="form-select" data-auto-submit>' + L.options({ '1': 'Corner Plots Only (केवल कॉर्नर)', '0': 'Non-Corner Only (गैर-कॉर्नर)' }, q.corner, 'All Plots (सभी भूखंड)') + '</select></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-calendar') + ' From Date (प्रारम्भिक तिथि)</label><input type="date" name="from" class="form-control" value="' + e(q.from || '') + '"></div>' +
            '<div class="form-group"><label class="form-label">' + icon('fa-calendar-check') + ' To Date (अंतिम तिथि)</label><input type="date" name="to" class="form-control" value="' + e(q.to || '') + '"></div>' +
            '</div></div>' +

            // Step 2: Columns Selection
            '<div class="wizard-step-section">' +
            '<div class="wizard-step-bar">' +
            '<span class="wizard-step-num">Step 2</span>' +
            '<span class="wizard-step-name">प्रदर्शित किए जाने वाले कॉलम चुनें (Select Columns to Display &amp; Export)</span>' +
            '<span class="wizard-step-hint"><span id="active-cols-count">' + activeColIds.length + '</span> / 8 Columns selected</span>' +
            '</div>' +
            '<div class="custom-cols-box">' +
            '<div class="custom-cols-top">' +
            '<div class="custom-cols-legend">' + icon('fa-table-columns') + ' Check the fields you want in the report table &amp; export:</div>' +
            '<div class="custom-cols-actions">' +
            '<button type="button" class="btn btn-light btn-sm" id="btn-cols-all">' + icon('fa-check-double') + ' <span>Select All (8)</span></button>' +
            '<button type="button" class="btn btn-light btn-sm" id="btn-cols-default">' + icon('fa-rotate-left') + ' <span>Default (7)</span></button>' +
            '<button type="button" class="btn btn-light btn-sm" id="btn-cols-clear">' + icon('fa-xmark') + ' <span>Clear All</span></button>' +
            '</div></div>' +
            '<div class="custom-cols-grid">' +
            ALL_COLS.map(function (col) {
                const isChecked = activeColIds.indexOf(col.id) !== -1;
                return '<label class="col-pill-label' + (isChecked ? ' active' : '') + '">' +
                    '<input type="checkbox" class="col-pill-checkbox" data-col="' + col.id + '"' + (isChecked ? ' checked' : '') + '>' +
                    icon(col.icon) + ' <span>' + col.label + ' <small class="text-muted">(' + col.label_hi + ')</small></span></label>';
            }).join('') +
            '</div></div></div>' +

            // Actions: Generate / Reset
            '<div class="form-actions" style="margin-top:16px;padding-top:12px;border-top:1px solid var(--border);display:flex;gap:10px;align-items:center;flex-wrap:wrap;">' +
            '<button type="submit" class="btn btn-primary">' + icon('fa-wand-magic-sparkles') + ' <span>Generate Custom Report / रिपोर्ट बनाएं</span></button>' +
            '<a href="' + P.BASE + '/admin/reports?tab=custom" class="btn btn-light">' + icon('fa-rotate-left') + ' <span>Reset Filters / रीसेट करें</span></a>' +
            '</div></form></div></div>';

        // 3. Summary Overview & KPIs
        const summaryBar = '<div class="report-summary-bar">' +
            '<div class="report-summary-item">' +
            '<div class="report-summary-icon icon-navy">' + icon('fa-shapes') + '</div>' +
            '<div class="report-summary-meta"><span class="report-summary-label">Matched Records</span>' +
            '<span class="report-summary-val">' + P.fmt_num(totalCount) + ' Plots</span></div></div>' +

            '<div class="report-summary-item">' +
            '<div class="report-summary-icon icon-gold">' + icon('fa-ruler-combined') + '</div>' +
            '<div class="report-summary-meta"><span class="report-summary-label">Total Land Area</span>' +
            '<span class="report-summary-val">' + P.fmt_num(totalAreaSqm, 2) + ' sq.m</span></div></div>' +

            '<div class="report-summary-item">' +
            '<div class="report-summary-icon icon-green">' + icon('fa-indian-rupee-sign') + '</div>' +
            '<div class="report-summary-meta"><span class="report-summary-label">Total Reserve Valuation</span>' +
            '<span class="report-summary-val">' + P.fmt_rupees(totalReserveVal) + '</span></div></div>' +

            '<div class="report-summary-item">' +
            '<div class="report-summary-icon icon-blue">' + icon('fa-calculator') + '</div>' +
            '<div class="report-summary-meta"><span class="report-summary-label">Avg Valuation / Plot</span>' +
            '<span class="report-summary-val">' + (totalCount ? P.fmt_rupees(avgValuation) : '₹ 0') + '</span></div></div>' +
            '</div>';

        // 4. Results View (Table / Cards) with Pagination
        let resultsHtml = '';
        if (viewMode === 'cards') {
            resultsHtml = '<div class="report-cards-grid">' +
                (p.items.length ? p.items.map(function (pRow) {
                    const viewUrl = P.BASE + '/admin/masters/properties/' + pRow.property_id;
                    return '<div class="report-prop-card">' +
                        '<div class="report-card-top">' +
                        '<div><span class="report-card-plot">Plot ' + e(pRow.plot_no) + '</span>' + (pRow.is_corner ? ' <span class="badge badge-warning">Corner</span>' : '') + '</div>' +
                        P.status_badge(pRow.status_id) + '</div>' +
                        '<div class="report-card-scheme">' + e(pRow.scheme_name) + '</div>' +
                        '<div class="report-card-sector">Sector: ' + e(pRow.sector_name || 'Main') + ' · ' + e(pRow.type_name) + '</div>' +
                        '<dl class="report-card-details">' +
                        '<div class="report-card-detail"><dt>Area</dt><dd>' + P.areaCell(pRow) + '</dd></div>' +
                        '<div class="report-card-detail"><dt>Reserve Price</dt><dd>' + (pRow.reserve_price ? P.fmt_rupees(pRow.reserve_price) : '—') + '</dd></div>' +
                        '</dl>' +
                        '<div class="report-card-foot">' +
                        '<span class="text-muted small">' + e(P.fmt_date(pRow.created_at)) + '</span>' +
                        '<a href="' + viewUrl + '" class="btn btn-light btn-sm">' + icon('fa-eye') + ' <span>View Details</span></a>' +
                        '</div></div>';
                }).join('') : P.empty_state(4, true, 'No properties match the custom criteria')) +
                '</div>' + P.pagination(p);
        } else {
            const activeColsList = ALL_COLS.filter(function (c) { return activeColIds.indexOf(c.id) !== -1; });
            resultsHtml = '<div class="table-wrap"><table class="table table-stack report-table" id="custom-report-table"><thead><tr>' +
                '<th class="cell-no">#</th>' +
                activeColsList.map(function (c) {
                    if (c.sortKey) {
                        return P.th_sort(p, c.sortKey, c.label);
                    }
                    return '<th>' + c.label + '</th>';
                }).join('') +
                '<th class="text-right">Action</th>' +
                '</tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (pRow, i) {
                    const viewUrl = P.BASE + '/admin/masters/properties/' + pRow.property_id;
                    const rowNum = p.from() + i;
                    let cells = '';
                    activeColsList.forEach(function (c) {
                        if (c.id === 'plot') {
                            cells += '<td><a href="' + viewUrl + '"><strong>' + e(pRow.plot_no) + '</strong></a>' + (pRow.is_corner ? ' <span class="badge badge-warning">Corner</span>' : '') + '</td>';
                        } else if (c.id === 'scheme') {
                            cells += '<td>' + e(pRow.scheme_name) + '</td>';
                        } else if (c.id === 'sector') {
                            cells += '<td>' + e(pRow.sector_name || '—') + '</td>';
                        } else if (c.id === 'type') {
                            cells += '<td>' + e(pRow.type_name) + '</td>';
                        } else if (c.id === 'area') {
                            cells += '<td>' + P.areaCell(pRow) + '</td>';
                        } else if (c.id === 'status') {
                            cells += '<td>' + P.status_badge(pRow.status_id) + '</td>';
                        } else if (c.id === 'price') {
                            cells += '<td>' + (pRow.reserve_price ? P.fmt_rupees(pRow.reserve_price) : '—') + '</td>';
                        } else if (c.id === 'created') {
                            cells += '<td>' + e(P.fmt_date(pRow.created_at)) + '</td>';
                        }
                    });
                    return '<tr>' +
                        '<td class="cell-no">' + rowNum + '</td>' +
                        cells +
                        '<td class="text-right"><a href="' + viewUrl + '" class="btn btn-light btn-sm btn-icon-only" title="View details">' + icon('fa-eye') + '</a></td>' +
                        '</tr>';
                }).join('') : P.empty_state(activeColsList.length + 2, true, 'No properties match the custom criteria')) +
                '</tbody>' +
                (rows.length ? '<tfoot><tr><th colspan="2">Total (' + rows.length + ' Plots)</th><th colspan="' + activeColsList.length + '">' + P.fmt_num(totalAreaSqm, 2) + ' sq.m · Total Value: ' + P.fmt_rupees(totalReserveVal) + '</th></tr></tfoot>' : '') +
                '</table></div>' + P.pagination(p);
        }

        const toolbarHtml = '<div class="report-controls">' +
            '<div>Showing <strong>' + (p.total > 0 ? (p.from() + '–' + p.to()) : '0') + '</strong> of <strong>' + p.total + '</strong> matched records</div>' +
            '<div class="report-actions">' +
            renderViewToggle(viewMode, q, '/admin/reports') +
            renderExportButtons('rep-custom') +
            '</div></div>';

        return {
            title: 'Custom Report Builder',
            html: guideHtml + wizardFormHtml + summaryBar + '<div class="card p-3">' + toolbarHtml + resultsHtml + '</div>',
            after: function () {
                // Guide Toggle
                const toggleBtn = document.getElementById('btn-toggle-guide');
                const guideBody = document.getElementById('report-guide-body');
                const toggleIcon = document.getElementById('guide-toggle-icon');
                const toggleText = document.getElementById('guide-toggle-text');
                if (toggleBtn && guideBody) {
                    const isCollapsed = localStorage.getItem('kda_report_guide_collapsed') === '1';
                    if (isCollapsed) {
                        guideBody.style.display = 'none';
                        if (toggleIcon) toggleIcon.className = 'fa-solid fa-chevron-down';
                        if (toggleText) toggleText.textContent = 'गाइड देखें / View Guide';
                    }
                    toggleBtn.onclick = function () {
                        const hidden = guideBody.style.display === 'none';
                        guideBody.style.display = hidden ? 'grid' : 'none';
                        if (toggleIcon) toggleIcon.className = hidden ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
                        if (toggleText) toggleText.textContent = hidden ? 'गाइड छिपाएं / Hide Guide' : 'गाइड देखें / View Guide';
                        localStorage.setItem('kda_report_guide_collapsed', hidden ? '0' : '1');
                    };
                }

                // Column checkbox pills & quick buttons
                function updateColsState() {
                    const checked = [];
                    document.querySelectorAll('.col-pill-checkbox').forEach(function (cb) {
                        const colId = cb.getAttribute('data-col');
                        const parentLabel = cb.closest('.col-pill-label');
                        if (cb.checked) {
                            checked.push(colId);
                            if (parentLabel) parentLabel.classList.add('active');
                        } else {
                            if (parentLabel) parentLabel.classList.remove('active');
                        }
                    });
                    const input = document.getElementById('custom-cols-input');
                    if (input) input.value = checked.join(',');
                    const countEl = document.getElementById('active-cols-count');
                    if (countEl) countEl.textContent = checked.length;
                }

                document.querySelectorAll('.col-pill-checkbox').forEach(function (cb) {
                    cb.onchange = updateColsState;
                });

                const btnAll = document.getElementById('btn-cols-all');
                if (btnAll) {
                    btnAll.onclick = function () {
                        document.querySelectorAll('.col-pill-checkbox').forEach(function (cb) { cb.checked = true; });
                        updateColsState();
                    };
                }

                const btnDefault = document.getElementById('btn-cols-default');
                if (btnDefault) {
                    btnDefault.onclick = function () {
                        const defIds = ['plot', 'scheme', 'sector', 'type', 'area', 'status', 'price'];
                        document.querySelectorAll('.col-pill-checkbox').forEach(function (cb) {
                            cb.checked = defIds.indexOf(cb.getAttribute('data-col')) !== -1;
                        });
                        updateColsState();
                    };
                }

                const btnClear = document.getElementById('btn-cols-clear');
                if (btnClear) {
                    btnClear.onclick = function () {
                        document.querySelectorAll('.col-pill-checkbox').forEach(function (cb) { cb.checked = false; });
                        updateColsState();
                    };
                }

                // CSV Export (all matching rows)
                const csvBtn = document.getElementById('rep-custom-csv-btn');
                if (csvBtn) {
                    csvBtn.onclick = function () {
                        const activeColsList = ALL_COLS.filter(function (c) { return activeColIds.indexOf(c.id) !== -1; });
                        const activeHeaders = ['S.No'].concat(activeColsList.map(function (c) { return c.label; }));
                        const dataRows = rows.map(function (pRow, i) {
                            const r = [i + 1];
                            activeColsList.forEach(function (c) {
                                if (c.id === 'plot') r.push(pRow.plot_no + (pRow.is_corner ? ' (Corner)' : ''));
                                else if (c.id === 'scheme') r.push(pRow.scheme_name);
                                else if (c.id === 'sector') r.push(pRow.sector_name || '—');
                                else if (c.id === 'type') r.push(pRow.type_name);
                                else if (c.id === 'area') r.push(pRow.area_sqm + ' sq.m');
                                else if (c.id === 'status') r.push(P.statusName(pRow.status_id));
                                else if (c.id === 'price') r.push(pRow.reserve_price || '');
                                else if (c.id === 'created') r.push(pRow.created_at || '');
                            });
                            return r;
                        });
                        downloadCsv('Custom_Property_Report_' + DB.today(), activeHeaders, dataRows);
                    };
                }

                // Print / Save PDF (all matching rows)
                const printBtn = document.getElementById('rep-custom-print-btn');
                if (printBtn) {
                    printBtn.onclick = function () {
                        const activeColsList = ALL_COLS.filter(function (c) { return activeColIds.indexOf(c.id) !== -1; });
                        let printTableHtml = '<table class="table report-table"><thead><tr><th class="cell-no">#</th>' +
                            activeColsList.map(function (c) { return '<th>' + c.label + '</th>'; }).join('') +
                            '</tr></thead><tbody>' +
                            rows.map(function (pRow, i) {
                                let cells = '';
                                activeColsList.forEach(function (c) {
                                    if (c.id === 'plot') cells += '<td><strong>' + e(pRow.plot_no) + '</strong>' + (pRow.is_corner ? ' (Corner)' : '') + '</td>';
                                    else if (c.id === 'scheme') cells += '<td>' + e(pRow.scheme_name) + '</td>';
                                    else if (c.id === 'sector') cells += '<td>' + e(pRow.sector_name || '—') + '</td>';
                                    else if (c.id === 'type') cells += '<td>' + e(pRow.type_name) + '</td>';
                                    else if (c.id === 'area') cells += '<td>' + P.areaCell(pRow) + '</td>';
                                    else if (c.id === 'status') cells += '<td>' + P.status_badge(pRow.status_id) + '</td>';
                                    else if (c.id === 'price') cells += '<td>' + (pRow.reserve_price ? P.fmt_rupees(pRow.reserve_price) : '—') + '</td>';
                                    else if (c.id === 'created') cells += '<td>' + e(P.fmt_date(pRow.created_at)) + '</td>';
                                });
                                return '<tr><td class="cell-no">' + (i + 1) + '</td>' + cells + '</tr>';
                            }).join('') +
                            '</tbody>' +
                            '<tfoot><tr><th colspan="2">Total (' + rows.length + ' Plots)</th><th colspan="' + activeColsList.length + '">' + P.fmt_num(totalAreaSqm, 2) + ' sq.m · Total Value: ' + P.fmt_rupees(totalReserveVal) + '</th></tr></tfoot>' +
                            '</table>';

                        printReportSheet('Custom Property Report (कस्टम भूखंड रिपोर्ट)', 'Kota Development Authority — Filtered Asset Export', summaryBar, printTableHtml);
                    };
                }
            }
        };
    }

    /* =========================================================================
       MAIN ROUTER FOR REPORTS
       ========================================================================= */
    function reportsMain(ctx) {
        const q = ctx.query;
        const activeTab = q.tab || 'auction';

        let reportResult;
        if (activeTab === 'inventory') {
            reportResult = reportInventory(ctx);
        } else if (activeTab === 'sla') {
            reportResult = reportSLA(ctx);
        } else if (activeTab === 'deficiency') {
            reportResult = reportDeficiencies(ctx);
        } else if (activeTab === 'custom') {
            reportResult = reportCustomBuilder(ctx);
        } else {
            reportResult = reportAuctionReady(ctx);
        }

        const headerHtml = P.pageHeader(icon('fa-chart-pie') + ' Reports &amp; Analytics',
            'Comprehensive administrative reports, verification SLA metrics, deficiency analysis, and custom data exports.');

        const fullHtml = headerHtml + renderTabs(activeTab, q) + reportResult.html;

        return {
            title: reportResult.title || 'Reports & Analytics',
            html: fullHtml,
            after: reportResult.after
        };
    }

    // Register routes
    P.route('/admin/reports', null, reportsMain, 'report.view');
    P.route('/reports', null, reportsMain, 'report.view');

})(window);