/* ===== js/pages/masters.js ===== */
/* =====================================================================
   PRAMAAN — Super Admin masters.
   One generic engine (config below) gives every simple master the same
   list / search / filter / sort / add / edit / activate / delete screens.
   Scheme + Sector masters are custom (they own sectors & role mapping).
   ===================================================================== */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'SUPER_ADMIN';
    const BASE = '/admin/masters';

    // badge colours / icons offered for statuses (same lists the server accepts)
    const COLOR_OPTS = function () { return L.STATUS_COLORS; };
    const ICON_OPTS = function () { return L.STATUS_ICONS; };

    /* ---------------- config of the generic masters ---------------- */
    const MASTERS = {
        areas: {
            table: 'areas', pk: 'area_id', title: 'Area Master', one: 'Area', icon: 'fa-map-location-dot', entity: 'AREA',
            sub: 'Geographical areas of Kota. Inactive areas are hidden when creating new schemes.',
            name: function (r) { return r.area_name; }, sort: 'order',
            fields: [
                { name: 'area_code', label: 'Code', rules: 'required|code', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. AREA-NORTH', hint: 'CAPITAL letters, digits, hyphen, underscore. Stored on schemes — cannot be changed later.' },
                { name: 'area_name', label: 'Area name', rules: 'required|min:2|max:60|text', unique: true, placeholder: 'e.g. North Kota' },
                { name: 'name_hi', label: 'Name (Hindi)', rules: 'max:60|text', placeholder: 'e.g. उत्तर कोटा' },
                { name: 'description', label: 'Description', rules: 'max:150|text' },
                { name: 'sort_order', label: 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: function () { return DB.count('areas') + 1; } }
            ],
            columns: [
                ['order', '#', function (r) { return r.sort_order; }, function (r) { return String(r.sort_order); }],
                ['code', 'Code', function (r) { return r.area_code; }, function (r) { return '<span class="mono">' + e(r.area_code) + '</span>'; }],
                ['name', 'Area name', function (r) { return r.area_name; }, function (r) { return '<strong>' + e(r.area_name) + '</strong>' + (r.name_hi ? '<span class="cell-sub">' + e(r.name_hi) + '</span>' : ''); }],
                ['desc', 'Description', function (r) { return r.description; }, function (r) { return e(r.description || '—'); }]
            ],
            usage: function (r) { const n = DB.count('schemes', { area_id: r.area_id, is_active: 1 }); return { n: n, text: n + ' scheme' + (n === 1 ? '' : 's') }; },
            search: function (r) { return [r.area_code, r.area_name, r.name_hi, r.description]; }
        },
        'plot-types': {
            table: 'plot_types', pk: 'plot_type_id', title: 'Plot Type Master', one: 'Plot type', icon: 'fa-shapes', entity: 'PLOT_TYPE',
            sub: 'Types of property / plot. Inactive types are hidden from new property entry but kept on existing records.',
            name: function (r) { return r.name; }, sort: 'order',
            fields: [
                { name: 'code', label: 'Code', rules: 'required|code', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. GODOWN', hint: 'CAPITAL letters, digits, underscore. Stored on properties — cannot be changed later.' },
                { name: 'name', label: 'Name', rules: 'required|min:2|max:60|text', unique: true, placeholder: 'e.g. Godown' },
                { name: 'name_hi', label: 'Name (Hindi)', rules: 'max:60|text' },
                { name: 'description', label: 'Description', rules: 'max:150|text' },
                { name: 'sort_order', label: 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: function () { return DB.count('plot_types') + 1; } }
            ],
            columns: [
                ['order', '#', function (r) { return r.sort_order; }, function (r) { return String(r.sort_order); }],
                ['code', 'Code', function (r) { return r.code; }, function (r) { return '<span class="mono">' + e(r.code) + '</span>'; }],
                ['name', 'Plot type', function (r) { return r.name; }, function (r) { return '<strong>' + e(r.name) + '</strong>' + (r.name_hi ? '<span class="cell-sub">' + e(r.name_hi) + '</span>' : ''); }],
                ['desc', 'Description', function (r) { return r.description; }, function (r) { return e(r.description || '—'); }]
            ],
            usage: function (r) { const n = DB.count('properties', { property_type: r.code, is_active: 1 }); return { n: n, text: n + ' propert' + (n === 1 ? 'y' : 'ies') }; },
            search: function (r) { return [r.code, r.name, r.name_hi, r.description]; }
        },
        statuses: {
            table: 'statuses', pk: 'status_id', title: 'Status Master', one: 'Property status', icon: 'fa-flag', entity: 'STATUS',
            sub: 'Property status names, colours and icons. System statuses drive the verification workflow — their code cannot change and they cannot be removed.',
            name: function (r) { return r.name; }, sort: 'order',
            fields: [
                { name: 'code', label: 'Status code', rules: 'required|code', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. ON_HOLD', hint: 'CAPITAL letters, digits, underscore. Cannot be changed later.' },
                { name: 'name', label: 'Display name', rules: 'required|min:2|max:40|text', unique: true, placeholder: 'e.g. On Hold' },
                { name: 'name_hi', label: 'Name (Hindi)', rules: 'max:40|text' },
                { name: 'color', label: 'Badge colour', type: 'select', rules: 'required', options: COLOR_OPTS, def: function () { return 'info'; }, preview: true },
                { name: 'icon', label: 'Icon', type: 'select', rules: 'required', options: ICON_OPTS, def: function () { return 'fa-flag'; }, preview: true },
                { name: 'description', label: 'Meaning', rules: 'max:150|text' },
                { name: 'sort_order', label: 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: function () { return DB.count('statuses') + 1; } }
            ],
            columns: [
                ['order', '#', function (r) { return r.sort_order; }, function (r) { return String(r.sort_order); }],
                ['code', 'Code', function (r) { return r.code; }, function (r) { return '<span class="mono">' + e(r.code) + '</span>' + (r.is_system ? '<span class="cell-sub">' + icon('fa-lock') + ' system</span>' : '<span class="cell-sub">custom</span>'); }],
                ['name', 'Badge', function (r) { return r.name; }, function (r) { return P.status_badge(r.code) + (r.name_hi ? '<span class="cell-sub">' + e(r.name_hi) + '</span>' : ''); }],
                ['desc', 'Meaning', function (r) { return r.description; }, function (r) { return e(r.description || '—'); }]
            ],
            locked: function (r) { return !!r.is_system; },
            usage: function (r) { const n = DB.count('properties', { current_status: r.code, is_active: 1 }); return { n: n, text: n + ' propert' + (n === 1 ? 'y' : 'ies') }; },
            search: function (r) { return [r.code, r.name, r.name_hi, r.description]; }
        }
    };
    P.MASTERS = MASTERS;

    /* ---------------- generic list ---------------- */
    function listPage(key, ctx) {
        const m = MASTERS[key];
        const q = ctx.query;
        let rows = DB.all(m.table).map(function (r) { return Object.assign({}, r, { _usage: m.usage(r) }); });
        if (q.status === 'active') { rows = rows.filter(function (r) { return r.is_active; }); }
        if (q.status === 'inactive') { rows = rows.filter(function (r) { return !r.is_active; }); }
        if (m.applyFilter) { rows = m.applyFilter(rows, q); }
        const sortable = { usage: function (r) { return r._usage.n; } };
        m.columns.forEach(function (c) { sortable[c[0]] = c[2]; });
        const p = P.paginate(rows, ctx, { sort: m.sort, dir: 'asc', filters: ['status'].concat(m.filterKeys || []), search: m.search, sortable: sortable });
        const filters = (m.filters ? m.filters(q) : '') + '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' +
            L.options({ active: 'Active', inactive: 'Inactive' }, q.status, 'All status') + '</select>';
        const total = DB.count(m.table), active = DB.count(m.table, { is_active: 1 });
        return {
            title: m.title,
            html: crumbs(m.title) + P.pageHeader(icon(m.icon) + ' ' + m.title, m.sub,
                '<a href="#' + BASE + '/' + key + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add ' + e(m.one) + '</span></a>') +
                '<div class="master-mini"><span>' + icon('fa-database') + ' <strong>' + total + '</strong> total</span><span class="ok">' + icon('fa-circle-check') + ' <strong>' + active + '</strong> active</span>' +
                '<span class="off">' + icon('fa-circle-pause') + ' <strong>' + (total - active) + '</strong> inactive</span></div>' +
                '<div class="card">' + P.list_toolbar(p, 'Search ' + m.one.toLowerCase() + '…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                m.columns.map(function (c) { return P.th_sort(p, c[0], c[1]); }).join('') + P.th_sort(p, 'usage', 'Used in') + '<th>Status</th><th>Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (r) {
                    const id = r[m.pk], on = r.is_active === 1, locked = m.locked && m.locked(r);
                    return '<tr>' + m.columns.map(function (c) { return '<td data-label="' + e(c[1]) + '">' + c[3](r) + '</td>'; }).join('') +
                        '<td data-label="Used in"><span class="badge ' + (r._usage.n ? 'badge-info' : 'badge-muted') + '">' + r._usage.n + '</span><span class="cell-sub">' + e(r._usage.text) + '</span></td>' +
                        '<td data-label="Status">' + P.active_badge(r.is_active) + '</td><td class="actions actions-start"><div class="btn-group">' +
                        '<a href="#' + BASE + '/' + key + '/' + id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + m.name(r)) + '" aria-label="' + e('Edit ' + m.name(r)) + '">' + icon('fa-pen') + '</a>' +
                        (locked ? '<span class="btn btn-sm btn-light btn-icon-only is-disabled" title="System status — always active">' + icon('fa-lock') + '</span>' :
                            '<form method="post" data-action="master-toggle" data-confirm="' + e(on ? 'Deactivate ' + m.one.toLowerCase() + ' "' + m.name(r) + '"? It will be hidden from new entries; existing records keep it.' : 'Activate ' + m.one.toLowerCase() + ' "' + m.name(r) + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="key" value="' + key + '"><input type="hidden" name="id" value="' + id + '">' +
                            (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') + '</form>') +
                        (!locked && !r._usage.n ? '<form method="post" data-action="master-delete" data-confirm="' + e('Delete ' + m.one.toLowerCase() + ' "' + m.name(r) + '" permanently? It is not used anywhere.') + '" data-confirm-variant="danger" data-confirm-ok="Yes, delete">' +
                            '<input type="hidden" name="key" value="' + key + '"><input type="hidden" name="id" value="' + id + '">' +
                            '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Delete" aria-label="Delete">' + icon('fa-trash-can') + '</button></form>' : '') +
                        '</div></td></tr>';
                }).join('') : P.empty_state(m.columns.length + 3, p.hasFilters(), 'No records yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }

    /* ---------------- generic form ---------------- */
    function formPage(key, row) {
        const m = MASTERS[key];
        const isEdit = !!row;
        const locked = isEdit && m.locked && m.locked(row);
        const val = function (f) { return isEdit ? (row[f.name] == null ? '' : row[f.name]) : (f.def ? f.def() : ''); };
        const field = function (f) {
            const ro = isEdit && f.lockOnEdit;
            const attrs = ' id="' + f.name + '" name="' + f.name + '" data-label="' + e(f.label) + '"' + (ro ? ' readonly' : (f.rules ? ' data-rules="' + f.rules + '"' : '')) +
                (f.upper ? ' data-uppercase' : '') + (f.placeholder ? ' placeholder="' + e(f.placeholder) + '"' : '');
            let input;
            if (f.type === 'select') {
                input = '<select class="form-select"' + attrs + (f.preview ? ' data-master-preview' : '') + '>' + L.options(f.options(val(f)), val(f), 'Select ' + f.label.toLowerCase()) + '</select>';
            } else {
                input = '<input type="' + (f.type === 'number' ? 'number' : 'text') + '" class="form-control' + (f.upper ? ' mono' : '') + '" value="' + e(val(f)) + '"' + attrs + '>';
            }
            const req = /required/.test(f.rules || '');
            return '<div class="form-group"><label class="form-label" for="' + f.name + '">' + e(f.label) + (req ? ' <span class="required">*</span>' : '') + '</label>' + input +
                (f.hint || ro ? '<div class="form-hint">' + (ro ? 'Cannot be changed after creation.' : e(f.hint)) + '</div>' : '') + '<div class="invalid-feedback"></div></div>';
        };
        const usage = isEdit ? m.usage(row) : null;
        const previewField = m.fields.some(function (f) { return f.preview; });
        return {
            title: (isEdit ? 'Edit ' : 'Add ') + m.one,
            html: crumbs(m.title, isEdit ? 'Edit' : 'Add') + P.pageHeader(icon(m.icon) + ' ' + (isEdit ? 'Edit ' : 'Add ') + e(m.one), isEdit ? e(m.name(row)) : m.sub,
                '<a href="#' + BASE + '/' + key + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
                (usage && usage.n ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This ' + e(m.one.toLowerCase()) + ' is used in <strong>' + e(usage.text) + '</strong>. Changes show everywhere it is used.</span></div>' : '') +
                (locked ? '<div class="alert alert-warning">' + icon('fa-lock') + '<span>System status — the workflow depends on code <strong>' + e(row.code) + '</strong>. You can change its name, colour, icon and order.</span></div>' : '') +
                '<div class="card"><div class="card-body"><form method="post" data-action="master-save" novalidate data-validate>' +
                '<input type="hidden" name="key" value="' + key + '">' + (isEdit ? '<input type="hidden" name="id" value="' + row[m.pk] + '">' : '') +
                '<div class="form-grid">' + m.fields.map(field).join('') +
                (previewField ? '<div class="form-group"><label class="form-label">Preview</label><div class="master-preview" data-preview-box></div></div>' : '') +
                (locked ? '' : '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (!isEdit || row.is_active ? ' checked' : '') + '>' +
                    '<span><strong>Active</strong><br><small class="text-muted">Inactive records are hidden from new entries.</small></span></label></div>') +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update' : 'Create') + ' ' + e(m.one) + '</span></button>' +
                '<a href="#' + BASE + '/' + key + '" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>',
            after: previewField ? function (root) {
                const form = root.querySelector('form[data-action="master-save"]');
                const box = form.querySelector('[data-preview-box]');
                function draw() {
                    const name = (form.querySelector('[name="name"]').value || 'Status name').trim();
                    box.innerHTML = '<span class="badge badge-' + e(form.querySelector('[name="color"]').value || 'muted') + '"><i class="fa-solid ' + e(form.querySelector('[name="icon"]').value || 'fa-flag') + '" aria-hidden="true"></i> ' + e(name) + '</span>';
                }
                form.addEventListener('input', draw);
                form.addEventListener('change', draw);
                draw();
            } : null
        };
    }
    function crumbs(title, last) {
        let listHref = '';
        if (last) {
            const k = Object.keys(MASTERS).find(function (x) { return MASTERS[x].title === title; });
            if (k) { listHref = '#' + BASE + '/' + k; }
            else if (title === 'Sector Master') { listHref = '#' + BASE + '/sectors'; }
            else if (title === 'Scheme Master') { listHref = '#' + BASE + '/schemes'; }
        }
        return '<nav class="ins-crumbs" aria-label="Breadcrumb"><a href="#/admin/dashboard">Home</a><span>/</span>' +
            (last ? (listHref ? '<a href="' + listHref + '">' + e(title) + '</a>' : '<span>' + e(title) + '</span>') + '<span>/</span><strong>' + e(last) + '</strong>' : '<strong>' + e(title) + '</strong>') + '</nav>';
    }

    Object.keys(MASTERS).forEach(function (key) {
        P.route(BASE + '/' + key, PANEL, function (ctx) { return listPage(key, ctx); });
        P.route(BASE + '/' + key + '/create', PANEL, function () { return formPage(key, null); });
        P.route(BASE + '/' + key + '/{id}/edit', PANEL, function (ctx) {
            const r = DB.find(MASTERS[key].table, ctx.params.id);
            return r ? formPage(key, r) : P.notFound(MASTERS[key].one + ' not found.');
        });
    });


    /* ================= Sector Master (all schemes) ================= */
    function sectorRows() {
        return DB.all('sectors').map(function (s) {
            const sc = DB.find('schemes', s.scheme_id);
            const areaName = sc ? DB.areaName(sc.area_id) : '—';
            return Object.assign({}, s, { scheme_name: sc ? sc.scheme_name : '—', scheme_code: sc ? sc.scheme_code : '—', scheme_active: sc ? sc.is_active : 0,
                area: areaName,
                props: DB.count('properties', { sector_id: s.sector_id, is_active: 1 }) });
        });
    }
    P.route(BASE + '/sectors', PANEL, function (ctx) {
        const q = ctx.query;
        let rows = sectorRows();
        if (q.scheme_id) { rows = rows.filter(function (r) { return String(r.scheme_id) === q.scheme_id; }); }
        if (q.status === 'active') { rows = rows.filter(function (r) { return r.is_active; }); }
        if (q.status === 'inactive') { rows = rows.filter(function (r) { return !r.is_active; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'scheme', dir: 'asc', filters: ['scheme_id', 'status'],
            search: function (r) { return [r.sector_name, r.scheme_name, r.scheme_code, r.area]; },
            sortable: { scheme: function (r) { return r.scheme_name + ' ' + String(r.sort_order).padStart(3, '0'); }, name: function (r) { return r.sector_name; },
                order: function (r) { return r.sort_order; }, props: function (r) { return r.props; } }
        });
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name + ' (' + s.scheme_code + ')'; });
        const filters = '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit>' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive' }, q.status, 'All status') + '</select>';
        const total = DB.count('sectors'), active = DB.count('sectors', { is_active: 1 });
        return {
            title: 'Sector Master',
            html: crumbs('Sector Master') + P.pageHeader(icon('fa-table-cells') + ' Sector Master', 'Sectors of all schemes. Properties are registered inside an active sector.',
                '<a href="' + P.url(BASE + '/sectors/create', q.scheme_id ? { scheme_id: q.scheme_id } : null) + '" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Sector</span></a>') +
                '<div class="master-mini"><span>' + icon('fa-database') + ' <strong>' + total + '</strong> total</span><span class="ok">' + icon('fa-circle-check') + ' <strong>' + active + '</strong> active</span>' +
                '<span class="off">' + icon('fa-circle-pause') + ' <strong>' + (total - active) + '</strong> inactive</span></div>' +
                '<div class="card">' + P.list_toolbar(p, 'Search sector, scheme or area…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' + P.th_sort(p, 'scheme', 'Scheme') + P.th_sort(p, 'name', 'Sector') +
                P.th_sort(p, 'order', 'Order') + P.th_sort(p, 'props', 'Properties') + '<th>Status</th><th>Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (r) {
                    const on = r.is_active === 1;
                    return '<tr><td data-label="Scheme"><strong>' + e(r.scheme_name) + '</strong><span class="cell-sub">' + e(r.scheme_code) + (r.area && r.area !== '—' ? ' · ' + e(r.area) : '') + (r.scheme_active ? '' : ' · scheme inactive') + '</span></td>' +
                        '<td data-label="Sector">' + e(r.sector_name) + '</td><td data-label="Order">' + r.sort_order + '</td>' +
                        '<td data-label="Properties"><a href="' + P.url('/admin/masters/sectors/' + r.sector_id + '/edit') + '" class="badge ' + (r.props ? 'badge-info' : 'badge-muted') + '">' + r.props + '</a></td>' +
                        '<td data-label="Status">' + P.active_badge(r.is_active) + '</td><td class="actions actions-start"><div class="btn-group">' +
                        '<a href="#' + BASE + '/sectors/' + r.sector_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit sector" aria-label="Edit sector">' + icon('fa-pen') + '</a>' +
                        '<form method="post" data-action="sector-toggle" data-confirm="' + e(on ? 'Deactivate sector "' + r.sector_name + '"? Existing properties stay; no new property can be added in it.' : 'Activate sector "' + r.sector_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                        '<input type="hidden" name="id" value="' + r.sector_id + '">' + (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                            : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') + '</form>' +
                        (!r.props ? '<form method="post" data-action="sector-delete" data-confirm="' + e('Delete sector "' + r.sector_name + '" permanently?') + '" data-confirm-variant="danger" data-confirm-ok="Yes, delete">' +
                            '<input type="hidden" name="id" value="' + r.sector_id + '"><button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Delete" aria-label="Delete">' + icon('fa-trash-can') + '</button></form>' : '') +
                        '</div></td></tr>';
                }).join('') : P.empty_state(6, p.hasFilters(), 'No sectors yet')) + '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    });
    function sectorForm(row, ctx) {
        const isEdit = !!row;
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name + ' (' + s.scheme_code + ')' + (s.is_active ? '' : ' — inactive'); });
        const sc = isEdit ? DB.find('schemes', row.scheme_id) : null;
        const props = isEdit ? DB.count('properties', { sector_id: row.sector_id, is_active: 1 }) : 0;
        return {
            title: isEdit ? 'Edit Sector' : 'Add Sector',
            html: crumbs('Sector Master', isEdit ? 'Edit' : 'Add') + P.pageHeader(icon('fa-table-cells') + ' ' + (isEdit ? 'Edit Sector' : 'Add Sector'),
                isEdit ? e(row.sector_name + ' · ' + sc.scheme_name) : 'Add one or more sectors to a scheme. Each name must be unique inside its scheme.',
                '<a href="#' + BASE + '/sectors" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
                (props ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>' + props + ' propert' + (props === 1 ? 'y is' : 'ies are') + ' registered in this sector. Renaming shows the new name on them too.</span></div>' : '') +
                '<div class="card"><div class="card-body"><form method="post" data-action="sector-save" novalidate data-validate>' + (isEdit ? '<input type="hidden" name="id" value="' + row.sector_id + '">' : '') +
                '<div class="form-grid"><div class="form-group"><label class="form-label" for="scheme_id">Scheme <span class="required">*</span></label>' +
                (isEdit ? '<input type="text" class="form-control" readonly value="' + e(sc.scheme_name + ' (' + sc.scheme_code + ')') + '"><div class="form-hint">A sector cannot move to another scheme.</div>'
                    : '<select id="scheme_id" name="scheme_id" class="form-select" data-rules="required" data-label="Scheme">' + L.options(schemes, ctx.query.scheme_id, 'Select scheme') + '</select>') +
                '<div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="sector_name">' + (isEdit ? 'Sector name' : 'Sector name(s)') + ' <span class="required">*</span></label>' +
                (isEdit ? '<input type="text" id="sector_name" name="sector_name" class="form-control" maxlength="80" value="' + e(row.sector_name) + '" data-rules="required|max:80|text" data-label="Sector name">'
                    : '<input type="text" id="sector_name" name="sector_name" class="form-control" maxlength="400" placeholder="e.g. Sector-5, Sector-6, Block-C" data-rules="required|max:400|text" data-label="Sector name(s)">' +
                    '<div class="form-hint">Separate several sectors with commas.</div>') + '<div class="invalid-feedback"></div></div>' +
                (isEdit ? '<div class="form-group"><label class="form-label" for="sort_order">Display order <span class="required">*</span></label><input type="number" id="sort_order" name="sort_order" class="form-control" value="' + row.sort_order + '" data-rules="required|integer|between:0,999" data-label="Display order"><div class="invalid-feedback"></div></div>' +
                    '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (row.is_active ? ' checked' : '') + '><span><strong>Active</strong><br><small class="text-muted">Inactive sectors cannot receive new properties.</small></span></label></div>' : '') +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update Sector' : 'Add Sector(s)') + '</span></button>' +
                '<a href="#' + BASE + '/sectors" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>'
        };
    }
    P.route(BASE + '/sectors/create', PANEL, function (ctx) { return sectorForm(null, ctx); });
    P.route(BASE + '/sectors/{id}/edit', PANEL, function (ctx) {
        const r = DB.find('sectors', ctx.params.id);
        return r ? sectorForm(r, ctx) : P.notFound('Sector not found.');
    });

    /* ================= Scheme Master for Super Admin (same screens as Data Entry) ================= */
    if (P.registerSchemeScreens) { P.registerSchemeScreens(BASE + '/schemes', PANEL, crumbs); }

    /* ================= Masters hub (removed) ================= */
    P.route(BASE, PANEL, function () {
        P.go('/admin/dashboard');
        return false;
    });
})(window);
