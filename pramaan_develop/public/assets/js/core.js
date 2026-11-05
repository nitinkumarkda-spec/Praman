/* ===== js/core.js ===== */
/* =====================================================================
   PRAMAAN — core: helpers (same names as app/Core/helpers.php),
   auth/session, paginator, layout (sidebar/topbar), hash router and
   the POST-action dispatcher used by app.js.
   ===================================================================== */
(function (window, document) {
    'use strict';

    const Praman = window.Praman = window.Praman || {};
    const DB = window.PramanDB;

    // Branding, limits and code lists come from the server (App Settings + app/Core/Lookups.php)
    const S = function (key, fallback) { return DB.setting(key, fallback); };
    const APP = {
        get name() { return S('app_name', 'PRAMAAN'); },
        get tagline() { return S('app_tagline', 'Property Verification System'); },
        get full_name() { return S('app_full_name', ''); },
        get org() { return S('org_name', ''); },
        get org_short() { return S('org_short', ''); },
        get website() { return S('org_website', ''); }
    };
    // same object everywhere (P.PANELS[x]); refreshed whenever new state arrives
    const PANELS = {};
    function syncLookups() {
        Object.keys(PANELS).forEach(function (k) { delete PANELS[k]; });
        Object.assign(PANELS, DB.lookup('panels'));
    }
    syncLookups();
    const HOME = { ADMIN: '/admin/dashboard', SUPER_ADMIN: '/admin/dashboard', DATA_ENTRY: '/data-entry/dashboard', OIC: '/oic/dashboard', ROLE_USER: '/role/dashboard' };
    const SECURITY = {
        get password_min() { return Number(S('password_min_length', 10)); }
    };

    const Lookups = {
        get AREAS() { return masterMap('areas', 'area_id', 'area_name', false); },
        get SCHEME_TYPES() { return DB.lookup('scheme_types'); },
        get STATUS_COLORS() { return DB.lookup('status_colors'); },
        get STATUS_ICONS() { return DB.lookup('status_icons'); },
        /* masters are linked by id: plot_type_id => name, status_id => name */
        get PROPERTY_TYPES() { return masterMap('plot_types', 'plot_type_id', 'name', false); },
        get PROPERTY_STATUSES() { return masterMap('statuses', 'status_id', 'name', false); },
        /** Only active rows (+ the current value, so old records still show it) — for entry forms. */
        activeMap: function (table, key, name, current) {
            const m = {};
            DB.all(table).sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0) || String(a[name]).localeCompare(String(b[name])); })
                .forEach(function (r) { if (r.is_active || String(r[key]) === String(current)) { m[r[key]] = r[name] + (r.is_active ? '' : ' (inactive)'); } });
            return m;
        },
        /* Unit Master (sq m, sq ft, sq yard …) — unit_id => name; the base unit (sq m) is the system row */
        get BASE_UNIT() { const u = DB.first('units', { is_system: 1 }); return u ? u.unit_id : null; },
        get AREA_UNITS() { return masterMap('units', 'unit_id', 'name', false); },
        unit: function (id) { return id ? DB.find('units', id) : null; },
        unitSymbol: function (id) { const u = Lookups.unit(id); return u ? u.symbol : ''; },
        unitName: function (id) { const u = Lookups.unit(id); return u ? u.name : ''; },
        /** Square metres in 1 unit. */
        unitFactor: function (id) { const u = Lookups.unit(id); return u ? Number(u.to_sqm) : 1; },
        toSqm: function (v, unit) { return Math.round(v * Lookups.unitFactor(unit) * 10000) / 10000; },
        convert: function (v, from, to) { return from === to ? v : v * Lookups.unitFactor(from) / Lookups.unitFactor(to); },
        /** "150.00 sq ft" (+ "= 13.94 sq m" when not in sq m and withSqm is set). */
        area: function (value, unit, sqm) {
            if (value == null || value === '') { return '—'; } // area is optional on a property
            return fmt_num(value, 2) + ' ' + Lookups.unitSymbol(unit) + (sqm != null && unit !== Lookups.BASE_UNIT ? ' (= ' + fmt_num(sqm, 2) + ' ' + Lookups.unitSymbol(Lookups.BASE_UNIT) + ')' : '');
        },
        options: function (map, selected, placeholder) {
            let html = placeholder ? '<option value="">' + e(placeholder) + '</option>' : '';
            Object.keys(map).forEach(function (k) {
                html += '<option value="' + e(k) + '"' + (String(k) === String(selected == null ? '' : selected) ? ' selected' : '') + '>' + e(map[k]) + '</option>';
            });
            return html;
        }
    };

    function masterMap(table, key, name) {
        const m = {};
        DB.all(table).sort(function (a, b) { return (a.sort_order || 0) - (b.sort_order || 0); }).forEach(function (r) { m[r[key]] = r[name]; });
        return m;
    }

    /* ---------------- helpers ---------------- */
    function e(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c];
        });
    }
    function url(path, query) {
        let u = BASE + path;
        if (query) {
            const qs = Object.keys(query).filter(function (k) { return query[k] !== '' && query[k] != null; })
                .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(query[k]); }).join('&');
            if (qs) { u += '?' + qs; }
        }
        return u;
    }
    function parseDate(v) {
        if (!v) { return null; }
        const m = String(v).match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?/);
        if (!m) { return null; }
        return new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0));
    }
    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    function p2(n) { return String(n).padStart(2, '0'); }
    function fmt_date(v) {
        const d = parseDate(v);
        return d ? p2(d.getDate()) + '-' + p2(d.getMonth() + 1) + '-' + d.getFullYear() : '—';
    }
    /** "in 5 days" / "tomorrow" / "today" / "2 days ago" for a Y-m-d date. */
    function dueText(v) {
        const d = parseDate(v);
        if (!d) { return '—'; }
        const t = new Date(); t.setHours(0, 0, 0, 0); d.setHours(0, 0, 0, 0);
        const n = Math.round((d - t) / 86400000);
        return n > 1 ? 'in ' + n + ' days' : n === 1 ? 'tomorrow' : n === 0 ? 'today' : n === -1 ? '1 day ago' : (-n) + ' days ago';
    }
    function fmt_datetime(v) {
        const d = parseDate(v);
        if (!d) { return '—'; }
        let h = d.getHours();
        const ap = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        return fmt_date(v) + ' ' + p2(h) + ':' + p2(d.getMinutes()) + ' ' + ap;
    }
    function fmt_num(v, dec) {
        return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: dec || 0, maximumFractionDigits: dec || 0 });
    }
    function label(code) {
        return code == null ? '—' : String(code).toLowerCase().replace(/_/g, ' ').replace(/(^|\s)\S/g, function (c) { return c.toUpperCase(); });
    }
    const BADGES = {
        LISTED: ['badge-info', 'fa-list'], UNDER_VERIFICATION: ['badge-warning', 'fa-hourglass-half'],
        AUCTION_READY: ['badge-success', 'fa-circle-check'], NOT_READY: ['badge-danger', 'fa-circle-xmark'],
        SENT_FOR_AUCTION: ['badge-primary', 'fa-gavel'], SOLD: ['badge-dark', 'fa-handshake'], WITHHELD: ['badge-muted', 'fa-pause'],
        ARCHIVED: ['badge-muted', 'fa-box-archive'], OPEN: ['badge-warning', 'fa-folder-open'], CLOSED: ['badge-dark', 'fa-lock'],
        SUPERSEDED: ['badge-muted', 'fa-clock-rotate-left'], CANCELLED: ['badge-muted', 'fa-ban'], PENDING: ['badge-warning', 'fa-clock'],
        APPROVED: ['badge-success', 'fa-check'], REJECTED: ['badge-danger', 'fa-xmark'], NO_RESPONSE: ['badge-danger', 'fa-bell-slash'],
        NOT_REQUIRED: ['badge-muted', 'fa-minus'], OVERDUE: ['badge-danger', 'fa-stopwatch'], READY: ['badge-success', 'fa-circle-check'],
        ACTIVE: ['badge-success', 'fa-circle'], INACTIVE: ['badge-muted', 'fa-circle']
    };
    /** Fixed ids of the workflow's statuses in the Status Master (same as App\Codes\PropertyStatus). */
    const ST = DB.ST;
    /** Name of a property status (status_id) from the Status Master. */
    function statusName(id) { const s = DB.find('statuses', id); return s ? s.name : '—'; }
    function status_badge(s) {
        const ms = typeof s === 'number' ? DB.find('statuses', s) : null; // property statuses (status_id): name / colour / icon from Status Master
        if (ms) { return '<span class="badge badge-' + e(ms.color) + '"><i class="fa-solid ' + e(ms.icon) + '" aria-hidden="true"></i> ' + e(ms.name) + '</span>'; }
        const b = BADGES[s] || ['badge-muted', 'fa-circle-info'];
        return '<span class="badge ' + b[0] + '"><i class="fa-solid ' + b[1] + '" aria-hidden="true"></i> ' + e(label(s)) + '</span>';
    }
    function active_badge(v) { return status_badge(Number(v) === 1 ? 'ACTIVE' : 'INACTIVE'); }
    function icon(name, extra) { return '<i class="fa-solid ' + name + (extra ? ' ' + extra : '') + '" aria-hidden="true"></i>'; }
    function by(fn, dir) {
        const d = dir === 'desc' ? -1 : 1;
        return function (a, b) {
            const x = fn(a), y = fn(b);
            if (x == null && y == null) { return 0; }
            if (x == null) { return -1 * d; }
            if (y == null) { return 1 * d; }
            if (typeof x === 'number' && typeof y === 'number') { return (x - y) * d; }
            return String(x).localeCompare(String(y), undefined, { numeric: true, sensitivity: 'base' }) * d;
        };
    }
    function initials(name) {
        return String(name || '').trim().split(/\s+/).slice(0, 2).map(function (p) { return p.charAt(0).toUpperCase(); }).join('');
    }

    /* ---------------- Auth / session (the server session decides; this only reads it) ---------------- */
    const Auth = window.PramanAuth = {
        PANELS: PANELS,
        user: function () {
            const s = DB.session();
            if (!s) { return null; }
            const u = DB.find('users', s.user_id);
            if (!u || !u.is_active) { return null; }
            const r = u.role_id ? DB.find('roles', u.role_id) : null;
            return Object.assign({}, u, { role_name: r ? r.role_name : null });
        },
        id: function () { const u = Auth.user(); return u ? u.user_id : null; },
        check: function () { return !!Auth.user(); },
        impersonator: function () { const s = DB.session(); return s && s.impersonator_id ? DB.find('users', s.impersonator_id) : null; },
        /** Has the panel been given any one of these permissions (Super Admin → Permissions)? can('a.b', 'c.d') */
        can: function () {
            const s = DB.session();
            const mine = (s && s.permissions) || [];
            return Array.prototype.some.call(arguments, function (c) { return mine.indexOf(c) !== -1; });
        },
        home: function () {
            const u = Auth.user();
            if (!u) { return '/login'; }
            if (Number(u.role_id) === 1 || Auth.can('role.view', 'user.view', 'master.view', 'settings.view', 'audit.view')) {
                return '/admin/dashboard';
            }
            const roleId = Number(u.role_id);
            if (roleId === 7 || Auth.can('verification.send')) {
                return '/oic/dashboard';
            }
            const rObj = roleId ? DB.find('roles', roleId) : null;
            if (roleId > 1 && rObj && rObj.status_id) {
                return '/role/dashboard';
            }
            if (roleId === 6 || (Auth.can('scheme.view') && Auth.can('property.create', 'scheme.create'))) {
                return '/data-entry/dashboard';
            }
            if (roleId > 1) {
                return '/role/dashboard';
            }
            return '/admin/dashboard';
        }
    };

    /* ---------------- Server API ---------------- */
    const BASE = ((document.querySelector('meta[name="app-base"]') || {}).content || '').replace(/\/+$/, '');
    Praman.BASE = BASE;
    function apiUrl(path) { return BASE + '/api/' + path; }
    /** Apply a fresh state from the server (rows + session + CSRF token). */
    function applyState(state) {
        if (!state) { return; }
        const before = Auth.id();
        DB.load(state);
        syncLookups();
        (state.flashes || []).forEach(function (f) { flash(f.type, f.message); });
        if (before && !Auth.id()) {
            const box = document.getElementById('toast-container');
            if (box) { box.innerHTML = ''; } // never show one user's toasts to the next user
        }
    }
    /**
     * POST an action to the server. body: FormData or plain object.
     * Resolves with the JSON reply ({ ok, message, field, flashes, go, state }).
     */
    Praman.api = function (action, body) {
        let fd = body;
        if (!(body instanceof FormData)) {
            fd = new FormData();
            Object.keys(body || {}).forEach(function (k) { if (body[k] != null) { fd.append(k, body[k]); } });
        }
        return window.fetch(apiUrl(action), {
            method: 'POST', body: fd, credentials: 'same-origin', cache: 'no-store',
            headers: { 'X-CSRF-Token': DB.csrf(), 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (res) {
            return res.json().catch(function () { return { ok: false, message: 'Unexpected server response (' + res.status + ').' }; }).then(function (json) {
                json.status = res.status;
                if (json.state) { applyState(json.state); }
                // signed out, or CSRF token out of date (e.g. another tab logged in again): take the fresh session + token
                if (res.status === 401 || json.csrf) { return Praman.refreshState().then(function () { return json; }); }
                return json;
            });
        }, function () {
            return { ok: false, message: 'Cannot reach the server. Check your connection and try again.' };
        });
    };
    /** GET the latest state (new notifications, other users' changes). */
    Praman.refreshState = function () {
        return window.fetch(apiUrl('state'), { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
            .then(function (res) { return res.json(); })
            .then(function (json) { if (json && json.state) { applyState(json.state); } return json; })
            .catch(function () { return null; });
    };

    /* ---------------- Flash messages ---------------- */
    let flashes = [];
    function flash(type, message) { flashes.push({ type: type, message: message }); }
    function renderFlash() {
        const icons = { success: 'fa-circle-check', error: 'fa-circle-exclamation', warning: 'fa-triangle-exclamation', info: 'fa-circle-info' };
        const html = flashes.map(function (f) {
            return '<div class="alert alert-' + f.type + ' anim-slide-down" role="' + (f.type === 'error' ? 'alert' : 'status') + '" data-flash="' + f.type + '">' +
                icon(icons[f.type] + (f.type === 'error' || f.type === 'warning' ? ' fa-beat' : '')) +
                '<span>' + e(f.message) + '</span>' +
                '<button type="button" class="alert-close" data-dismiss-alert aria-label="Dismiss">' + icon('fa-xmark') + '</button></div>';
        }).join('');
        flashes = [];
        return html;
    }
    window.PramanFlashHtml = renderFlash; // landing page shows flashes inside its sign-in popup

    /* ---------------- Paginator (mirrors App\Core\Paginator) ---------------- */
    const PER_PAGE = [10, 25, 50];
    function paginate(rows, ctx, opt) {
        const q = ctx.query;
        const sortable = opt.sortable || {};
        const sort = sortable[q.sort] ? q.sort : opt.sort;
        const dir = q.dir === 'asc' || q.dir === 'desc' ? q.dir : (opt.dir || 'asc');
        const perPage = PER_PAGE.indexOf(+q.per_page) !== -1 ? +q.per_page : 10;
        const search = String(q.q || '').trim();
        const filterKeys = opt.filters || [];

        let list = rows;
        if (search && opt.search) {
            const s = search.toLowerCase();
            list = list.filter(function (r) {
                return opt.search(r).some(function (v) { return String(v == null ? '' : v).toLowerCase().indexOf(s) !== -1; });
            });
        }
        if (sortable[sort]) { list = list.slice().sort(by(sortable[sort], dir)); }

        const total = list.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        const page = Math.min(Math.max(1, parseInt(q.page, 10) || 1), pages);
        const items = list.slice((page - 1) * perPage, page * perPage);

        const p = {
            path: ctx.path, items: items, total: total, page: page, perPage: perPage, sort: sort, dir: dir, search: search,
            filter: function (k, d) { return q[k] !== undefined && q[k] !== '' ? String(q[k]) : (d === undefined ? null : d); },
            hasFilters: function () {
                return search !== '' || filterKeys.some(function (k) { return q[k] !== undefined && q[k] !== ''; });
            },
            totalPages: function () { return pages; },
            from: function () { return total ? (page - 1) * perPage + 1 : 0; },
            to: function () { return Math.min(page * perPage, total); },
            url: function (over) {
                const base = { q: search, sort: sort, dir: dir, per_page: perPage === 10 ? '' : perPage, page: page > 1 ? page : '' };
                filterKeys.forEach(function (k) { base[k] = q[k] || ''; });
                return url(ctx.path, Object.assign(base, over || {}));
            },
            pageUrl: function (n) { return p.url({ page: n > 1 ? n : '' }); },
            sortUrl: function (key) { return p.url({ sort: key, dir: sort === key && dir === 'asc' ? 'desc' : 'asc', page: '' }); },
            sortIcon: function (key) { return sort !== key ? 'fa-sort' : (dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down'); },
            window: function () {
                const out = [];
                for (let i = 1; i <= pages; i++) {
                    if (i === 1 || i === pages || Math.abs(i - page) <= 1) { out.push(i); }
                    else if (out[out.length - 1] !== 0) { out.push(0); }
                }
                return out;
            }
        };
        return p;
    }
    function th_sort(p, key, text, cls) {
        const aria = p.sort === key ? (p.dir === 'asc' ? 'ascending' : 'descending') : 'none';
        return '<th class="sortable' + (cls ? ' ' + e(cls) : '') + '" aria-sort="' + aria + '"><a href="' + e(p.sortUrl(key)) + '">' +
            e(text) + ' ' + icon(p.sortIcon(key)) + '</a></th>';
    }
    function list_toolbar(p, placeholder, filtersHtml) {
        return '<form method="get" data-path="' + e(p.path) + '" class="toolbar" role="search">' +
            '<div class="toolbar-search">' + icon('fa-magnifying-glass') +
            '<input type="search" name="q" value="' + e(p.search) + '" class="form-control" placeholder="' + e(placeholder || 'Search…') + '" aria-label="Search" maxlength="100"></div>' +
            (filtersHtml ? '<div class="toolbar-filters">' + filtersHtml + '</div>' : '') +
            '<input type="hidden" name="sort" value="' + e(p.sort) + '"><input type="hidden" name="dir" value="' + e(p.dir) + '">' +
            '<div class="toolbar-actions"><label class="per-page"><span>Show</span>' +
            '<select name="per_page" class="form-select form-select-sm" data-auto-submit aria-label="Rows per page">' +
            PER_PAGE.map(function (n) { return '<option value="' + n + '"' + (p.perPage === n ? ' selected' : '') + '>' + n + '</option>'; }).join('') +
            '</select></label>' +
            '<button type="submit" class="btn btn-primary btn-sm">' + icon('fa-filter') + ' <span>Apply</span></button>' +
            (p.hasFilters() ? '<a href="' + e(url(p.path)) + '" class="btn btn-light btn-sm">' + icon('fa-rotate-left') + ' <span>Reset</span></a>' : '') +
            '</div></form>';
    }
    function pagination(p) {
        let html = '<div class="pagination-bar"><div class="page-info">' + (p.total > 0
            ? 'Showing <strong>' + fmt_num(p.from()) + '</strong>–<strong>' + fmt_num(p.to()) + '</strong> of <strong>' + fmt_num(p.total) + '</strong> record' + (p.total === 1 ? '' : 's')
            : 'No records') + '</div>';
        if (p.totalPages() > 1) {
            html += '<nav aria-label="Pagination"><ul class="pagination"><li>' +
                (p.page > 1 ? '<a class="page-link" href="' + e(p.pageUrl(p.page - 1)) + '" aria-label="Previous page">' + icon('fa-chevron-left') + '</a>'
                    : '<span class="page-link disabled" aria-hidden="true">' + icon('fa-chevron-left') + '</span>') + '</li>';
            p.window().forEach(function (pg) {
                html += '<li>' + (pg === 0 ? '<span class="page-link ellipsis" aria-hidden="true">…</span>'
                    : pg === p.page ? '<span class="page-link active" aria-current="page">' + pg + '</span>'
                        : '<a class="page-link" href="' + e(p.pageUrl(pg)) + '">' + pg + '</a>') + '</li>';
            });
            html += '<li>' + (p.page < p.totalPages() ? '<a class="page-link" href="' + e(p.pageUrl(p.page + 1)) + '" aria-label="Next page">' + icon('fa-chevron-right') + '</a>'
                : '<span class="page-link disabled" aria-hidden="true">' + icon('fa-chevron-right') + '</span>') + '</li></ul></nav>';
        }
        return html + '</div>';
    }
    function empty_state(colspan, filtered, message) {
        return '<tr class="empty-row"><td colspan="' + colspan + '"><div class="empty-state">' +
            icon(filtered ? 'fa-magnifying-glass' : 'fa-folder-open', 'fa-bounce empty-icon') +
            '<p class="empty-title">' + e(filtered ? 'No matching records found' : (message || 'No records yet')) + '</p>' +
            '<p class="text-muted">' + e(filtered ? 'Try a different search term or reset the filters.' : 'Records you add will appear here.') + '</p></div></td></tr>';
    }

    /* ---------------- Shared partials ---------------- */
    const ACT_ICONS = {
        LOGIN: 'fa-right-to-bracket', LOGOUT: 'fa-right-from-bracket', PASSWORD_CHANGED: 'fa-key', ACCOUNT_LOCKED: 'fa-user-lock',
        ACCESS_DENIED: 'fa-hand', CREATED: 'fa-plus', UPDATED: 'fa-pen', DEACTIVATED: 'fa-toggle-off', ACTIVATED: 'fa-toggle-on'
    };
    function activity(rows, title) {
        let html = '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-clock-rotate-left') + ' ' + e(title || 'Recent activity') + '</h3>' +
            '<span class="text-muted small">Latest ' + rows.length + '</span></div><div class="card-body p-0">';
        if (!rows.length) {
            html += '<div class="empty-state compact">' + icon('fa-inbox', 'empty-icon') + '<p class="empty-title">No activity yet</p></div>';
        } else {
            html += '<ul class="activity-list">' + rows.map(function (a) {
                return '<li><span class="activity-icon">' + icon(ACT_ICONS[a.action] || 'fa-circle-dot') + '</span><div class="activity-body">' +
                    '<strong>' + e(label(a.action)) + '</strong><span class="text-muted">' + e(label(a.entity_type)) + (a.entity_id != null ? ' #' + e(a.entity_id) : '') + '</span>' +
                    '<small class="text-muted">' + e(a.performed_by_name || 'System') + ' &middot; ' + e(fmt_datetime(a.performed_at)) + ' &middot; ' + e(a.ip_address) + '</small></div></li>';
            }).join('') + '</ul>';
        }
        return html + '</div></div>';
    }
    function recentActivity(limit, userId) {
        return DB.where('audit_logs', userId ? { performed_by: userId } : null)
            .sort(function (a, b) { return b.log_id - a.log_id; }).slice(0, limit || 8);
    }
    function status_overview(counts, title) {
        // bar colour = the badge colour of the status in the Status Master (counts are keyed by status_id)
        const colors = {};
        DB.all('statuses').forEach(function (s) { colors[s.status_id] = 'bar-' + s.color; });
        const total = Object.keys(counts).reduce(function (s, k) { return s + counts[k]; }, 0);
        let html = '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-chart-simple') + ' ' + e(title || 'Property status') + '</h3>' +
            '<span class="badge badge-muted">' + fmt_num(total) + ' total</span></div><div class="card-body">';
        if (!total) {
            html += '<div class="empty-state compact">' + icon('fa-building-circle-exclamation', 'empty-icon') + '<p class="empty-title">No properties yet</p>' +
                '<p class="text-muted">Status distribution will appear once properties are registered.</p></div>';
        } else {
            html += '<ul class="bar-list">' + Object.keys(counts).map(function (s) {
                const pct = Math.round(counts[s] * 1000 / total) / 10;
                return '<li><div class="bar-label"><span>' + e(statusName(Number(s))) + '</span><strong>' + fmt_num(counts[s]) + ' <small class="text-muted">(' + pct + '%)</small></strong></div>' +
                    '<div class="bar" role="img" aria-label="' + e(statusName(Number(s)) + ': ' + counts[s]) + '"><span class="bar-fill ' + colors[s] + '" data-pct="' + pct + '"></span></div></li>';
            }).join('') + '</ul>';
        }
        return html + '</div></div>';
    }
    function propertyStatusCounts(createdBy) {
        const out = {};
        Object.keys(Lookups.PROPERTY_STATUSES).forEach(function (s) { out[s] = 0; });
        DB.where('properties', function (p) { return p.is_active === 1 && (createdBy == null || p.created_by === createdBy); })
            .forEach(function (p) { out[p.status_id] = (out[p.status_id] || 0) + 1; }); // keyed by status_id (P.ST)
        return out;
    }
    function pageHeader(title, subtitle, actions) {
        return '<div class="page-header"><div><h2 class="page-title">' + title + '</h2>' +
            (subtitle ? '<p class="page-subtitle">' + subtitle + '</p>' : '') + '</div>' +
            (actions ? '<div class="page-actions">' + actions + '</div>' : '') + '</div>';
    }
    function statCard(color, ic, value, text, sub, href) {
        const rawSub = (sub !== null && sub !== undefined) ? String(sub).replace(/<[^>]+>/g, '').trim() : '';
        const fullTip = text + (rawSub ? ' — ' + rawSub : '');
        const subHtml = rawSub
            ? '<small class="stat-sub" data-tooltip="' + e(rawSub) + '">' + sub + '</small>'
            : '<small class="stat-sub stat-sub-empty" aria-hidden="true">&nbsp;</small>';
        const inner = '<span class="stat-icon">' + icon(ic) + '</span>' +
            '<div class="stat-content">' +
                '<p class="stat-value">' + fmt_num(value) + '</p>' +
                '<p class="stat-label" data-tooltip="' + e(text) + '">' + e(text) + '</p>' +
                subHtml +
            '</div>';
        return href ? '<a class="stat-card stat-' + color + ' stat-link" href="' + e(href) + '" data-tooltip="' + e(fullTip) + '">' + inner + '</a>'
            : '<div class="stat-card stat-' + color + '" data-tooltip="' + e(fullTip) + '">' + inner + '</div>';
    }

    /* ---------------- Layout ---------------- */
    // [label, icon, path, permission] — an item shows only when the panel has that permission
    function userRoleName(u) {
        if (!u) return 'User';
        const r = u.role_id ? DB.find('roles', u.role_id) : null;
        return r ? r.role_name : 'Admin';
    }

    const ALL_MENU_ITEMS = [
        ['Dashboard', 'fa-gauge-high', function () { return Auth.home(); }],
        ['Reports', 'fa-chart-pie', '/admin/reports', 'report.view'],
        { section: 'Schemes & Properties', requiresAny: ['scheme.view', 'property.view', 'verification.view', 'property.create'] },
        ['Schemes', 'fa-map', '/admin/masters/schemes', 'scheme.view'],
        ['Property Register', 'fa-building', '/admin/masters/properties', ['property.view', 'verification.view']],
        { section: 'Masters', requiresAny: ['master.view'] },
        ['Sector', 'fa-table-cells', '/admin/masters/sectors', 'master.view'],
        ['Plot Type', 'fa-shapes', '/admin/masters/plot-types', 'master.view'],
        ['Unit', 'fa-ruler-combined', '/admin/masters/units', 'master.view'],
        ['Status', 'fa-flag', '/admin/masters/statuses', 'master.view'],


        { section: 'Access Control', requiresAny: ['role.view', 'user.view', 'permission.view'] },
        ['Role Master', 'fa-sitemap', '/admin/roles', 'role.view'],
        ['User Master', 'fa-users', '/admin/users', 'user.view'],
        ['Permissions', 'fa-user-shield', '/admin/permissions', 'permission.view'],
        { section: 'System & Monitoring', requiresAny: ['settings.view', 'audit.view'] },
        ['App Settings', 'fa-sliders', '/admin/settings', 'settings.view'],
        ['Activity & Audit', 'fa-chart-line', '/admin/audit-logs', 'audit.view']
    ];

    /** Menu based dynamically on the user's role permissions. */
    function menuFor(user) {
        const out = [];
        ALL_MENU_ITEMS.forEach(function (it) {
            if (it.section) {
                if (it.requiresAny && !it.requiresAny.some(function (p) { return Auth.can(p); })) { return; }
                out.push(it);
                return;
            }
            if (it[3]) { const pms = [].concat(it[3]); if (!Auth.can.apply(null, pms)) { return; } }
            const href = typeof it[2] === 'function' ? it[2]() : it[2];
            out.push([it[0], it[1], href, it[3]]);
        });
        return out.filter(function (it, i) { return !it.section || (out[i + 1] && !out[i + 1].section); });
    }
    function sidebar(user, current) {
        const items = menuFor(user);
        let active = '';
        items.forEach(function (it) {
            if (it[2] && (current === it[2] || current.indexOf(it[2] + '/') === 0) && it[2].length > active.length) { active = it[2]; }
        });
        let nav = items.map(function (it) {
            if (it.section) { return '<div class="nav-section">' + e(it.section) + '</div>'; }
            const on = it[2] === active;
            return '<a class="nav-link' + (on ? ' active' : '') + '" href="' + url(it[2]) + '" title="' + e(it[0]) + '"' + (on ? ' aria-current="page"' : '') + '>' +
                icon(it[1] + ' nav-icon') + '<span>' + e(it[0]) + '</span></a>';
        }).join('');
        nav += '<div class="nav-section">Account</div>' +
            '<a class="nav-link' + (current === '/change-password' ? ' active' : '') + '" href="' + BASE + '/change-password" title="Change Password">' + icon('fa-key nav-icon') + '<span>Change Password</span></a>' +
            '<form method="post" data-action="logout" class="nav-form"><button type="submit" class="nav-link nav-button" title="Logout">' + icon('fa-right-from-bracket nav-icon') + '<span>Logout</span></button></form>';
        return '<aside class="sidebar" id="sidebar" aria-label="Main navigation"><div class="sidebar-brand">' +
            // KDA emblem — the same embedded image as the favicon (<link id="app-icon"> in <head>)
            '<span class="brand-logo"><img src="' + e((document.getElementById('app-icon') || {}).href || '') + '" alt="" width="50" height="50"></span><div class="brand-text"><strong>' + e(APP.name) + '</strong><small>' + e(APP.tagline) + '</small></div>' +
            '<button type="button" class="sidebar-close btn-icon" data-sidebar-close aria-label="Close menu">' + icon('fa-xmark') + '</button></div>' +
            
            '<nav class="sidebar-nav">' + nav + '</nav></aside>';
    }
    function topbar(user, title) {
        const imp = Auth.impersonator();
        const d = new Date();
        return '<header class="topbar"><button type="button" class="btn-icon sidebar-toggle" data-sidebar-toggle aria-label="Toggle menu" title="Show / hide menu" aria-controls="sidebar" aria-expanded="false">' + icon('fa-bars') + '</button>' +
            '<h1 class="topbar-title">' + e(title) + '</h1><div class="topbar-right">' +
            (imp ? '<form method="post" data-action="impersonate-stop" class="back-admin-form"><button type="submit" class="btn-back-admin" title="' + e('Logged in as ' + user.name + ' — return to ' + imp.name) + '">' + icon('fa-arrow-left') + ' <span>Back to Admin</span></button></form>' : '') +
            (Praman.notifBell ? Praman.notifBell(user) : '') +
            '<span class="topbar-date"><i class="fa-regular fa-calendar" aria-hidden="true"></i> ' + p2(d.getDate()) + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + '</span>' +
            '<div class="user-menu" data-dropdown><button type="button" class="user-trigger" data-dropdown-toggle aria-haspopup="true" aria-expanded="false">' +
            '<span class="avatar" aria-hidden="true">' + e(initials(user.name)) + '</span><span class="user-meta"><strong>' + e(user.name) + '</strong><small>' + e(user.mobile || (user.role_id === 1 ? 'Admin' : '')) + '</small></span>' +
            icon('fa-chevron-down user-caret') + '</button><div class="dropdown-menu" role="menu"><div class="dropdown-header"><strong>' + e(user.name) + '</strong>' +
            (user.mobile ? '<small><i class="fa-solid fa-phone" aria-hidden="true"></i> ' + e(user.mobile) + '</small>' : '') + '<small>Last login: ' + e(fmt_datetime(user.last_login_at)) + '</small></div>' +
            '<a class="dropdown-item" role="menuitem" href="' + BASE + '/change-password">' + icon('fa-key') + ' Change Password</a>' +
            '<form method="post" data-action="logout"><button type="submit" class="dropdown-item text-danger" role="menuitem">' + icon('fa-right-from-bracket') + ' Logout</button></form>' +
            '</div></div></div></header>';
    }
    const CONFIRM_MODAL = '<div class="modal-backdrop" id="confirm-modal" hidden><div class="modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-message">' +
        '<div class="modal-header"><span class="modal-icon"><i class="fa-solid fa-circle-question" aria-hidden="true"></i></span><h2 class="modal-title" id="confirm-title">Please confirm</h2></div>' +
        '<div class="modal-body"><p id="confirm-message"></p></div><div class="modal-footer">' +
        '<button type="button" class="btn btn-light" data-confirm-cancel><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>No, cancel</span></button>' +
        '<button type="button" class="btn btn-primary" data-confirm-ok><i class="fa-solid fa-check" aria-hidden="true"></i> <span>Yes, continue</span></button></div></div></div>';

    /* ---------------- Router ---------------- */
    const routes = [];
    /** perm: permission code (or list, any one is enough) the panel needs to open the screen. */
    function route(path, panel, view, perm) {
        const keys = [];
        const re = new RegExp('^' + path.replace(/\{(\w+)\}/g, function (m, k) { keys.push(k); return '(\\d+)'; }) + '$');
        routes.push({ re: re, keys: keys, panel: panel, view: view, perm: perm ? [].concat(perm) : null });
    }
    /** App path + query of the address bar (the app may live in a sub-folder: BASE). */
    function parseUrl() {
        let path = window.location.pathname;
        if (BASE && path.indexOf(BASE) === 0) { path = path.slice(BASE.length); }
        path = path.replace(/\/+$/, '') || '/';
        const query = {};
        window.location.search.slice(1).split('&').forEach(function (pair) {
            if (!pair) { return; }
            const kv = pair.split('=');
            query[decodeURIComponent(kv[0])] = decodeURIComponent((kv[1] || '').replace(/\+/g, ' '));
        });
        return { path: path, query: query };
    }
    Praman.currentPath = function () { return parseUrl().path; };
    function pageKey() { return window.location.pathname + window.location.search; }
    let shownKey = null; // the page on screen: moving only to an #anchor must not redraw it
    /** The address changed: every screen listener (and render) hears 'praman:navigate'. */
    function navigated() { window.dispatchEvent(new Event('praman:navigate')); }
    function pushUrl(target) {
        window.history.pushState(null, '', target);
        window.setTimeout(navigated, 0); // like a hash change: never inside the current render
    }
    function go(path, query) {
        const target = url(path, query);
        if (pageKey() === target) { render(); }
        else { pushUrl(target); }
    }
    function errorPage(status, title, message) {
        const icons = { 403: 'fa-hand', 404: 'fa-map-location-dot', 500: 'fa-triangle-exclamation' };
        return {
            title: title,
            html: '<div class="error-card anim-fade-up">' + icon(icons[status] || 'fa-circle-exclamation', 'error-icon fa-bounce') +
                '<p class="error-code">' + status + '</p><h1 class="error-title">' + e(title) + '</h1><p class="error-message">' + e(message) + '</p>' +
                '<a href="' + url(Auth.home()) + '" class="btn btn-primary">' + icon('fa-house') + ' <span>Go to ' + (Auth.check() ? 'Dashboard' : 'Login') + '</span></a></div>'
        };
    }
    Praman.notFound = function (msg) { return errorPage(404, 'Page not found', msg || 'The page or record you are looking for does not exist.'); };

    function render() {
        const ctx = parseUrl();
        const root = document.getElementById('app-root');
        const user = Auth.user();

        if (ctx.path === '/' || ctx.path === '' || ctx.path === '/login') {
            if (user) { return go(Auth.home()); }
            // guests: landing page; the sign-in form opens in a popup (js/pages/landing.js)
            const landing = Praman.views.landing(ctx);
            document.body.className = 'landing-body';
            document.title = landing.title;
            if (Praman.destroyCharts) { Praman.destroyCharts(); }
            root.innerHTML = landing.html;
            window.scrollTo(0, 0);
            afterRender(root, landing);
            return;
        }
        if (!user) { flash('warning', 'Please login to continue.'); return go('/login'); }

        let page = null;
        let matched = false;
        for (let i = 0; i < routes.length && !page; i++) {
            const r = routes[i];
            const m = ctx.path.match(r.re);
            if (!m) { continue; }
            matched = true;
                        const hasPerm = !r.perm || Auth.can.apply(null, r.perm);
            const isAdmin = Number(user.role_id) === 1;
            if (!isAdmin && !hasPerm) {
                Praman.api('access-denied', { path: ctx.path });
                page = errorPage(403, 'Access denied', 'You do not have permission to open this page.');
                break;
            }
            ctx.params = {};
            r.keys.forEach(function (k, j) { ctx.params[k] = Number(m[j + 1]); });
            ctx.user = user;
            page = r.view(ctx);
            if (page === false) { return; } // view redirected
        }
        if (!matched) { page = Praman.notFound(); }

        document.body.className = 'app-body';
        document.title = (page.title || 'Dashboard') + ' | ' + APP.name;
        const fresh = Auth.user();
        if (Praman.destroyCharts) { Praman.destroyCharts(); }
        const oldNav = root.querySelector('.sidebar-nav');
        const navScroll = oldNav ? oldNav.scrollTop : null;
        root.innerHTML = '<a class="skip-link" href="#main-content">Skip to content</a><div class="app">' + sidebar(fresh, ctx.path) +
            '<div class="sidebar-overlay" data-sidebar-close></div><div class="main">' + topbar(fresh, page.title || '') +
            '<main id="main-content" class="content" tabindex="-1">' + renderFlash() + page.html + '</main>' +
            '<footer class="app-footer"><span>&copy; ' + new Date().getFullYear() + ' ' + e(APP.org) + ' &middot; ' + e(APP.name) + '</span><span class="text-muted">Internal use only</span></footer>' +
            '</div></div>';
        document.body.classList.remove('sidebar-open', 'modal-open');
        restoreNavScroll(root, navScroll);
        afterRender(root, page);
        window.scrollTo(0, 0);
    }
    /** Sidebar is rebuilt on every page: keep its scroll position and make sure the active item is visible. */
    function restoreNavScroll(root, top) {
        const nav = root.querySelector('.sidebar-nav');
        if (!nav) { return; }
        if (top !== null) { nav.scrollTop = top; }
        const active = nav.querySelector('.nav-link.active');
        if (active) {
            const a = active.getBoundingClientRect(), n = nav.getBoundingClientRect();
            if (a.top < n.top + 8 || a.bottom > n.bottom - 8) { nav.scrollTop += a.top - n.top - n.height / 2 + a.height / 2; }
        }
        const sync = function () {
            nav.classList.toggle('can-up', nav.scrollTop > 4);
            nav.classList.toggle('can-down', nav.scrollTop + nav.clientHeight < nav.scrollHeight - 4);
        };
        nav.addEventListener('scroll', sync, { passive: true });
        sync();
    }
    /** Sizes / colours drawn from data (the CSP allows no inline style attributes). */
    function applyDataStyles(root) {
        root.querySelectorAll('[data-css-h],[data-css-w],[data-css-bg],[data-css-img]').forEach(function (el) {
            if (el.hasAttribute('data-css-h')) { el.style.height = parseFloat(el.getAttribute('data-css-h')) + 'px'; }
            if (el.hasAttribute('data-css-w')) { el.style.width = parseFloat(el.getAttribute('data-css-w')) + '%'; }
            if (el.hasAttribute('data-css-bg')) { el.style.background = el.getAttribute('data-css-bg'); }
            // same-origin image URLs only (landing slides)
            const img = el.getAttribute('data-css-img');
            if (img && img.indexOf(Praman.BASE + '/assets/') === 0 && !/["'()\\\s]/.test(img)) { el.style.backgroundImage = 'url("' + img + '")'; }
        });
    }
    Praman.applyDataStyles = applyDataStyles;
    function afterRender(root, page) {
        applyDataStyles(root);
        if (Praman.loader) { window.requestAnimationFrame(function () { Praman.loader.done(); }); } // first paint is ready → open the gates
        if (Praman.initPage) { Praman.initPage(root); }
        if (Praman.initForms) { Praman.initForms(root); }
        if (page && page.after) { page.after(root); }
        shownKey = pageKey();
        const hashAnchor = window.location.hash.slice(1);
        if (hashAnchor) {
            const el = document.getElementById(hashAnchor);
            if (el) { window.setTimeout(function () { el.scrollIntoView({ behavior: 'smooth' }); }, 100); } // after the page has settled
        }
    }

    /* ---------------- POST actions ---------------- */
    const actions = {};
    function formData(form, submitter) {
        const out = {};
        new FormData(form).forEach(function (v, k) {
            if (k.slice(-2) === '[]') { (out[k] = out[k] || []).push(v); }
            else { out[k] = v; }
        });
        if (submitter && submitter.name) { out[submitter.name] = submitter.value; }
        return out;
    }
    /** Server-side style error: mark the field, toast, and unlock the form (inputs are kept). */
    function fail(form, field, message) {
        const el = field ? form.querySelector('[name="' + CSS.escape(field) + '"]') : null;
        if (el && Praman.Validation) {
            Praman.Validation.setError(el, message);
            el.focus();
        }
        Praman.toast('error', message);
        unlock(form);
        return false;
    }
    function unlock(form) {
        form.dataset.submitting = '';
        form.dataset.confirmed = '';
        form.querySelectorAll('.is-loading').forEach(function (b) { Praman.setLoading(b, false); });
        document.querySelectorAll('button.is-loading').forEach(function (b) { Praman.setLoading(b, false); });
    }
    /** Go to a server-given path such as "/role/tasks?status=pending". */
    function goTo(target) {
        const i = target.indexOf('?');
        const query = {};
        if (i !== -1) {
            target.slice(i + 1).split('&').forEach(function (kv) {
                if (!kv) { return; }
                const x = kv.split('=');
                query[decodeURIComponent(x[0])] = decodeURIComponent((x[1] || '').replace(/\+/g, ' '));
            });
        }
        go(i === -1 ? target : target.slice(0, i), query);
    }
    Praman.goTo = goTo;
    /**
     * Submit a form to the server action named by data-action (POST /api/<action>).
     * Local actions (Praman.actions[name]) run instead when a screen defines one.
     */
    Praman.dispatch = function (form, submitter) {
        const name = form.getAttribute('data-action');
        if (!/^[a-z-]+$/.test(name || '')) { unlock(form); Praman.toast('error', 'Unknown action.'); return; }
        if (actions[name]) {
            try {
                const r = actions[name](formData(form, submitter), form, submitter);
                if (r && typeof r.then === 'function') { r.catch(function (err) { console.error(err); fail(form, null, err.message || 'Unexpected error.'); }); }
            } catch (err) { console.error(err); fail(form, null, 'Unexpected error: ' + err.message); }
            return;
        }
        const fd = new FormData(form);
        if (submitter && submitter.name) { fd.append(submitter.name, submitter.value); }
        // files of the upload widgets (files.js): each widget is sent as <name>[]
        const uploads = form._uploads || { attachments: form._files || [] };
        Object.keys(uploads).forEach(function (n) { uploads[n].forEach(function (f) { fd.append(n + '[]', f, f.name); }); });
        Praman.api(name, fd).then(function (res) {
            if (res.status === 401) { unlock(form); flash('warning', res.message || 'Please login to continue.'); return go('/login'); }
            if (!res.ok) { return fail(form, res.field, res.message || 'The request could not be completed.'); }
            (res.flashes || []).forEach(function (f) { flash(f.type, f.message); });
            // switching user: play the loader while the next panel is drawn
            // (not on login: the dashboard opens straight away)
            const SWITCH = { logout: 'Signing you out securely…', impersonate: 'Opening the user panel…',
                'impersonate-stop': 'Returning to Super Admin…' };
            if (SWITCH[name] && Praman.loader) { Praman.loader.show(SWITCH[name]); }
            if (res.go) { goTo(res.go); } else { render(); }
        });
    };
    Praman.navigateGet = function (form) {
        const q = {};
        new FormData(form).forEach(function (v, k) { if (String(v).trim() !== '') { q[k] = String(v).trim().slice(0, 100); } });
        // date-range filters: valid dates, "from" not after "to"
        const bad = ['from', 'to'].filter(function (k) { return q[k] && !/^\d{4}-\d{2}-\d{2}$/.test(q[k]); });
        if (bad.length) { Praman.toast('error', 'Enter a valid date.'); return; }
        if (q.from && q.to && q.from > q.to) {
            const el = form.querySelector('[name="from"]');
            if (el && Praman.Validation) { Praman.Validation.setError(el, '"From" date cannot be after the "To" date.'); el.focus(); }
            Praman.toast('error', '"From" date cannot be after the "To" date.');
            return;
        }
        if (q.dir && !q.sort) { delete q.dir; }
        go(form.getAttribute('data-path'), q);
    };

    window.addEventListener('praman:navigate', render);
    window.addEventListener('popstate', function () { if (pageKey() !== shownKey) { navigated(); } });
    /* Links inside the app change the page without a reload (the server serves the same app for these paths).
       On window, so handlers that stop a link (confirm, sign-in popup) run first. */
    window.addEventListener('click', function (ev) {
        if (ev.defaultPrevented || ev.button !== 0 || ev.ctrlKey || ev.metaKey || ev.shiftKey || ev.altKey) { return; }
        const a = ev.target.closest ? ev.target.closest('a[href]') : null;
        if (!a || a.target || a.hasAttribute('download')) { return; }
        const to = new URL(a.href);
        const inApp = to.origin === window.location.origin && (to.pathname === BASE + '/' || to.pathname.indexOf(BASE + '/') === 0) &&
            to.pathname.indexOf(BASE + '/assets/') !== 0 && to.pathname.indexOf(BASE + '/api/') !== 0;
        if (!inApp) { return; }
        if (to.pathname + to.search === pageKey()) { return; } // same page: the browser only jumps to the #anchor
        ev.preventDefault();
        pushUrl(to.pathname + to.search + to.hash);
    });
    document.addEventListener('DOMContentLoaded', function () {
        // old bookmarks: /#/admin/dashboard -> /admin/dashboard
        if (window.location.hash.indexOf('#/') === 0) { window.history.replaceState(null, '', BASE + window.location.hash.slice(1)); }
        document.getElementById('modal-root').innerHTML = CONFIRM_MODAL;
        (DB.initialFlashes || []).forEach(function (f) { flash(f.type, f.message); });
        render();
    });

    /* Every minute: fetch the latest state so new notifications / other users' changes show up.
       The page is not re-drawn (forms keep what the user typed); the bell refreshes itself. */
    function poll() {
        if (document.hidden || !Auth.check() || document.querySelector('form[data-submitting="1"]')) { return; }
        const was = Auth.id();
        Praman.refreshState().then(function () {
            if (was && !Auth.id()) { flash('warning', 'Your session expired. Please login again.'); go('/login'); return; }
            if (Praman.onStateRefresh) { Praman.onStateRefresh(); }
        });
    }
    (function schedule() {
        window.setTimeout(function () { poll(); schedule(); }, Math.max(30, Number(S('poll_seconds', 60))) * 1000);
    })();

    /* ---------------- Black ngbTooltip System (Angular style) ---------------- */
    (function initNgbTooltip() {
        let tipEl = null;
        let arrowEl = null;
        let innerEl = null;
        let activeTarget = null;
        let hideTimer = null;

        function ensureTip() {
            if (tipEl) { return; }
            tipEl = document.createElement('div');
            tipEl.className = 'ngb-tooltip';
            tipEl.setAttribute('role', 'tooltip');
            tipEl.innerHTML = '<div class="tooltip-arrow"></div><div class="tooltip-inner"></div>';
            arrowEl = tipEl.querySelector('.tooltip-arrow');
            innerEl = tipEl.querySelector('.tooltip-inner');
            document.body.appendChild(tipEl);
        }

        function showTip(target, text) {
            if (!text || !text.trim()) { hideTip(); return; }
            ensureTip();
            clearTimeout(hideTimer);
            activeTarget = target;
            innerEl.textContent = text.trim();
            tipEl.className = 'ngb-tooltip';
            tipEl.style.display = 'block';
            tipEl.style.visibility = 'hidden';
            tipEl.style.left = '0px';
            tipEl.style.top = '0px';

            const rect = target.getBoundingClientRect();
            const tipW = tipEl.offsetWidth;
            const tipH = tipEl.offsetHeight;

            // Center horizontally on target, keep 8px within viewport
            let left = rect.left + (rect.width / 2) - (tipW / 2);
            left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));

            // Default placement: bottom (niche aayegi)
            let top = rect.bottom + 8;
            let placement = 'bottom';
            // Agar bottom me screen overflow ho to hi top par flip kare
            if (top + tipH > window.innerHeight - 8 && rect.top - tipH - 8 > 8) {
                top = rect.top - tipH - 8;
                placement = 'top';
            }

            tipEl.className = 'ngb-tooltip ngb-tooltip-' + placement;
            tipEl.style.left = Math.round(left) + 'px';
            tipEl.style.top = Math.round(top) + 'px';
            tipEl.style.visibility = 'visible';

            // Point arrow at center of target
            const targetCenter = rect.left + (rect.width / 2);
            let arrowLeft = targetCenter - left;
            arrowLeft = Math.max(10, Math.min(arrowLeft, tipW - 10));
            arrowEl.style.left = Math.round(arrowLeft) + 'px';

            requestAnimationFrame(function () {
                if (activeTarget === target) {
                    tipEl.classList.add('show');
                }
            });
        }

        function hideTip() {
            activeTarget = null;
            if (!tipEl) { return; }
            tipEl.classList.remove('show');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(function () {
                if (!activeTarget && tipEl) {
                    tipEl.style.display = 'none';
                }
            }, 160);
        }

        // Global mouseover listener: supports both data-tooltip and title
        document.addEventListener('mouseover', function (e) {
            const el = e.target.closest('[data-tooltip], [title]');
            if (!el) { return; }
            let text = el.getAttribute('data-tooltip');
            if (!text && el.hasAttribute('title')) {
                text = el.getAttribute('title');
                el.setAttribute('data-tooltip', text);
                el.removeAttribute('title'); // prevent browser's native popup
            }
            if (text && text.trim()) {
                showTip(el, text);
            }
        }, { passive: true });

        document.addEventListener('mouseout', function (e) {
            if (!activeTarget) { return; }
            const related = e.relatedTarget;
            if (related && (activeTarget === related || activeTarget.contains(related))) {
                return;
            }
            hideTip();
        }, { passive: true });

        window.addEventListener('scroll', hideTip, { passive: true });
        window.addEventListener('wheel', hideTip, { passive: true });
        window.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { hideTip(); }
        });
        document.addEventListener('pointerdown', hideTip, { passive: true });
    })();

    Object.assign(Praman, {
        APP: APP, PANELS: PANELS, HOME: HOME, SECURITY: SECURITY, Lookups: Lookups, DB: DB, Auth: Auth,
        e: e, url: url, label: label, fmt_date: fmt_date, dueText: dueText, fmt_datetime: fmt_datetime, fmt_num: fmt_num,
        status_badge: status_badge, active_badge: active_badge, icon: icon, by: by, ST: ST, statusName: statusName,
        flash: flash, go: go, render: render, route: route, actions: actions, fail: fail, unlock: unlock,
        paginate: paginate, th_sort: th_sort, list_toolbar: list_toolbar, pagination: pagination, empty_state: empty_state,
        activity: activity, recentActivity: recentActivity, status_overview: status_overview, propertyStatusCounts: propertyStatusCounts,
        pageHeader: pageHeader, statCard: statCard, views: Praman.views || {}
    });
})(window, document);
