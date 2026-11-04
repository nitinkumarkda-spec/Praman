/* ===== js/pages/dataentry.js ===== */
/* PRAMAAN — Data Entry panel (app/Views/dataentry/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'DATA_ENTRY';

    /* ================= Scheme Master ================= */
    function schemeRows() {
        return DB.all('schemes').map(function (s) {
            const secs = DB.Scheme.sectors(s.scheme_id).filter(function (x) { return x.is_active; });
            return Object.assign({}, s, {
                area: DB.areaName(s.area_id),
                sector_count: secs.length, sector_names: secs.map(function (x) { return x.sector_name; }).join(', '),
                property_count: DB.count('properties', { scheme_id: s.scheme_id, is_active: 1 })
            });
        });
    }
    function schemeListPage(ctx, base, crumbs) {
        const q = ctx.query;
        let rows = schemeRows();
        if (q.type) { rows = rows.filter(function (s) { return s.scheme_type === q.type; }); }
        if (q.status === 'active') { rows = rows.filter(function (s) { return s.is_active; }); }
        if (q.status === 'inactive') { rows = rows.filter(function (s) { return !s.is_active; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['type', 'status'],
            search: function (s) { return [s.scheme_name, s.sector_names]; },
            sortable: {
                name: function (s) { return s.scheme_name; },
                type: function (s) { return s.scheme_type; },
                sectors: function (s) { return s.sector_count; },
                properties: function (s) { return s.property_count; }
            }
        });
        const filters = '<select name="type" class="form-select" aria-label="Scheme type" data-auto-submit>' + L.options(L.SCHEME_TYPES, q.type, 'All types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive' }, q.status, 'All status') + '</select>';
        return {
            title: 'Schemes',
            html: (crumbs ? crumbs('Schemes') : '') + P.pageHeader(icon('fa-map') + ' Schemes', 'Manage schemes and their sectors for properties.',
                Auth.can('scheme.create') ? '<a href="' + P.BASE + base + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search scheme or sector name…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Scheme') + P.th_sort(p, 'type', 'Type') +
                P.th_sort(p, 'sectors', 'Sectors') + P.th_sort(p, 'properties', 'Properties') +
                '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (s) {
                    const on = s.is_active === 1;
                    return '<tr><td data-label="Scheme"><strong>' + e(s.scheme_name) + '</strong></td>' +
                        '<td data-label="Type">' + e(L.SCHEME_TYPES[s.scheme_type] || s.scheme_type) + '</td>' +
                        '<td data-label="Sectors"><span class="badge badge-info">' + s.sector_count + '</span>' +
                        '<span class="cell-sub truncate" title="' + e(s.sector_names) + '">' + e(s.sector_names || '—') + '</span></td>' +
                        '<td data-label="Properties">' + s.property_count + '</td><td data-label="Status">' + P.active_badge(s.is_active) + '</td>' +
                        '<td class="actions"><div class="btn-group">' + (Auth.can('scheme.edit') ? '<a href="' + P.BASE + base + '/' + s.scheme_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit scheme" aria-label="Edit scheme">' + icon('fa-pen') + '</a>' : '') +
                        (Auth.can('scheme.toggle') ? '<form method="post" data-action="master"data-confirm="' + e(on ? 'Deactivate scheme "' + s.scheme_name + '"? No new property can be added in it.' : 'Activate scheme "' + s.scheme_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="key" value="schemes"><input type="hidden" name="do" value="toggle"><input type="hidden" name="id" value="' + s.scheme_id + '">' +
                            (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') + '</form>' : '') + '</div></td></tr>';
                }).join('') : P.empty_state(7, p.hasFilters(), 'No schemes yet — create the first one')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }

    /** Tag input for new sectors: each name becomes a chip (hidden sectors_new[]); typing searches names used in other schemes (js/forms.js). */
    function sectorTagsHtml(schemeId) {
        return '<div class="tag-input" data-sector-tags data-scheme-id="' + (schemeId || '') + '">' +
            '<div class="tag-box" data-tag-box>' + icon('fa-table-cells tag-box-icon') + '<span class="tag-chips" data-tag-chips></span>' +
            '<input type="text" class="tag-entry" maxlength="80" placeholder="Type a sector name and press Enter — e.g. Sector-4" aria-label="Add sector" ' +
            'autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" data-sector-entry>' +
            '<button type="button" class="tag-caret" tabindex="-1" aria-label="Show sectors" data-tag-caret>' + icon('fa-chevron-down') + '</button></div>' +
            '<ul class="tag-suggest" role="listbox" aria-label="Sector suggestions" hidden data-tag-suggest></ul>' +
            '<div class="form-hint">Click to pick from existing sectors, or type a new name and press Enter.</div>' +
            '<div class="invalid-feedback" data-tag-error></div></div>';
    }
    function schemeForm(scheme, base, crumbs) {
        const isEdit = !!scheme;
        const v = function (k) { return isEdit ? scheme[k] : ''; };
        const sectors = isEdit ? DB.Scheme.sectors(scheme.scheme_id) : [];

        let html = (crumbs ? crumbs('Schemes', isEdit ? 'Edit' : 'Add') : '') + P.pageHeader(icon('fa-map') + ' ' + (isEdit ? 'Edit Scheme' : 'Create Scheme'),
            isEdit ? 'Update scheme details and manage its sectors.'
                : 'Fill scheme details and add its sectors.',
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
            '<form method="post" data-action="master" novalidate data-validate data-scheme-form data-confirm="' +
            (isEdit ? 'Save changes to this scheme?' : 'Create this scheme with the details and sectors entered?') + '">' +
            '<input type="hidden" name="key" value="schemes"><input type="hidden" name="do" value="save">' +
            '<input type="hidden" name="back" value="' + e(base) + '">' + (isEdit ? '<input type="hidden" name="id" value="' + scheme.scheme_id + '">' : '') +
            // 1. details
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">1</span> Scheme details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_name">Scheme name <span class="required">*</span></label>' +
            '<input type="text" id="scheme_name" name="scheme_name" class="form-control" maxlength="150" value="' + e(v('scheme_name')) + '" placeholder="e.g. Ravindra Nagar Awasiya Yojana" data-rules="required|min:3|max:150|text" data-label="Scheme name"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="scheme_type">Scheme type <span class="required">*</span></label>' +
            '<select id="scheme_type" name="scheme_type" class="form-select" data-rules="required" data-label="Scheme type">' + L.options(L.SCHEME_TYPES, v('scheme_type'), 'Select type') + '</select><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +
            // 2. sectors
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">2</span> Sectors <span class="pb-opt">(optional)</span></h3>' +
            '<span class="badge badge-info"><span data-sector-count>0</span> active</span></div><div class="card-body">';
        if (isEdit && sectors.length) {
            html += '<p class="form-section">' + icon('fa-list') + ' Existing sectors — rename or deactivate</p><div class="sector-list">' +
                sectors.map(function (s) {
                    return '<div class="sector-row"><div class="sector-input"><input type="text" name="sec_name_' + s.sector_id + '" class="form-control" maxlength="80" value="' + e(s.sector_name) + '" aria-label="Sector name" data-rules="required|max:80|text" data-label="Sector name" data-sector-name></div>' +
                        '<label class="check sector-active"><input type="checkbox" name="sec_active_' + s.sector_id + '" value="1"' + (s.is_active ? ' checked' : '') + ' data-sector-active><span>Active</span></label>' +
                        '<span class="badge badge-muted" title="Properties in this sector">' + icon('fa-building') + ' ' + DB.count('properties', { sector_id: s.sector_id, is_active: 1 }) + '</span></div>';
                }).join('') + '</div><p class="form-hint mb-3">Deactivated sectors keep their existing properties but cannot be used for new ones.</p>';
        }
        html += '<p class="form-section">' + icon('fa-plus') + ' ' + (isEdit ? 'Add new sectors' : 'Add sectors') + '</p>' +
            sectorTagsHtml(isEdit ? scheme.scheme_id : null) +
            '</div></div>' +
            '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') +
            ' <span>' + (isEdit ? 'Update Scheme' : 'Create Scheme') + '</span></button>' +
            '<a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return { title: isEdit ? 'Edit Scheme' : 'Create Scheme', html: html };
    }
    /** Scheme list + create + edit screens under any base path (Data Entry and Super Admin masters). */
    P.registerSchemeScreens = function (base, panel, crumbs) {
        P.route(base, panel, function (ctx) { return schemeListPage(ctx, base, crumbs); }, 'scheme.view');
        P.route(base + '/create', panel, function () { return schemeForm(null, base, crumbs); }, 'scheme.create');
        P.route(base + '/{id}/edit', panel, function (ctx) {
            const s = DB.find('schemes', ctx.params.id);
            return s ? schemeForm(s, base, crumbs) : P.notFound('Scheme not found.');
        }, 'scheme.edit');
    };
    P.registerSchemeScreens('/data-entry/schemes', PANEL, null);


    /* ================= Property Register ================= */
    P.propertyRows = function () {
        return DB.where('properties', { is_active: 1 }).map(function (p) {
            const sc = DB.find('schemes', p.scheme_id);
            const se = DB.find('sectors', p.sector_id);
            const cu = DB.find('users', p.created_by);
            const round = p.current_round_id ? DB.find('rounds', p.current_round_id) : null;
            const tasks = round ? DB.where('tasks', { round_id: round.round_id }) : [];
            return Object.assign({}, p, {
                scheme_name: sc ? sc.scheme_name : '—', sector_name: se ? se.sector_name : '—',
                type_name: L.PROPERTY_TYPES[p.plot_type_id] || '—', status_name: P.statusName(p.status_id),
                area: sc ? DB.areaName(sc.area_id) : '—',
                created_by_name: cu ? cu.name : '', round_no: round ? round.round_no : null,
                tasks_total: tasks.length, tasks_approved: tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length
            });
        });
    };
    P.propertyFilters = function (q, extraStatuses) {
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name; });
        const sectors = {};
        if (q.scheme_id) { DB.Scheme.sectors(q.scheme_id).forEach(function (s) { sectors[s.sector_id] = s.sector_name; }); }
        const none = !Object.keys(sectors).length;
        return '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit data-reset-on-change="sector_id">' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            '<select name="sector_id" class="form-select" aria-label="Sector" data-auto-submit' + (none ? ' disabled' : '') + '>' + L.options(sectors, q.sector_id, none ? 'Select scheme first' : 'All sectors') + '</select>' +
            '<select name="type" class="form-select" aria-label="Plot type" data-auto-submit>' + L.options(L.PROPERTY_TYPES, q.type, 'All plot types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options(Object.assign({}, L.PROPERTY_STATUSES, extraStatuses || {}), q.status, 'All status') + '</select>';
    };
    P.filterProperties = function (rows, q) {
        if (q.scheme_id) { rows = rows.filter(function (r) { return String(r.scheme_id) === q.scheme_id; }); }
        if (q.sector_id) { rows = rows.filter(function (r) { return String(r.sector_id) === q.sector_id; }); }
        if (q.type) { rows = rows.filter(function (r) { return String(r.plot_type_id) === q.type; }); }
        if (q.status === 'REVERIFY_DUE') { rows = rows.filter(function (r) { return DB.Verification.isDue(r); }); }
        else if (q.status) { rows = rows.filter(function (r) { return String(r.status_id) === q.status; }); }
        return rows;
    };
    P.propertySortable = {
        scheme: function (r) { return r.scheme_name; }, sector: function (r) { return r.sector_name; },
        plot: function (r) { return r.plot_no; }, type: function (r) { return r.type_name; }, area: function (r) { return r.area_sqm; },
        status: function (r) { return r.status_name; }, created: function (r) { return r.property_id; }
    };
    P.propertySearch = function (r) { return [r.plot_no, r.scheme_name, r.sector_name, r.type_name, r.area]; };
    /** "Area" cell of a property table: value + unit (and sq m when another unit), or — */
    P.areaCell = function (r) {
        if (r.area_value == null) { return '—'; }
        return P.fmt_num(r.area_value, 2) + ' ' + e(L.unitSymbol(r.unit_id)) +
            (r.unit_id !== L.BASE_UNIT ? '<span class="cell-sub">= ' + P.fmt_num(r.area_sqm, 2) + ' ' + e(L.unitSymbol(L.BASE_UNIT)) + '</span>' : '');
    };

    /* Reserve price + site plan (entered by Data Entry), shown on the detail pages */
    P.propertyDoc = function (propertyId, context) {
        const rows = P.attachmentsFor({ property_id: propertyId, context: context });
        return rows.length ? rows[rows.length - 1] : null;
    };
    P.fmt_rupees = function (v) {
        return v ? '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '—';
    };
    P.propertyPriceRows = function (prop) {
        const plan = P.propertyDoc(prop.property_id, 'SITE_PLAN');
        const priceRow = (prop && prop.reserve_price) ? '<dt>Reserve price</dt><dd>' + e(P.fmt_rupees(prop.reserve_price)) + '</dd>' : '';
        return priceRow + '<dt>Site plan</dt><dd>' + (plan ? P.attachmentChips([plan]) : '<span class="text-muted">—</span>') + '</dd>';
    };

    /* ================= Verification Indicators & Mapped Role Modal ================= */
    const VF = {
        APPROVED: ['ok', 'fa-circle-check', 'Approved'],
        REJECTED: ['bad', 'fa-circle-xmark', 'Rejected'],
        NO_RESPONSE: ['late', 'fa-triangle-exclamation', 'No response'],
        PENDING: ['wait', 'fa-hourglass-half', 'Pending'],
        SKIPPED: ['skip', 'fa-minus', 'Not required (round closed)']
    };
    function verificationCell(r) {
        const round = DB.where('rounds', { property_id: r.property_id }).sort(function (a, b) { return b.round_no - a.round_no; })[0];
        if (!round) {
            return '<div class="vf vf-empty"><span class="vf-none">' + icon('fa-hourglass-start') + ' Not sent yet</span><span class="vf-sub">' + (r.role_count || 0) + ' sections mapped</span></div>';
        }
        const open = round.round_status === 'OPEN';
        const groups = { APPROVED: [], REJECTED: [], NO_RESPONSE: [], PENDING: [], SKIPPED: [] };
        const segs = DB.where('tasks', { round_id: round.round_id }).map(function (t) {
            return { t: t, role: DB.find('roles', t.role_id), user: t.responded_by ? DB.find('users', t.responded_by) : null };
        }).sort(function (a, b) { return (a.role ? a.role.sort_order : 0) - (b.role ? b.role.sort_order : 0); }).map(function (x) {
            const roleName = x.role ? x.role.role_name : 'Role';
            const key = x.t.task_status === 'PENDING' && !open ? 'SKIPPED' : (groups[x.t.task_status] ? x.t.task_status : 'PENDING');
            const tip = roleName + ' — ' + VF[key][2] + (x.user ? ' by ' + x.user.name : '') +
                (x.t.responded_at ? ' on ' + P.fmt_datetime(x.t.responded_at) : (key === 'PENDING' ? ' · due ' + P.fmt_date(x.t.sla_due_date) : '')) +
                (x.t.remarks ? ' · “' + x.t.remarks + '”' : '');
            groups[key].push({ name: roleName.replace(/\s+Cell$/i, ''), tip: tip });
            return '<span class="vf-seg vf-' + VF[key][0] + '" title="' + e(tip) + '"></span>';
        });
        const total = segs.length;
        const responded = groups.APPROVED.length + groups.REJECTED.length;
        const note = function (key, text) {
            const g = groups[key];
            return '<span class="vf-note vf-' + VF[key][0] + '" title="' + e(g.map(function (x) { return x.tip; }).join('\n')) + '">' + icon(VF[key][1]) + ' <span>' + text + '</span></span>';
        };
        const names = function (key) { return e(groups[key].map(function (x) { return x.name; }).join(', ')); };
        const notes = Object.keys(groups).filter(function (k) { return groups[k].length; }).map(function (k) {
            if (groups[k].length === total) { return note(k, 'All ' + VF[k][2].toLowerCase()); }
            return k === 'APPROVED' ? note(k, groups[k].length + ' approved') : note(k, names(k));
        }).join('');
        return '<div class="vf"><div class="vf-head"><span class="vf-count" title="' + responded + ' of ' + total + ' sections verified">' +
            '<strong>' + responded + '</strong>/' + total + '<span class="sr-only"> sections verified</span></span>' +
            '<span class="vf-round" title="Verification round ' + round.round_no + '">R' + round.round_no + '</span></div>' +
            '<div class="vf-bar" aria-hidden="true">' + segs.join('') + '</div><div class="vf-notes">' + notes + '</div></div>';
    }



    /* ================= Unified Property Register List ================= */
    function propertyListPage(ctx, base, crumbs) {
        const q = ctx.query;
        const p = P.paginate(P.filterProperties(P.propertyRows(), q), ctx, {
            sort: 'created', dir: 'desc', filters: ['scheme_id', 'sector_id', 'type', 'status'],
            search: P.propertySearch, sortable: P.propertySortable
        });
        const sendable = DB.Verification.SENDABLE;
        const canSend = Auth.can('verification.send');
        const canCreate = Auth.can('property.create');
        const canEdit = Auth.can('property.edit');
        const ST = P.ST;

        const rows = p.items.map(function (r, i) {
            const st = r.status_id;
            const can = canSend && sendable.indexOf(st) !== -1;
            const due = DB.Verification.isDue(r);
            const cls = due ? 'row-warning' : st === ST.AUCTION_READY ? 'row-success' : (st === ST.NOT_READY || st === ST.EXPIRED ? 'row-danger' : '');
            const view = P.BASE + base + '/' + r.property_id;

            return '<tr class="' + cls + '">' +
                (canSend ? '<td class="cell-check" data-label="Select">' +
                    (can ? '<input type="checkbox" name="ids[]" value="' + r.property_id + '" aria-label="Select plot ' + e(r.plot_no) + '" data-status="' + st + '">'
                        : '<input type="checkbox" disabled aria-label="Not sendable">') + '</td>' : '') +
                '<td data-label="S.No" class="cell-no">' + (p.from() + i) + '</td>' +
                '<td data-label="Plot no."><a href="' + view + '"><strong>' + e(r.plot_no) + '</strong></a>' + (r.is_corner ? '<span class="cell-sub">Corner</span>' : '') + '</td>' +
                '<td data-label="Scheme">' + e(r.scheme_name) + '</td><td data-label="Sector">' + e(r.sector_name) + '</td>' +
                '<td data-label="Plot type">' + e(r.type_name) + '</td>' +
                '<td data-label="Area" class="nowrap">' + P.areaCell(r) + '</td>' +
                '<td data-label="Verification" class="cell-vf">' + verificationCell(r) + '</td>' +
                '<td data-label="Status">' + P.status_badge(st) +
                (r.round_no ? '<span class="cell-sub">R' + r.round_no + (st === ST.UNDER_VERIFICATION ? ' · ' + r.tasks_approved + '/' + r.tasks_total + ' approved' : '') + '</span>' : '') +
                (st === ST.AUCTION_READY || st === ST.EXPIRED
                    ? '<span class="cell-sub' + (due || st === ST.EXPIRED ? ' text-danger' : '') + '">' + (st === ST.EXPIRED ? 'Expired ' : 'Valid till ') +
                    e(P.fmt_date(r.valid_till)) + (due ? ' (' + P.dueText(r.valid_till) + ')' : '') + '</span>' : '') + '</td>' +
                '<td class="actions"><div class="btn-group btn-group-icons">' +
                '<a href="' + view + '" class="btn btn-light btn-sm btn-icon-only" title="View details &amp; status" aria-label="View details">' + icon('fa-eye') + '</a>' +
                '<a href="' + view + '#timeline" class="btn btn-light btn-sm btn-icon-only" title="Action timeline" aria-label="Action timeline">' + icon('fa-timeline') + '</a>' +
                (canEdit ? (st === ST.LISTED ? '<a href="' + view + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit" aria-label="Edit">' + icon('fa-pen') + '</a>'
                    : '<span class="btn btn-sm btn-light btn-icon-only is-disabled" title="Locked — only Listed properties can be edited">' + icon('fa-lock') + '</span>') : '') +
                (!canSend ? '' : st === ST.LISTED ? '<button type="submit" form="send-' + r.property_id + '" class="btn btn-primary btn-sm btn-icon-only" title="Send for verification" data-loading-text="Sending…">' + icon('fa-paper-plane') + '<span class="sr-only">Send for verification</span></button>'
                    : st === ST.NOT_READY ? '<a href="' + view + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-send for verification" aria-label="Re-send for verification">' + icon('fa-rotate') + '</a>'
                        : st === ST.EXPIRED || due ? '<a href="' + view + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-verify (periodic)" aria-label="Re-verify">' + icon('fa-calendar-check') + '</a>' : '') +
                '</div></td></tr>';
        }).join('');

        const single = p.items.filter(function (r) { return canSend && r.status_id === ST.LISTED; }).map(function (r) {
            return '<form method="post" data-action="oic-send" id="send-' + r.property_id + '" hidden data-confirm="' +
                e('Send plot ' + r.plot_no + ' (' + r.scheme_name + (r.sector_id ? ' / ' + r.sector_name : '') + ') for verification?') + '" data-confirm-ok="Yes, send">' +
                '<input type="hidden" name="ids[]" value="' + r.property_id + '"></form>';
        }).join('');

        const colCount = canSend ? 11 : 10;
        const bulkBar = canSend ? '<div class="bulk-bar"><span class="bulk-count">' + icon('fa-square-check') + ' <strong data-bulk-count="ids[]">0</strong> selected</span>' +
            '<input type="text" name="reason" class="form-control form-control-sm bulk-reason" maxlength="255" placeholder="Re-verification reason (required for Not Ready, optional for Validity Expired)" aria-label="Re-verification reason" data-rules="max:255|text" data-label="Re-verification reason" data-bulk-reason>' +
            '<button type="submit" class="btn btn-primary btn-sm" data-bulk-requires="ids[]" disabled data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send selected for verification</span></button></div>' : '';

        return {
            title: 'Property Register',
            html: (crumbs ? crumbs('Property Register') : '') + P.pageHeader(icon('fa-building') + ' Property Register',
                'Comprehensive property register, verification workflow progress, and auction readiness.',
                canCreate ? '<a href="' + P.BASE + base + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Property</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., scheme, sector, plot type…', P.propertyFilters(q, { REVERIFY_DUE: '⚠ Re-verification due' })) +
                '<form method="post" data-action="oic-send" id="bulk-send-form" data-confirm="Send the selected properties for verification? A new round will be created for each." data-confirm-ok="Yes, send" data-bulk-form data-validate novalidate>' +
                bulkBar +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr>' +
                (canSend ? '<th class="cell-check"><input type="checkbox" data-check-all="ids[]" aria-label="Select all sendable properties"></th>' : '') +
                '<th class="cell-no">S.No</th>' + P.th_sort(p, 'plot', 'Plot no.') + P.th_sort(p, 'scheme', 'Scheme') + P.th_sort(p, 'sector', 'Sector') +
                P.th_sort(p, 'type', 'Plot type') + P.th_sort(p, 'area', 'Area') + '<th class="cell-vf">Verification</th>' +
                P.th_sort(p, 'status', 'Status') + '<th class="text-right">Actions</th></tr></thead><tbody>' +
                (rows || P.empty_state(colCount, p.hasFilters(), 'No properties found')) +
                '</tbody></table></div></form>' + single + P.pagination(p) + '</div>'
        };
    }

    /* Edit one property (only while Listed). New properties come from the Add Property grid below. */
    function propertyForm(prop, base, crumbs) {
        const v = function (k, d) { return prop[k] == null ? (d || '') : prop[k]; };
        const sc = DB.find('schemes', prop.scheme_id);
        const optional = ' <span class="pb-opt">(optional)</span>';
        const sectors = DB.Scheme.sectors(prop.scheme_id, true);
        let html = (crumbs ? crumbs('Property Register', 'Edit') : '') + P.pageHeader(icon('fa-building-circle-check') + ' Edit Property', e('Plot ' + prop.plot_no + ' · ' + sc.scheme_name + ' · ' + P.statusName(prop.status_id)),
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>');
        html += '<form method="post" data-action="property-save" novalidate data-validate data-property-form><input type="hidden" name="id" value="' + prop.property_id + '">' +
            '<input type="hidden" name="back" value="' + e(base) + '">' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-location-dot') + ' Location</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme</label>' +
            '<input type="text" class="form-control" value="' + e(sc.scheme_name) + '" readonly id="scheme_id" data-scheme-label="' + e(sc.scheme_name) + '">' +
            '<div class="form-hint">Scheme cannot be changed after creation.</div><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="sector_id"><span class="step-no">2</span> Sector' + optional + '</label>' +
            '<select id="sector_id" name="sector_id" class="form-select"' + (sectors.length ? '' : ' disabled') + '><option value="">No sector</option>' + sectors.map(function (s) {
                return '<option value="' + s.sector_id + '"' + (s.sector_id === prop.sector_id ? ' selected' : '') + '>' + e(s.sector_name) + '</option>';
            }).join('') + '</select><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-group confirm-box" data-confirm-box><p class="confirm-question"><span class="step-no">3</span> Should this property stay in Scheme <strong data-confirm-scheme>[—]</strong>, Sector <strong data-confirm-sector>[—]</strong>?</p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="confirm_location" value="YES" data-rules="required" data-label="the confirmation (Yes / No)"> <span><strong>Yes</strong>, that is correct</span></label>' +
            '<label class="check"><input type="radio" name="confirm_location" value="NO"> <span><strong>No</strong>, I need to change it</span></label></div><div class="invalid-feedback"></div>' +
            '<p class="form-hint" data-confirm-no hidden>' + icon('fa-circle-info') + ' Please change the sector above. Save stays disabled until you answer <strong>Yes</strong>.</p></div></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-ruler-combined') + ' Plot details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="plot_no">Plot no. <span class="required">*</span></label><input type="text" id="plot_no" name="plot_no" class="form-control" maxlength="30" value="' + e(v('plot_no')) + '" placeholder="e.g. 112-A" data-uppercase data-rules="required|plotno" data-label="Plot no."><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="plot_type_id">Plot type <span class="required">*</span></label><select id="plot_type_id" name="plot_type_id" class="form-select" data-rules="required" data-label="Plot type">' + L.options(L.activeMap('plot_types', 'plot_type_id', 'name', v('plot_type_id')), v('plot_type_id'), 'Select plot type') + '</select><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)', current: P.propertyDoc(prop.property_id, 'SITE_PLAN') }) +
            '<div class="form-group span-2"><label class="form-label">Area' + optional + '</label>' +
            '<div class="dim-row">' +
            '<div class="form-group"><label class="form-label text-muted" for="area_length">Length</label><input type="text" id="area_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2" data-rules="decimal:2|positive" data-label="Length" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<span class="dim-x" aria-hidden="true">×</span>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_width">Width</label><input type="text" id="area_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Width" autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label text-muted" for="area_value">Calculated Area</label><div class="pb-area"><input type="text" id="area_value" name="area_value" class="form-control" readonly value="' + e(v('area_value')) + '" placeholder="Auto (L × W)" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-area-value tabindex="-1" style="cursor: not-allowed;">' +
            '<select id="unit_id" name="unit_id" class="form-select" aria-label="Area unit" data-area-unit>' + L.options(L.activeMap('units', 'unit_id', 'name', v('unit_id')), v('unit_id', L.BASE_UNIT)) + '</select></div>' +
            '<div class="form-hint" data-area-preview></div><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-hint" data-area-calc-hint>Enter Length and Width — Area is auto-calculated and non-editable.</div></div>' +
            '<div class="form-group"><span class="form-label">Corner plot</span><label class="check"><input type="checkbox" name="is_corner" value="1"' + (prop.is_corner ? ' checked' : '') + '> <span>This is a <strong>corner</strong> plot</span></label></div>' +
            '<div class="form-group span-2"><label class="form-label" for="remarks">Description' + optional + '</label><textarea id="remarks" name="remarks" class="form-control" maxlength="500" rows="2" placeholder="Optional notes about this plot" data-rules="max:500|text" data-label="Description">' + e(v('remarks')) + '</textarea><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +

            '<div class="form-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-requires-yes>' + icon('fa-floppy-disk') + ' <span>Update Property</span></button>' +
            '<a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return { title: 'Edit Property', html: html };
    }
    /* Add Property: details once → plot numbers (ranges, or one by one with prefix / postfix) → grid to fix corners → save all */
    function propertyBulkForm(base, crumbs) {
        const schemes = DB.Scheme.eligible();
        let html = (crumbs ? crumbs('Property Register', 'Add') : '') + P.pageHeader(icon('fa-building-circle-check') + ' Add Property',
            'Choose the scheme and plot details, enter the plot numbers, then check the grid and save all properties at once.',
            '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>');
        if (!schemes.length) {
            const schemeLink = base.indexOf('/admin/') === 0 ? '/admin/masters/schemes/create' : '/data-entry/schemes/create';
            return {
                title: 'Add Property',
                html: html + '<div class="card"><div class="card-body"><div class="empty-state">' + icon('fa-map-location-dot', 'fa-bounce empty-icon') +
                    '<p class="empty-title">No active scheme</p><p class="text-muted mb-3">Create or activate a scheme first; its properties are added here.</p>' +
                    (Auth.can('scheme.create') ? '<a href="' + P.BASE + schemeLink + '" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a>' : '') + '</div></div></div>'
            };
        }
        const optional = ' <span class="pb-opt">(optional)</span>';
        html += '<form method="post" data-action="property-save" novalidate data-validate data-property-bulk data-confirm="Save these properties?" data-confirm-title="Save properties" data-confirm-ok="Yes, save all">' +
            '<input type="hidden" name="plots" value="[]" data-pb-plots><input type="hidden" name="back" value="' + e(base) + '">' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-location-dot') + ' Property details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme <span class="required">*</span></label>' +
            '<select id="scheme_id" name="scheme_id" class="form-select" data-rules="required" data-label="Scheme">' +
            '<option value="">Select scheme</option>' + schemes.map(function (s) { return '<option value="' + s.scheme_id + '">' + e(s.scheme_name) + '</option>'; }).join('') +
            '</select><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="sector_id"><span class="step-no">2</span> Sector' + optional + '</label>' +
            '<select id="sector_id" name="sector_id" class="form-select" disabled><option value="">Select scheme first</option></select>' +
            '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="pb_type"><span class="step-no">3</span> Plot type <span class="required">*</span></label>' +
            '<select id="pb_type" name="plot_type_id" class="form-select" data-rules="required" data-label="Plot type" data-pb-default="plot_type_id">' + L.options(L.activeMap('plot_types', 'plot_type_id', 'name'), '', 'Select plot type') + '</select><div class="invalid-feedback"></div></div>' +
            P.pdfField({ name: 'site_plan', label: 'Site plan (PDF)' }) +
            '<div class="form-group span-2"><label class="form-label"><span class="step-no">4</span> Area' + optional + '</label>' +
            '<div class="dim-row">' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_length">Length</label>' +
            '<input type="text" id="pb_length" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 30.00" data-decimals="2" data-rules="decimal:2|positive" data-label="Length" data-pb-length autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<span class="dim-x" aria-hidden="true">×</span>' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_width">Width</label>' +
            '<input type="text" id="pb_width" class="form-control" inputmode="decimal" maxlength="10" placeholder="e.g. 40.50" data-decimals="2" data-rules="decimal:2|positive" data-label="Width" data-pb-width autocomplete="off"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label text-muted" for="pb_area">Calculated Area</label>' +
            '<div class="pb-area"><input type="text" id="pb_area" name="area_value" class="form-control" readonly placeholder="Auto (L × W)" data-decimals="2" data-rules="decimal:2|positive" data-label="Area" data-pb-default="area_value" tabindex="-1" style="cursor: not-allowed;">' +
            '<select name="unit_id" class="form-select" aria-label="Area unit" data-pb-default="unit_id">' + L.options(L.activeMap('units', 'unit_id', 'name'), L.BASE_UNIT) + '</select></div>' +
            '<div class="invalid-feedback"></div></div></div>' +
            '<div class="form-hint" data-pb-area-calc-hint>Enter Length and Width — Area is automatically calculated (Length × Width) and is not editable.</div>' +
            '</div>' +
            '</div></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-list-ol') + ' Plot numbers</h3></div><div class="card-body">' +
            '<div class="form-group pb-question"><p class="confirm-question"><span class="step-no">5</span> Are the plot numbers in a running range?</p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="pb_range" value="YES" checked> <span><strong>Yes</strong>, from – to (e.g. 1 to 50)</span></label>' +
            '<label class="check"><input type="radio" name="pb_range" value="NO"> <span><strong>No</strong>, I will enter each plot no.</span></label></div></div>' +
            '<div data-pb-mode="YES"><div class="pb-rows" data-pb-rows></div>' +
            '<p class="form-hint">Leading zeros are kept: 001 to 050 gives 001, 002 … 050.</p></div>' +
            '<div data-pb-mode="NO" hidden><div class="pb-rows" data-pb-rows></div>' +
            '<p class="form-hint">Prefix and postfix are joined exactly as typed: <span class="mono">A-</span> + <span class="mono">12</span> + <span class="mono">/B</span> → <span class="mono">A-12/B</span>.</p></div>' +
            '<div class="pb-row-actions"><button type="button" class="btn btn-light btn-sm" data-pb-add>' + icon('fa-plus') + ' <span>Add more</span></button>' +
            '<button type="button" class="btn btn-primary" data-pb-generate>' + icon('fa-table-cells') + ' <span>Generate Property Grid</span></button></div>' +
            '</div></div>' +
            '<div class="card pb-grid-card" data-pb-grid-card hidden><div class="card-header"><h3 class="card-title">' + icon('fa-table-cells') + ' Property Grid</h3>' +
            '<span class="text-muted pb-grid-tip">Choose Edit to change a plot; use Mark Corner for corner plots</span></div>' +
            '<div class="pb-legend"><span><i class="pb-swatch"></i> Normal</span><span><i class="pb-swatch is-corner"></i> Corner</span></div>' +
            '<div class="pb-grid" data-pb-grid></div><div class="pb-grid-foot" data-pb-count></div></div>' +
            '<div class="form-actions pb-actions"><a href="' + P.BASE + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a>' +
            '<button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-pb-save disabled>' + icon('fa-check-double') + ' <span>Review &amp; Save Properties</span></button></div>' +
            '</form>';
        return { title: 'Add Property', html: html };
    }
    /* ================= Unified Property Details ================= */
    function propertyDetail(ctx, base, crumbs) {
        const prop = P.propertyRows().find(function (r) { return r.property_id === ctx.params.id; });
        if (!prop) { return P.notFound('Property not found.'); }
        const rounds = DB.where('rounds', { property_id: prop.property_id }).sort(function (a, b) { return b.round_no - a.round_no; }).map(function (r) {
            const tasks = DB.where('tasks', { round_id: r.round_id }).map(function (t) {
                const role = DB.find('roles', t.role_id);
                const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
                const assignees = DB.where('task_assignees', { task_id: t.task_id }).map(function (a) {
                    const u = DB.find('users', a.user_id); return u ? u.name + (u.mobile ? ' (' + u.mobile + ')' : '') : '';
                }).filter(Boolean).sort().join(', ');
                return Object.assign({}, t, { role_name: role ? role.role_name : '', sort_order: role ? role.sort_order : 0, is_mandatory: role ? role.is_mandatory : 0, responded_by_name: ru ? ru.name : null, assignees: assignees });
            }).sort(function (a, b) { return a.sort_order - b.sort_order; });
            const iu = DB.find('users', r.initiated_by);
            return Object.assign({}, r, { tasks: tasks, initiated_by_name: iu ? iu.name : '' });
        });
        const current = rounds[0] || null;
        const ST = P.ST, st = prop.status_id;
        const canSend = Auth.can('verification.send');
        const canEdit = Auth.can('property.edit');

        let headerActions = '<a href="' + P.BASE + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>';
        if (st === ST.LISTED) {
            if (canEdit) {
                headerActions += '<a href="' + P.BASE + base + '/' + prop.property_id + '/edit" class="btn btn-primary">' + icon('fa-pen') + ' <span>Edit</span></a>';
            }
            if (canSend) {
                headerActions += '<form method="post" data-action="oic-send" data-confirm="Send this property for verification?" data-confirm-ok="Yes, send">' +
                    '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><button type="submit" class="btn btn-success" data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send for verification</span></button></form>';
            }
        }

        let html = (crumbs ? crumbs('Property Register', 'Plot ' + prop.plot_no) : '') +
            P.pageHeader(icon('fa-building') + ' Plot ' + e(prop.plot_no) + ' ' + P.status_badge(st),
                e(prop.scheme_name + (prop.sector_id ? ' · ' + prop.sector_name : '')),
                headerActions);

        const due = DB.Verification.isDue(prop);
        const till = prop.valid_till;
        if (due && current) {
            html += '<div class="alert alert-warning">' + icon('fa-calendar-day', 'fa-shake') + '<span><strong>Re-verification due.</strong> Approved in round R' + current.round_no +
                ', but the validity ends ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + '). Auction it before that date, or <a href="#resend">start re-verification</a> now.</span></div>';
        } else if (st === ST.AUCTION_READY && current) {
            html += '<div class="alert alert-success">' + icon('fa-circle-check') + '<span><strong>Ready for Auction.</strong> All roles approved in round R' + current.round_no + '. Valid till ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + ').</span></div>';
        } else if (st === ST.EXPIRED) {
            html += '<div class="alert alert-error">' + icon('fa-calendar-xmark') + '<span><strong>Validity expired on ' + e(P.fmt_date(till)) + '.</strong> The approval of round R' + (current ? current.round_no : '—') +
                ' can no longer be used for an auction. <a href="#resend">Send it for re-verification</a> (new round).</span></div>';
        } else if (st === ST.NOT_READY && current) {
            const rej = current.tasks.filter(function (t) { return t.task_status === 'REJECTED' || t.task_status === 'NO_RESPONSE'; });
            html += '<div class="alert alert-error">' + icon('fa-circle-xmark', 'fa-beat') + '<span><strong>Not Ready — round R' + current.round_no + ' rejected.</strong> Reasons:<ul class="reason-list">' +
                rej.map(function (t) { return '<li><strong>' + e(t.role_name) + ':</strong> ' + e(t.remarks || 'No response') + ' <small>(' + e(t.responded_by_name || '') + ', ' + e(P.fmt_datetime(t.responded_at)) + ')</small></li>'; }).join('') +
                '</ul></span></div>';
        }

        html += '<div class="card mb-3"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Scheme</dt><dd>' + e(prop.scheme_name) + '</dd>' +
            (prop.area && prop.area !== '—' ? '<dt>Area</dt><dd>' + e(prop.area) + '</dd>' : '') +
            '<dt>Sector</dt><dd>' + e(prop.sector_name) + '</dd><dt>Plot no.</dt><dd>' + e(prop.plot_no) + '</dd>' +
            '<dt>Plot type</dt><dd>' + e(prop.type_name) + '</dd>' +
            '<dt>Corner plot</dt><dd>' + (prop.is_corner ? '<span class="badge badge-warning">Corner</span>' : 'No') + '</dd>' +
            '<dt>Plot area</dt><dd>' + e(L.area(prop.area_value, prop.unit_id, prop.area_sqm)) + '</dd>' +
            P.propertyPriceRows(prop) +
            '<dt>Created</dt><dd>' + e(P.fmt_datetime(prop.created_at) + ' · ' + prop.created_by_name) + '</dd>' +
            (prop.remarks ? '<dt>Description</dt><dd>' + e(prop.remarks) + '</dd>' : '') + '</dl></div></div>';

        if (st !== ST.LISTED && canSend && DB.Verification.canSend(prop)) {
            const need = st === ST.NOT_READY;
            const nextNo = (current ? current.round_no : 0) + 1;
            html += '<div class="card" id="resend"><div class="card-header"><h3 class="card-title">' + icon('fa-rotate') + ' ' + (need ? 'Send again for verification' : 'Periodic re-verification') + ' (new round R' + nextNo + ')</h3>' +
                (need ? '' : '<span class="text-muted small">Every mapped section verifies the property again; it stays Under Verification until the round closes.</span>') + '</div><div class="card-body">' +
                '<form method="post" data-action="oic-send" id="resend-form" novalidate data-validate data-confirm="Start a new verification round? The old round stays in history." data-confirm-ok="Yes, ' + (need ? 're-send' : 're-verify') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><div class="form-group"><label class="form-label" for="reason">Reason for re-verification ' + (need ? '<span class="required">*</span>' : '<small class="text-muted">(optional)</small>') + '</label>' +
                '<input type="text" id="reason" name="reason" class="form-control" maxlength="255" placeholder="' +
                e(need ? 'e.g. Encroachment removed, record corrected' : 'Default: Periodic re-verification — validity ' + (st === ST.EXPIRED ? 'expired' : 'ends') + ' on ' + P.fmt_date(till)) +
                '" data-rules="' + (need ? 'required|min:5|max:255|text' : 'max:255|text') + '" data-label="Reason"><div class="invalid-feedback"></div></div>' +
                '<button type="submit" class="btn btn-warning" data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>' + (need ? 'Re-send for verification' : 'Start re-verification') + '</span></button></form></div></div>';
        }

        if (!rounds.length) {
            html += '<div class="card"><div class="card-body"><div class="empty-state">' + icon('fa-hourglass-start', 'empty-icon') +
                '<p class="empty-title">Not sent for verification yet</p><p class="text-muted">Use “Send for verification” to create round R1.</p></div></div></div>';
        }
        rounds.forEach(function (round, i) {
            const isOpen = round.round_status === 'OPEN';
            const approved = round.tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length;
            html += '<details class="card round-card"' + (i === 0 ? ' open' : '') + '><summary class="card-header"><h3 class="card-title">' + icon('fa-layer-group') + ' Round R' + round.round_no + ' ' +
                P.status_badge(round.round_status) + ' ' + (round.result ? P.status_badge(round.result) : '') + '</h3>' +
                '<span class="text-muted small">' + approved + '/' + round.tasks.length + ' approved · Sent ' + e(P.fmt_datetime(round.initiated_at)) + ' by ' + e(round.initiated_by_name) +
                (round.closed_at ? ' · Closed ' + e(P.fmt_datetime(round.closed_at)) : ' · Due ' + e(P.fmt_date(round.due_date))) + '</span></summary>' +
                (round.resend_reason ? '<p class="round-note">' + icon('fa-quote-left') + ' Re-verification reason: ' + e(round.resend_reason) + '</p>' : '') +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Role</th><th>Assigned user(s)</th><th>Status</th><th>Remark</th><th>Attachments</th><th>Date</th></tr></thead><tbody>' +
                round.tasks.map(function (t) {
                    const pendingClosed = t.task_status === 'PENDING' && !isOpen;
                    return '<tr class="' + (t.task_status === 'APPROVED' ? 'row-success' : (t.task_status === 'REJECTED' ? 'row-danger' : '')) + '">' +
                        '<td data-label="Role"><strong>' + e(t.role_name) + '</strong>' + (t.is_mandatory ? '' : ' <span class="badge badge-muted">optional</span>') + '</td>' +
                        '<td data-label="Assigned">' + e(t.assignees || '—') + '</td><td data-label="Status">' +
                        (pendingClosed ? '<span class="badge badge-muted">' + icon('fa-minus') + ' Not required (round closed)</span>' : P.status_badge(t.task_status)) + '</td>' +
                        '<td data-label="Remark">' + e(t.remarks || '—') +
                        (t.section_check ? '<span class="cell-sub">' + icon('fa-file-signature') + ' ' + e(P.sectionCheckText(t.section_check)) + '</span>' : '') + '</td><td data-label="Attachments">' + (P.attachmentChips(P.attachmentsFor({ task_id: t.task_id })) || '—') + '</td><td data-label="Date">' +
                        (t.responded_at ? e(P.fmt_datetime(t.responded_at)) + '<span class="cell-sub">' + e(t.responded_by_name || '') + '</span>' : '<span class="text-muted">SLA ' + e(P.fmt_date(t.sla_due_date)) + '</span>') + '</td></tr>';
                }).join('') + '</tbody></table></div>' +
                (round.result === 'NOT_READY' && round.result_note ? '<div class="card-footer"><strong>Result:</strong> ' + e(round.result_note).replace(/\n/g, '<br>') + '</div>'
                    : round.result === 'READY' ? '<div class="card-footer"><strong>Result:</strong> All roles approved · valid ' + e(P.fmt_date(round.valid_from)) + ' – ' + e(P.fmt_date(round.valid_till)) + '</div>' : '') +
                '</details>';
        });
        html += P.propertyTimeline(prop.property_id);
        return { title: 'Property ' + prop.plot_no, html: html };
    }

    /** Property list + add + view + edit under any base path. Permission-checked. */
    P.registerPropertyScreens = function (base, panel, crumbs) {
        P.route(base, panel, function (ctx) { return propertyListPage(ctx, base, crumbs); }, ['property.view', 'verification.view']);
        P.route(base + '/create', panel, function () { return propertyBulkForm(base, crumbs); }, 'property.create');
        P.route(base + '/{id}', panel, function (ctx) { return propertyDetail(ctx, base, crumbs); }, ['property.view', 'verification.view']);
        P.route(base + '/{id}/edit', panel, function (ctx) {
            const p = DB.find('properties', ctx.params.id);
            if (!p || !p.is_active) { return P.notFound('Property not found.'); }
            if (p.status_id !== P.ST.LISTED) {
                P.flash('warning', 'Only properties in "Listed" status can be edited. This one is ' + P.statusName(p.status_id) + '.');
                P.go(base);
                return false;
            }
            return propertyForm(p, base, crumbs);
        }, 'property.edit');
    };
    P.registerPropertyScreens('/data-entry/properties', PANEL, null);

})(window);
