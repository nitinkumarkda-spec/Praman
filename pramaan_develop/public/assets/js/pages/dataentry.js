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
            const maps = DB.where('scheme_users', { scheme_id: s.scheme_id, is_active: 1 });
            return Object.assign({}, s, {
                sector_count: secs.length, sector_names: secs.map(function (x) { return x.sector_name; }).join(', '),
                mapped_roles: new Set(maps.map(function (m) { return m.role_id; })).size, mapped_users: maps.length,
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
            search: function (s) { return [s.scheme_name, s.scheme_code]; },
            sortable: {
                code: function (s) { return s.scheme_code; }, name: function (s) { return s.scheme_name; }, type: function (s) { return s.scheme_type; },
                year: function (s) { return s.year_of_scheme; }, sectors: function (s) { return s.sector_count; },
                properties: function (s) { return s.property_count; }
            }
        });
        const filters = '<select name="type" class="form-select" aria-label="Scheme type" data-auto-submit>' + L.options(L.SCHEME_TYPES, q.type, 'All types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive' }, q.status, 'All status') + '</select>';
        return {
            title: 'Scheme Master',
            html: (crumbs ? crumbs('Scheme Master') : '') + P.pageHeader(icon('fa-map') + ' Scheme Master', 'Schemes with their sectors and the role users who will verify properties in them.',
                '<a href="#' + base + '/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a>') +
                '<div class="card">' + P.list_toolbar(p, 'Search scheme name or code…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'code', 'Code') + P.th_sort(p, 'name', 'Scheme') + P.th_sort(p, 'type', 'Type') +
                P.th_sort(p, 'year', 'Year') + P.th_sort(p, 'sectors', 'Sectors') + '<th>Role mapping</th>' + P.th_sort(p, 'properties', 'Properties') +
                '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (s) {
                    const on = s.is_active === 1;
                    return '<tr><td data-label="Code"><span class="mono">' + e(s.scheme_code) + '</span></td><td data-label="Scheme"><strong>' + e(s.scheme_name) + '</strong></td>' +
                        '<td data-label="Type">' + e(L.SCHEME_TYPES[s.scheme_type] || s.scheme_type) + '</td>' +
                        '<td data-label="Year">' + s.year_of_scheme + '</td><td data-label="Sectors"><span class="badge badge-info">' + s.sector_count + '</span>' +
                        '<span class="cell-sub truncate" title="' + e(s.sector_names) + '">' + e(s.sector_names || '—') + '</span></td>' +
                        '<td data-label="Role mapping">' + s.mapped_roles + ' roles<span class="cell-sub">' + s.mapped_users + ' users</span></td>' +
                        '<td data-label="Properties">' + s.property_count + '</td><td data-label="Status">' + P.active_badge(s.is_active) + '</td>' +
                        '<td class="actions"><div class="btn-group"><a href="#' + base + '/' + s.scheme_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit scheme" aria-label="Edit scheme">' + icon('fa-pen') + '</a>' +
                        '<form method="post" data-action="scheme-toggle" data-confirm="' + e(on ? 'Deactivate scheme "' + s.scheme_name + '"? No new property can be added in it.' : 'Activate scheme "' + s.scheme_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                        '<input type="hidden" name="id" value="' + s.scheme_id + '">' +
                        (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                            : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') + '</form></div></td></tr>';
                }).join('') : P.empty_state(10, p.hasFilters(), 'No schemes yet — create the first one')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }

    function sectorNewRow(value) {
        return '<div class="sector-row" data-repeater-item><div class="sector-input">' +
            '<input type="text" name="sectors_new[]" class="form-control" maxlength="80" value="' + e(value || '') + '" placeholder="Sector name, e.g. Sector-4" aria-label="New sector name" data-rules="max:80|text" data-label="Sector name" data-sector-name data-sector-new></div>' +
            '<button type="button" class="btn btn-light btn-sm text-danger" data-repeater-remove aria-label="Remove sector">' + icon('fa-trash-can') + ' <span>Remove</span></button></div>';
    }
    function schemeForm(scheme, base, crumbs) {
        const isEdit = !!scheme;
        const v = function (k) { return isEdit ? scheme[k] : ''; };
        const sectors = isEdit ? DB.Scheme.sectors(scheme.scheme_id) : [];
        const mapping = isEdit ? DB.Scheme.mapping(scheme.scheme_id) : {};
        const options = DB.Scheme.roleUserOptions();

        let html = (crumbs ? crumbs('Scheme Master', isEdit ? 'Edit' : 'Add') : '') + P.pageHeader(icon('fa-map') + ' ' + (isEdit ? 'Edit Scheme' : 'Create Scheme'),
            isEdit ? 'Changes to sectors and role mapping apply to NEW properties only — existing properties keep their snapshot.'
                : 'Fill scheme details, add all its sectors, tick "Done" and map a user for every role.',
            '<a href="#' + base + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
            '<form method="post" data-action="scheme-save" novalidate data-validate data-scheme-form data-confirm="' +
            (isEdit ? 'Save changes to this scheme?' : 'Create this scheme with the sectors and role mapping shown?') + '">' +
            '<input type="hidden" name="back" value="' + e(base) + '">' + (isEdit ? '<input type="hidden" name="id" value="' + scheme.scheme_id + '">' : '') +
            // 1. details
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">1</span> Scheme details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_name">Scheme name <span class="required">*</span></label>' +
            '<input type="text" id="scheme_name" name="scheme_name" class="form-control" maxlength="150" value="' + e(v('scheme_name')) + '" placeholder="e.g. Ravindra Nagar Awasiya Yojana" data-rules="required|min:3|max:150|text" data-label="Scheme name"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="scheme_code">Scheme code <span class="required">*</span></label>' +
            '<input type="text" id="scheme_code" name="scheme_code" class="form-control mono" maxlength="30" value="' + e(v('scheme_code')) + '" placeholder="e.g. RNY-2019" data-uppercase ' +
            (isEdit ? 'readonly' : 'data-rules="required|ucode" data-label="Scheme code"') + '>' +
            '<div class="form-hint">' + (isEdit ? 'Code cannot be changed.' : 'CAPITAL letters, digits, / _ - . Cannot be changed later.') + '</div><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="scheme_type">Scheme type <span class="required">*</span></label>' +
            '<select id="scheme_type" name="scheme_type" class="form-select" data-rules="required" data-label="Scheme type">' + L.options(L.SCHEME_TYPES, v('scheme_type'), 'Select type') + '</select><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="year_of_scheme">Year of scheme <span class="required">*</span></label>' +
            '<input type="number" id="year_of_scheme" name="year_of_scheme" class="form-control" min="1950" max="2100" value="' + e(v('year_of_scheme')) + '" placeholder="e.g. 2019" data-rules="required|year" data-label="Year of scheme"><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +
            // 2. sectors
            '<div class="card"><div class="card-header"><h3 class="card-title"><span class="step-no">2</span> Sectors <span class="required">*</span></h3>' +
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
            '<div class="sector-list" data-repeater>' + (isEdit ? '' : sectorNewRow('')) + '</div>' +
            '<template data-repeater-template>' + sectorNewRow('').replace('class="sector-row"', 'class="sector-row anim-fade-up"') + '</template>' +
            '<button type="button" class="btn btn-outline btn-sm mt-2" data-repeater-add>' + icon('fa-plus') + ' <span>Add another sector</span></button>' +
            '<div class="form-group done-box mt-3"><label class="check"><input type="checkbox" name="sectors_done" value="1" id="sectors_done" data-rules="required" data-label="the “All sectors added — Done” checkbox">' +
            '<span><strong>All sectors added — Done</strong> <span class="required">*</span><br><small class="text-muted">Tick after adding all sectors. The scheme is saved only with at least one sector and this box ticked.</small></span></label>' +
            '<div class="invalid-feedback"></div></div></div></div>' +
            // 3. mapping
            '<div class="card"><div class="card-header"><div><h3 class="card-title"><span class="step-no">3</span> Role – user mapping</h3>' +
            '<span class="text-muted small">These users will verify every property created in this scheme.</span></div>' +
            '<div class="mapping-tools"><span class="mapping-count" data-map-count>0 selected</span>' +
            '<label class="select-all-chip"><input type="checkbox" data-map-all> <span>Select all roles</span></label></div></div><div class="card-body">' +
            (options.length ? '' : '<div class="alert alert-warning">' + icon('fa-triangle-exclamation') + '<span>No active roles exist. Ask the Super Admin to create roles.</span></div>') +
            '<div class="mapping-grid">' + options.map(function (opt) {
                const r = opt.role;
                const picked = mapping[r.role_id] || [];
                return '<fieldset class="mapping-card" data-mapping-role data-mandatory="' + r.is_mandatory + '" data-role-name="' + e(r.role_name) + '">' +
                    '<legend class="mapping-head"><span>' + icon('fa-user-tie') + ' ' + e(r.role_name) + ' <small class="mapping-picked" data-role-count></small></span>' +
                    '<label class="select-all-chip small"' + (opt.users.length ? '' : ' hidden') + '><input type="checkbox" data-role-all aria-label="' + e('Select all users of ' + r.role_name) + '"> <span>All</span></label></legend>' +
                    '<div class="mapping-users">' + (opt.users.length ? opt.users.map(function (u) {
                        return '<label class="check mapping-user"><input type="checkbox" name="role_users_' + r.role_id + '[]" value="' + u.user_id + '"' + (picked.indexOf(u.user_id) !== -1 ? ' checked' : '') + '>' +
                            '<span>' + e(u.name) + '<br><small class="text-muted">' + e(u.designation + ' · ' + u.emp_code) + '</small></span></label>';
                    }).join('') : '<p class="text-muted small">' + icon('fa-circle-info') + ' No active user in this role.</p>') + '</div>' +
                    '<div class="invalid-feedback" data-mapping-error></div></fieldset>';
            }).join('') + '</div></div></div>' +
            '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') +
            ' <span>' + (isEdit ? 'Update Scheme' : 'Create Scheme') + '</span></button>' +
            '<a href="#' + base + '" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return { title: isEdit ? 'Edit Scheme' : 'Create Scheme', html: html, after: initMappingSelectAll };
    }
    /** "All" checkbox per role + "Select all roles" in the card header (tri-state, live counts). */
    function initMappingSelectAll(root) {
        const form = root.querySelector('form[data-scheme-form]');
        if (!form) { return; }
        const cards = Array.prototype.slice.call(form.querySelectorAll('[data-mapping-role]'));
        const master = form.querySelector('[data-map-all]');
        const userBoxes = function (card) { return Array.prototype.slice.call(card.querySelectorAll('.mapping-users input[type="checkbox"]')); };
        function sync() {
            let all = 0, on = 0;
            cards.forEach(function (card) {
                const boxes = userBoxes(card);
                const n = boxes.filter(function (b) { return b.checked; }).length;
                const ra = card.querySelector('[data-role-all]');
                ra.checked = boxes.length > 0 && n === boxes.length;
                ra.indeterminate = n > 0 && n < boxes.length;
                card.querySelector('[data-role-count]').textContent = boxes.length ? '(' + n + '/' + boxes.length + ')' : '';
                card.classList.toggle('has-picked', n > 0);
                all += boxes.length; on += n;
            });
            master.checked = all > 0 && on === all;
            master.indeterminate = on > 0 && on < all;
            form.querySelector('[data-map-count]').textContent = on + ' of ' + all + ' users selected';
        }
        function fitTwo() {
            form.querySelectorAll('.mapping-users').forEach(function (box) {
                const rows = box.querySelectorAll('.mapping-user');
                box.classList.toggle('is-scrollable', rows.length > 2);
                box.style.maxHeight = rows.length > 2 ? (rows[0].offsetHeight + rows[1].offsetHeight + 2) + 'px' : '';
                const more = box.parentNode.querySelector('[data-more-users]');
                if (more) { more.hidden = rows.length <= 2 || box.scrollTop + box.clientHeight >= box.scrollHeight - 4; }
            });
        }
        form.querySelectorAll('.mapping-users').forEach(function (box) {
            const rows = box.querySelectorAll('.mapping-user').length;
            if (rows > 2) {
                const more = document.createElement('div');
                more.className = 'mapping-more';
                more.setAttribute('data-more-users', '');
                more.innerHTML = '<i class="fa-solid fa-angles-down" aria-hidden="true"></i> ' + (rows - 2) + ' more — scroll';
                box.parentNode.insertBefore(more, box.nextSibling);
            }
            box.addEventListener('scroll', fitTwo, { passive: true });
        });
        window.addEventListener('resize', fitTwo);
        window.setTimeout(fitTwo, 0);
        form.addEventListener('change', function (ev) {
            const t = ev.target;
            if (t.matches('[data-role-all]')) {
                userBoxes(t.closest('[data-mapping-role]')).forEach(function (b) { b.checked = t.checked; });
                t.closest('[data-mapping-role]').dispatchEvent(new Event('change', { bubbles: true })); // clears the card's error
            } else if (t.matches('[data-map-all]')) {
                cards.forEach(function (card) { userBoxes(card).forEach(function (b) { b.checked = t.checked; }); });
                cards.forEach(function (card) { card.classList.remove('has-error'); const er = card.querySelector('[data-mapping-error]'); if (er && t.checked) { er.textContent = ''; } });
            }
            sync();
        });
        sync();
    }
    /** Scheme list + create + edit screens under any base path (Data Entry and Super Admin masters). */
    P.registerSchemeScreens = function (base, panel, crumbs) {
        P.route(base, panel, function (ctx) { return schemeListPage(ctx, base, crumbs); });
        P.route(base + '/create', panel, function () { return schemeForm(null, base, crumbs); });
        P.route(base + '/{id}/edit', panel, function (ctx) {
            const s = DB.find('schemes', ctx.params.id);
            return s ? schemeForm(s, base, crumbs) : P.notFound('Scheme not found.');
        });
    };
    P.registerSchemeScreens('/data-entry/schemes', PANEL, null);


    /* ================= Property Register ================= */
    P.propertyRows = function () {
        return DB.where('properties', { is_active: 1 }).map(function (p) {
            const sc = DB.find('schemes', p.scheme_id);
            const se = DB.find('sectors', p.sector_id);
            const ru = DB.Property.roleUsers(p.property_id);
            const cu = DB.find('users', p.created_by);
            const round = p.current_round_id ? DB.find('rounds', p.current_round_id) : null;
            const tasks = round ? DB.where('tasks', { round_id: round.round_id }) : [];
            return Object.assign({}, p, {
                scheme_name: sc.scheme_name, scheme_code: sc.scheme_code, sector_name: se.sector_name,
                role_count: new Set(ru.map(function (x) { return x.role_id; })).size,
                role_users: ru.map(function (x) { return x.role_name + ': ' + x.name; }).join(', '),
                created_by_name: cu ? cu.name : '', round_no: round ? round.round_no : null,
                tasks_total: tasks.length, tasks_approved: tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length
            });
        });
    };
    P.propertyFilters = function (q, extraStatuses) {
        const schemes = {};
        DB.all('schemes').sort(P.by(function (s) { return s.scheme_name; })).forEach(function (s) { schemes[s.scheme_id] = s.scheme_name + ' (' + s.scheme_code + ')'; });
        const sectors = {};
        if (q.scheme_id) { DB.Scheme.sectors(q.scheme_id).forEach(function (s) { sectors[s.sector_id] = s.sector_name; }); }
        const none = !Object.keys(sectors).length;
        return '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit data-reset-on-change="sector_id">' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            '<select name="sector_id" class="form-select" aria-label="Sector" data-auto-submit' + (none ? ' disabled' : '') + '>' + L.options(sectors, q.sector_id, none ? 'Select scheme first' : 'All sectors') + '</select>' +
            '<select name="type" class="form-select" aria-label="Plot type" data-auto-submit>' + L.options(L.PROPERTY_TYPES, q.type, 'All types') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options(Object.assign({}, L.PROPERTY_STATUSES, extraStatuses || {}), q.status, 'All status') + '</select>';
    };
    P.filterProperties = function (rows, q) {
        if (q.scheme_id) { rows = rows.filter(function (r) { return String(r.scheme_id) === q.scheme_id; }); }
        if (q.sector_id) { rows = rows.filter(function (r) { return String(r.sector_id) === q.sector_id; }); }
        if (q.type) { rows = rows.filter(function (r) { return r.property_type === q.type; }); }
        if (q.status === 'REVERIFY_DUE') { rows = rows.filter(function (r) { return DB.Verification.isDue(r); }); }
        else if (q.status) { rows = rows.filter(function (r) { return r.current_status === q.status; }); }
        return rows;
    };
    P.propertySortable = {
        code: function (r) { return r.property_code; }, scheme: function (r) { return r.scheme_name; }, sector: function (r) { return r.sector_name; },
        plot: function (r) { return r.plot_no; }, type: function (r) { return r.property_type; }, area: function (r) { return r.area_sqm; },
        status: function (r) { return r.current_status; }, created: function (r) { return r.property_id; }
    };
    P.propertySearch = function (r) { return [r.plot_no, r.property_code, r.scheme_name, r.sector_name, r.ward_name, r.assembly_name]; };

    P.route('/data-entry/properties', PANEL, function (ctx) {
        const q = ctx.query;
        const p = P.paginate(P.filterProperties(P.propertyRows(), q), ctx, {
            sort: 'created', dir: 'desc', filters: ['scheme_id', 'sector_id', 'assembly_id', 'type', 'status'],
            search: P.propertySearch, sortable: P.propertySortable
        });
        return {
            title: 'Property Register',
            html: P.pageHeader(icon('fa-building') + ' Property Register', 'Vacant properties. Editable only while status is <strong>Listed</strong>.',
                '<a href="#/data-entry/properties/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Property</span></a>') +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., property code, scheme, sector…', P.propertyFilters(q)) +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr>' +
                P.th_sort(p, 'code', 'Code') + P.th_sort(p, 'scheme', 'Scheme') + P.th_sort(p, 'sector', 'Sector') + P.th_sort(p, 'plot', 'Plot no.') +
                P.th_sort(p, 'type', 'Type') + P.th_sort(p, 'area', 'Area') + '<th>Role users</th>' + P.th_sort(p, 'status', 'Status') + P.th_sort(p, 'created', 'Created') +
                '<th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (r) {
                    return '<tr><td data-label="Code"><a href="#/data-entry/properties/' + r.property_id + '" class="mono">' + e(r.property_code) + '</a></td>' +
                        '<td data-label="Scheme">' + e(r.scheme_name) + '<span class="cell-sub">' + e(r.scheme_code) + '</span></td><td data-label="Sector">' + e(r.sector_name) + '<span class="cell-sub">' + e(r.ward_name) + '</span></td>' +
                        '<td data-label="Plot no."><strong>' + e(r.plot_no) + '</strong></td><td data-label="Type">' + e(L.PROPERTY_TYPES[r.property_type]) + '</td>' +
                        '<td data-label="Area" class="nowrap">' + P.fmt_num(r.area_value, 2) + ' ' + (r.area_unit === 'SQFT' ? 'sq ft' : 'sq m') +
                        (r.area_unit === 'SQFT' ? '<span class="cell-sub">= ' + P.fmt_num(r.area_sqm, 2) + ' sq m</span>' : '') + '</td>' +
                        '<td data-label="Role users"><span class="badge badge-muted" title="' + e(r.role_users) + '">' + icon('fa-user-tie') + ' ' + r.role_count + ' roles</span></td>' +
                        '<td data-label="Status">' + P.status_badge(r.current_status) + '</td>' +
                        '<td data-label="Created">' + e(P.fmt_date(r.created_at)) + '<span class="cell-sub">' + e(r.created_by_name) + '</span></td><td class="actions">' +
                        '<div class="btn-group"><a href="#/data-entry/properties/' + r.property_id + '" class="btn btn-light btn-sm btn-icon-only" title="View details &amp; timeline" aria-label="View details and timeline">' + icon('fa-timeline') + '</a>' +
                        (r.current_status === 'LISTED' ? '<a href="#/data-entry/properties/' + r.property_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="Edit" aria-label="Edit">' + icon('fa-pen') + '</a>'
                            : '<span class="btn btn-sm btn-light btn-icon-only is-disabled" title="Locked — only Listed properties can be edited">' + icon('fa-lock') + '</span>') + '</div></td></tr>';
                }).join('') : P.empty_state(10, p.hasFilters(), 'No properties yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    });

    function propertyForm(prop) {
        const isEdit = !!prop;
        const v = function (k, d) { return isEdit ? (prop[k] == null ? '' : prop[k]) : (d || ''); };
        const schemes = isEdit ? [] : DB.Scheme.eligible();
        const sc = isEdit ? DB.find('schemes', prop.scheme_id) : null;
        let html = P.pageHeader(icon('fa-building-circle-check') + ' ' + (isEdit ? 'Edit Property' : 'Add Property'),
            isEdit ? e(prop.property_code + ' · ' + sc.scheme_name + ' · status Listed') : 'Step 1 select scheme → Step 2 select sector → Step 3 confirm → plot details.',
            '<a href="#/data-entry/properties" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>');
        if (!isEdit && !schemes.length) {
            return {
                title: 'Add Property',
                html: html + '<div class="card"><div class="card-body"><div class="empty-state">' + icon('fa-map-location-dot', 'fa-bounce empty-icon') +
                    '<p class="empty-title">No scheme is ready for property entry</p><p class="text-muted mb-3">A scheme appears here only when it is active and has at least one active sector.</p>' +
                    '<a href="#/data-entry/schemes/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Create Scheme</span></a></div></div></div>'
            };
        }
        const sectorOpts = isEdit ? '<option value="">Select sector</option>' + DB.Scheme.sectors(prop.scheme_id, true).map(function (s) {
            return '<option value="' + s.sector_id + '"' + (s.sector_id === prop.sector_id ? ' selected' : '') + '>' + e(s.sector_name) + '</option>';
        }).join('') : '<option value="">Select scheme first</option>';
        const roleUsers = isEdit ? DB.Property.roleUsers(prop.property_id) : [];
        html += '<form method="post" data-action="property-save" novalidate data-validate data-property-form>' + (isEdit ? '<input type="hidden" name="id" value="' + prop.property_id + '">' : '') +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-location-dot') + ' Location</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="scheme_id"><span class="step-no">1</span> Scheme <span class="required">*</span></label>' +
            (isEdit ? '<input type="text" class="form-control" value="' + e(sc.scheme_name + ' (' + sc.scheme_code + ')') + '" readonly id="scheme_id" data-scheme-label="' + e(sc.scheme_name) + '">' +
                '<div class="form-hint">Scheme cannot be changed after creation (role users were assigned from it).</div>'
                : '<select id="scheme_id" name="scheme_id" class="form-select" data-rules="required" data-label="Scheme" data-sector-source="local" data-sector-target="sector_id">' +
                '<option value="">Select scheme</option>' + schemes.map(function (s) { return '<option value="' + s.scheme_id + '">' + e(s.scheme_name + ' (' + s.scheme_code + ')') + '</option>'; }).join('') +
                '</select><div class="form-hint">Only schemes with at least one active sector are listed.</div>') + '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="sector_id"><span class="step-no">2</span> Sector <span class="required">*</span></label>' +
            '<select id="sector_id" name="sector_id" class="form-select" data-rules="required" data-label="Sector" data-selected="' + e(v('sector_id')) + '"' + (isEdit ? '' : ' disabled') + '>' + sectorOpts + '</select>' +
            '<div class="form-hint" data-sector-hint>Sectors load from the selected scheme.</div><div class="invalid-feedback"></div></div></div>' +
            '<div class="form-group confirm-box" data-confirm-box><p class="confirm-question"><span class="step-no">3</span> Should this property be created in Scheme <strong data-confirm-scheme>[—]</strong>, Sector <strong data-confirm-sector>[—]</strong>?</p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="confirm_location" value="YES" data-rules="required" data-label="the confirmation (Yes / No)"> <span><strong>Yes</strong>, that is correct</span></label>' +
            '<label class="check"><input type="radio" name="confirm_location" value="NO"> <span><strong>No</strong>, I need to change it</span></label></div><div class="invalid-feedback"></div>' +
            '<p class="form-hint" data-confirm-no hidden>' + icon('fa-circle-info') + ' Please change the scheme / sector above. Save stays disabled until you answer <strong>Yes</strong>.</p></div></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-ruler-combined') + ' Plot details</h3></div><div class="card-body"><div class="form-grid">' +
            '<div class="form-group"><label class="form-label" for="plot_no">Plot no. <span class="required">*</span></label><input type="text" id="plot_no" name="plot_no" class="form-control" maxlength="30" value="' + e(v('plot_no')) + '" placeholder="e.g. 112-A" data-uppercase data-rules="required|plotno" data-label="Plot no."><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="property_type">Plot type <span class="required">*</span></label><select id="property_type" name="property_type" class="form-select" data-rules="required" data-label="Plot type">' + L.options(L.activeMap('plot_types', 'code', 'name', v('property_type')), v('property_type'), 'Select type') + '</select><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="area_value">Area <span class="required">*</span></label><input type="text" id="area_value" name="area_value" class="form-control" inputmode="decimal" maxlength="13" value="' + e(v('area_value')) + '" placeholder="e.g. 150.50" data-rules="required|decimal:2|positive" data-label="Area" data-area-value><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="area_unit">Area unit <span class="required">*</span></label><select id="area_unit" name="area_unit" class="form-select" data-rules="required" data-label="Area unit" data-area-unit>' + L.options(L.AREA_UNITS, v('area_unit', 'SQM'), 'Select unit') + '</select><div class="form-hint" data-area-preview>1 sq ft = 0.092903 sq m</div><div class="invalid-feedback"></div></div>' +
            '<div class="form-group span-2"><label class="form-label" for="remarks">Remarks</label><textarea id="remarks" name="remarks" class="form-control" maxlength="500" rows="2" placeholder="Optional notes" data-rules="max:500|text" data-label="Remarks">' + e(v('remarks')) + '</textarea><div class="invalid-feedback"></div></div>' +
            '</div></div></div>' +
            (roleUsers.length ? '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-user-tie') + ' Assigned role users (snapshot)</h3></div><div class="card-body"><div class="chip-list">' +
                roleUsers.map(function (ru) { return '<span class="chip"><strong>' + e(ru.role_name) + ':</strong> ' + e(ru.name) + '</span>'; }).join('') + '</div></div></div>' : '') +
            '<div class="form-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…" data-requires-yes>' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update Property' : 'Save Property') + '</span></button>' +
            (isEdit ? '' : '<button type="submit" name="save_and_new" value="1" class="btn btn-gold btn-lg" data-loading-text="Saving…" data-requires-yes>' + icon('fa-plus') + ' <span>Save &amp; add another</span></button>') +
            '<a href="#/data-entry/properties" class="btn btn-light btn-lg">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form>';
        return {
            title: isEdit ? 'Edit Property' : 'Add Property', html: html,
            after: null
        };
    }
    P.route('/data-entry/properties/create', PANEL, function () { return propertyForm(null); });
    P.route('/data-entry/properties/{id}', PANEL, function (ctx) {
        const prop = P.propertyRows().find(function (r) { return r.property_id === ctx.params.id; });
        if (!prop) { return P.notFound('Property not found.'); }
        const ru = DB.Property.roleUsers(prop.property_id);
        return {
            title: 'Property ' + prop.plot_no,
            html: P.pageHeader(icon('fa-building') + ' Plot ' + e(prop.plot_no) + ' ' + P.status_badge(prop.current_status), e(prop.property_code + ' · ' + prop.scheme_name + ' · ' + prop.sector_name),
                '<a href="#/data-entry/properties" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>' +
                (prop.current_status === 'LISTED' ? '<a href="#/data-entry/properties/' + prop.property_id + '/edit" class="btn btn-primary">' + icon('fa-pen') + ' <span>Edit</span></a>' : '')) +
                '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
                '<dt>Property code</dt><dd class="mono">' + e(prop.property_code) + '</dd><dt>Scheme</dt><dd>' + e(prop.scheme_name + ' (' + prop.scheme_code + ')') + '</dd>' +
                '<dt>Sector</dt><dd>' + e(prop.sector_name) + '</dd><dt>Plot type</dt><dd>' + e(L.PROPERTY_TYPES[prop.property_type] || prop.property_type) + '</dd>' +
                '<dt>Area</dt><dd>' + P.fmt_num(prop.area_value, 2) + ' ' + e(L.AREA_UNITS[prop.area_unit]) + '</dd><dt>Created</dt><dd>' + e(P.fmt_datetime(prop.created_at) + ' · ' + prop.created_by_name) + '</dd>' +
                (prop.remarks ? '<dt>Remarks</dt><dd>' + e(prop.remarks) + '</dd>' : '') + '</dl></div></div>' +
                '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-user-tie') + ' Assigned role users</h3></div><div class="card-body"><div class="chip-list">' +
                (ru.length ? ru.map(function (x) { return '<span class="chip"><strong>' + e(x.role_name) + ':</strong> ' + e(x.name) + '</span>'; }).join('') : '<span class="text-muted">None</span>') +
                '</div></div></div></div>' + P.propertyTimeline(prop.property_id)
        };
    });
    P.route('/data-entry/properties/{id}/edit', PANEL, function (ctx) {
        const p = DB.find('properties', ctx.params.id);
        if (!p || !p.is_active) { return P.notFound('Property not found.'); }
        if (p.current_status !== 'LISTED') {
            P.flash('warning', 'Only properties in "Listed" status can be edited. This one is ' + P.label(p.current_status) + '.');
            P.go('/data-entry/properties');
            return false;
        }
        return propertyForm(p);
    });

})(window);
