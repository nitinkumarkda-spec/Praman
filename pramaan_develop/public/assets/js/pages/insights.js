/* ===== js/pages/insights.js ===== */
/* PRAMAAN — Super Admin: Activity & Performance Insights (full audit trail + analytics) */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;

    /* ---------- activity areas (modules) ---------- */
    const AREAS = {
        AUTH:         ['Auth', 'fa-right-to-bracket'],
        VERIFICATION: ['Verification', 'fa-list-check'],
        PROPERTY:     ['Property', 'fa-building'],
        SCHEME:       ['Scheme', 'fa-map'],
        MASTERS:      ['Masters', 'fa-table-cells-large'],
        SYSTEM:       ['System', 'fa-gear']
    };
    function areaOf(a) {
        switch (a.entity_type) {
            case 'AUTH': return 'AUTH';
            case 'ROUND': case 'TASK': return 'VERIFICATION';
            case 'PROPERTY': return 'PROPERTY';
            case 'SCHEME': return 'SCHEME';
            case 'PLOT_TYPE': case 'STATUS': case 'SECTOR': return 'MASTERS';
            case 'USER': case 'ROLE': return ['IMPERSONATION_STARTED', 'IMPERSONATION_ENDED', 'ACCOUNT_LOCKED', 'PASSWORD_CHANGED'].indexOf(a.action) !== -1 ? 'AUTH' : 'MASTERS';
            default: return 'SYSTEM';
        }
    }
    const QUICK = [
        ['today', 'Today', 'fa-calendar-day'], ['yesterday', 'Yesterday', 'fa-clock-rotate-left'], ['7d', 'Last 7 Days', 'fa-calendar-week'],
        ['all', 'All Logs', 'fa-list'], ['area:VERIFICATION', 'Verification updates', 'fa-list-check'],
        ['area:PROPERTY', 'Property updates', 'fa-building'], ['area:SCHEME', 'Scheme updates', 'fa-map']
    ];
    const FOCUS = [
        ['PROPERTY', 'Property register updates', 'Data Entry should keep registering vacant properties and correcting plot details.'],
        ['VERIFICATION', 'Verification updates', 'OIC should send Listed properties and sections should respond to pending tasks within SLA.'],
        ['SCHEME', 'Scheme master updates', 'Scheme sectors and role-user mapping should be reviewed so new properties get the right verifiers.']
    ];
    const EXPECTED_MIN = 20; // % share of activity each focus area should have

    /* ---------- helpers ---------- */
    const users = function () {
        const m = {};
        DB.all('users').forEach(function (u) { m[u.user_id] = u; });
        return m;
    };
    function dateOnly(v) { return String(v || '').slice(0, 10); }
    function timeOnly(v) {
        const s = P.fmt_datetime(v);
        return s === '—' ? '—' : s.slice(11);
    }
    function dayLabel(v) {
        const d = new Date(dateOnly(v) + 'T00:00:00');
        return isNaN(d) ? '—' : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function fmtVal(k, v) {
        if (v == null || v === '') { return 'Not set'; }
        if (Array.isArray(v)) { return v.join(', '); }
        if (typeof v === 'object') { return JSON.stringify(v); }
        if (k === 'is_active' || k === 'is_mandatory') { return Number(v) === 1 ? 'Yes' : 'No'; }
        if (k === 'role_id') { const r = DB.find('roles', v); return r ? r.role_name : String(v); }
        if (k === 'zone_id') { return DB.zoneName(v); }
        if (k === 'ward_id') { return DB.wardName(v); }
        if (k === 'assembly_id') { return DB.assemblyName(v); }
        if (k === 'panel') { return P.PANELS[v] || v; }
        if (/status|result|type$/.test(k) && /^[A-Z_]+$/.test(String(v))) { return P.label(v); }
        return String(v);
    }
    function propertyLabel(id) {
        const p = id ? DB.find('properties', id) : null;
        if (!p) { return null; }
        const sc = DB.find('schemes', p.scheme_id);
        const se = DB.find('sectors', p.sector_id);
        return 'Plot ' + p.plot_no + ' (' + p.property_code + ') · ' + sc.scheme_name + ' / ' + se.sector_name;
    }
    function subjectOf(a) {
        if (a.property_id) { return propertyLabel(a.property_id); }
        const t = a.entity_type;
        const row = t === 'SCHEME' ? DB.find('schemes', a.entity_id) : t === 'ROLE' ? DB.find('roles', a.entity_id) : t === 'USER' ? DB.find('users', a.entity_id) : null;
        if (!row) { return null; }
        if (t === 'SCHEME') { return row.scheme_name + ' (' + row.scheme_code + ')'; }
        if (t === 'ROLE') { return row.role_name + ' (' + row.role_code + ')'; }
        return row.name + ' (' + row.username + ')';
    }
    function titleOf(a) {
        const crud = { CREATED: 'Created', UPDATED: 'Updated', ACTIVATED: 'Activated', DEACTIVATED: 'Deactivated' };
        if (crud[a.action]) { return crud[a.action] + ' ' + P.label(a.entity_type); }
        const map = {
            ROUND_INITIATED: 'Sent for Verification', REINITIATED: 'Re-sent for Verification', ROUND_CLOSED: 'Verification Round Closed',
            TASK_APPROVED: 'Section Approved', TASK_REJECTED: 'Section Rejected', DATABASE_SEEDED: 'Database Seeded',
            IMPERSONATION_STARTED: 'Login as User', IMPERSONATION_ENDED: 'Back to Super Admin', DIRECTION_PUBLISHED: 'Direction Published'
        };
        return map[a.action] || P.label(a.action);
    }
    /** Plain-language sentence for the details box. */
    function describe(a) {
        const n = a.new_value || {};
        const o = a.old_value || {};
        switch (a.action) {
            case 'LOGIN': return n.message || 'Login successful';
            case 'LOGOUT': return n.message || 'User logged out';
            case 'ACCOUNT_LOCKED': return 'Account locked for ' + (n.minutes || 15) + ' minutes after repeated failed logins';
            case 'PASSWORD_CHANGED': return 'User changed own password';
            case 'PASSWORD_RESET': return 'Password reset by Super Admin (account unlocked)';
            case 'UNLOCKED': return 'Account unlocked by Super Admin';
            case 'IMPERSONATION_STARTED': return 'Super Admin opened the panel of ' + (n.target || 'a user');
            case 'IMPERSONATION_ENDED': return 'Super Admin returned to own panel';
            case 'ACCESS_DENIED': return 'Blocked access to ' + (n.path || 'a restricted page');
            case 'DATABASE_SEEDED': return 'Initial data loaded: ' + (n.roles || 0) + ' roles, ' + (n.users || 0) + ' users';
            case 'ROUND_INITIATED':
            case 'REINITIATED':
                return 'Round R' + n.round_no + ' sent to ' + (n.roles || []).length + ' section(s): ' + (n.roles || []).join(', ') +
                    (n.reason ? ' — Reason: ' + n.reason : '') + (o.status ? ' (previous status: ' + P.label(o.status) + ')' : '');
            case 'ROUND_CLOSED': return 'Round closed — result: ' + P.label(n.result) + (n.result === 'READY' ? ' (property is Auction Ready)' : ' (property is Not Ready)');
            case 'TASK_APPROVED':
            case 'TASK_REJECTED':
            case 'TASK_SIMULATED_APPROVED':
            case 'TASK_SIMULATED_REJECTED':
                return n.role + ' ' + (a.action.indexOf('APPROVED') !== -1 ? 'approved' : 'rejected') + ' round R' + n.round_no + (n.remarks ? ' — "' + n.remarks + '"' : '');
            case 'DIRECTION_PUBLISHED': return 'Direction published for: ' + (n.areas || []).join(', ');
            default: return null;
        }
    }
    function changeRows(a) {
        const o = a.old_value || {};
        const n = a.new_value || {};
        const keys = [];
        Object.keys(o).concat(Object.keys(n)).forEach(function (k) {
            if (k.charAt(0) !== '_' && k !== 'message' && keys.indexOf(k) === -1) { keys.push(k); }
        });
        return keys.filter(function (k) { return a.old_value ? JSON.stringify(o[k]) !== JSON.stringify(n[k]) : true; })
            .map(function (k) { return [P.label(k), fmtVal(k, o[k]), fmtVal(k, n[k])]; });
    }

    /* ---------- filtering ---------- */
    function rangeFor(key) {
        const t = DB.today();
        if (key === 'today') { return [t, t]; }
        if (key === 'yesterday') { const y = DB.today(-1); return [y, y]; }
        if (key === '7d') { return [DB.today(-6), t]; }
        return ['', ''];
    }
    function applyFilters(rows, q, um) {
        return rows.filter(function (a) {
            if (q.from && dateOnly(a.performed_at) < q.from) { return false; }
            if (q.to && dateOnly(a.performed_at) > q.to) { return false; }
            if (q.user && String(a.performed_by) !== q.user) { return false; }
            if (q.area && areaOf(a) !== q.area) { return false; }
            if (q.skip_auth === '1' && areaOf(a) === 'AUTH') { return false; }
            if (q.role) {
                const u = um[a.performed_by];
                if (!u) { return false; }
                if (q.role.indexOf('P:') === 0 && u.panel !== q.role.slice(2)) { return false; }
                if (q.role.indexOf('R:') === 0 && String(u.role_id) !== q.role.slice(2)) { return false; }
            }
            return true;
        });
    }

    /* ---------- page ---------- */
    P.route('/admin/audit-logs', 'SUPER_ADMIN', function (ctx) {
        const q = ctx.query;
        const um = users();
        const all = DB.all('audit_logs').sort(function (x, y) { return y.log_id - x.log_id; });
        const filtered = applyFilters(all, q, um);
        const p = P.paginate(filtered, ctx, {
            sort: 'id', dir: 'desc', filters: ['user', 'role', 'area', 'from', 'to', 'skip_auth', 'quick'],
            search: function (a) {
                return [a.action, titleOf(a), a.performed_by_name, a.performed_by_designation, a.ip_address, a.entity_type,
                    describe(a), subjectOf(a), JSON.stringify(a.old_value), JSON.stringify(a.new_value)];
            },
            sortable: { id: function (a) { return a.log_id; } }
        });
        // analytics use the searched + filtered set
        const s = (q.q || '').toLowerCase();
        const scope = s ? filtered.filter(function (a) {
            return [a.action, titleOf(a), a.performed_by_name, a.ip_address, describe(a), subjectOf(a), JSON.stringify(a.new_value)]
                .some(function (v) { return String(v || '').toLowerCase().indexOf(s) !== -1; });
        }) : filtered;

        const t = DB.today();
        const activeUsers = new Set(scope.map(function (a) { return a.performed_by; }).filter(Boolean)).size;
        const modules = new Set(scope.map(areaOf)).size;
        const last = scope[0];

        const stats = '<div class="stat-grid dash-stats ins-stats">' +
            P.statCard('navy', 'fa-database', scope.length, 'Filtered logs', 'of ' + P.fmt_num(all.length) + ' total') +
            P.statCard('green', 'fa-calendar-day', all.filter(function (a) { return dateOnly(a.performed_at) === t; }).length, 'Today', 'logged today') +
            P.statCard('blue', 'fa-user-check', activeUsers, 'Active users', 'in time range') +
            P.statCard('gold', 'fa-layer-group', modules, 'Modules', 'system areas') +
            '<div class="stat-card stat-red" title="' + (last ? e('Last activity: ' + dayLabel(last.performed_at) + ', ' + timeOnly(last.performed_at)) : 'No activity') + '"><span class="stat-icon">' + icon('fa-clock') + '</span><div class="stat-content"><p class="stat-value ins-stat-time">' +
            (last ? e(dayLabel(last.performed_at).slice(0, 6) + ', ' + timeOnly(last.performed_at)) : '—') + '</p><p class="stat-label" title="Last activity">Last activity</p><small class="stat-sub" title="most recent entry">most recent entry</small></div></div></div>';

        // ---- quick chips + filter form ----
        const quick = q.quick || (q.from || q.to || q.area ? '' : 'all');
        const chips = '<div class="ins-chips">' + QUICK.map(function (c) {
            const isArea = c[0].indexOf('area:') === 0;
            const r = rangeFor(c[0]);
            const href = isArea ? P.url(ctx.path, { area: c[0].slice(5), quick: c[0] }) : P.url(ctx.path, { from: r[0], to: r[1], quick: c[0] });
            return '<a class="ins-chip' + (quick === c[0] ? ' active' : '') + '" href="' + e(href) + '">' + icon(c[2]) + ' ' + e(c[1]) + '</a>';
        }).join('') + '</div>';
        const userOpts = {};
        Object.keys(um).map(function (k) { return um[k]; }).sort(P.by(function (u) { return u.name; }))
            .forEach(function (u) { userOpts[u.user_id] = u.name + ' (' + P.PANELS[u.panel] + ')'; });
        const roleOpts = {};
        Object.keys(P.PANELS).forEach(function (k) { if (k !== 'ROLE_USER') { roleOpts['P:' + k] = P.PANELS[k] + ' panel'; } });
        DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).forEach(function (r) { roleOpts['R:' + r.role_id] = r.role_name + ' (Role User)'; });
        const areaOpts = {};
        Object.keys(AREAS).forEach(function (k) { areaOpts[k] = AREAS[k][0]; });
        const field = function (lbl, html) { return '<div class="ins-field"><label class="form-label">' + lbl + '</label>' + html + '</div>'; };
        const filterCard = '<div class="card ins-filter-card"><div class="card-body">' + chips +
            '<form method="get" data-path="' + e(ctx.path) + '" class="ins-filters" role="search">' +
            field('Keyword', '<input type="search" name="q" class="form-control" value="' + e(q.q || '') + '" placeholder="Action, user, details, IP…" maxlength="100">') +
            field('User', '<select name="user" class="form-select">' + L.options(userOpts, q.user, 'All Users') + '</select>') +
            field('Role', '<select name="role" class="form-select">' + L.options(roleOpts, q.role, 'All Roles') + '</select>') +
            field('Activity Area', '<select name="area" class="form-select">' + L.options(areaOpts, q.area, 'All Areas') + '</select>') +
            field('Date Range', '<div class="ins-range"><input type="date" name="from" class="form-control" value="' + e(q.from || '') + '" aria-label="From date">' +
                '<span>to</span><input type="date" name="to" class="form-control" value="' + e(q.to || '') + '" aria-label="To date"></div>') +
            field('Skip', '<label class="check ins-skip"><input type="checkbox" name="skip_auth" value="1"' + (q.skip_auth === '1' ? ' checked' : '') + '><span>Auth</span></label>') +
            '<input type="hidden" name="per_page" value="' + p.perPage + '">' +
            '<div class="ins-actions"><button type="submit" class="btn btn-primary">' + icon('fa-filter') + ' <span>Apply</span></button>' +
            (p.hasFilters() ? '<a href="' + P.url(ctx.path) + '" class="btn btn-light btn-icon-only" title="Clear filters" aria-label="Clear filters">' + icon('fa-xmark') + '</a>' : '') +
            '</div></form></div></div>';
        const pills = '<div class="ins-pills"><span class="ins-pill">' + icon('fa-circle-info') + ' ' + P.fmt_num(scope.length) + ' of ' + P.fmt_num(all.length) + ' visible logs</span>' +
            '<span class="ins-pill">' + icon('fa-calendar') + ' ' + (q.from || q.to ? e((q.from || 'start') + ' to ' + (q.to || 'today')) : 'All dates') + '</span>' +
            (q.area ? '<span class="ins-pill">' + icon(AREAS[q.area][1]) + ' ' + e(AREAS[q.area][0]) + '</span>' : '') +
            (q.skip_auth === '1' ? '<span class="ins-pill">' + icon('fa-eye-slash') + ' Auth hidden</span>' : '') + '</div>';

        // ---- timeline ----
        const timeline = '<div class="card ins-timeline"><div class="card-header"><h3 class="card-title">' + icon('fa-timeline') + ' Activity Timeline</h3>' +
            '<div class="ins-tl-head"><span class="text-muted small">' + P.fmt_num(p.total) + ' record' + (p.total === 1 ? '' : 's') + '</span>' +
            '<label class="per-page"><span>Show</span><select class="form-select form-select-sm" data-ins-perpage aria-label="Rows per page">' +
            [10, 25, 50].map(function (n) { return '<option value="' + n + '"' + (p.perPage === n ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select></label></div></div>' +
            '<div class="card-body ins-tl-body">' + (p.items.length ? p.items.map(function (a) { return timelineItem(a, um); }).join('')
                : '<div class="empty-state">' + icon(p.hasFilters() ? 'fa-magnifying-glass' : 'fa-inbox', 'fa-bounce empty-icon') +
                '<p class="empty-title">' + (p.hasFilters() ? 'No matching activity found' : 'No activity yet') + '</p><p class="text-muted">Try a different date range or reset the filters.</p></div>') +
            '</div>' + P.pagination(p) + '</div>';

        const side = topPerformers(scope, um) + sectionPerformance(scope) + operational(scope) + moduleActivity(scope) + mostActive(scope, um) + quickInsight(scope);

        return {
            title: 'Activity & Performance Insights',
            html: '<nav class="ins-crumbs" aria-label="Breadcrumb"><a href="#/admin/dashboard">Home</a><span>/</span><span>Administration</span><span>/</span><strong>Activity &amp; Performance Insights</strong></nav>' +
                P.pageHeader(icon('fa-chart-line') + ' Activity &amp; Performance Insights', 'Monitor user activity, section performance, operational trends and audit history in one place.') +
                stats + filterCard + pills + '<div class="ins-layout"><div class="ins-main">' + timeline + '</div><aside class="ins-side">' + side + '</aside></div>',
            after: function (root) {
                const pp = root.querySelector('[data-ins-perpage]');
                if (pp) {
                    pp.addEventListener('change', function () {
                        const f = root.querySelector('form.ins-filters');
                        f.querySelector('[name=per_page]').value = pp.value;
                        f.requestSubmit();
                    });
                }
                root.querySelectorAll('[data-ins-modal-open]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const tpl = btn.closest('.card').querySelector('template[data-ins-modal-body]');
                        openInsModal(tpl, btn);
                    });
                });
                root.querySelectorAll('[data-ins-more]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const box = btn.closest('.card');
                        const open = box.classList.toggle('ins-show-all');
                        btn.querySelector('span').textContent = open ? 'Show less' : 'View All';
                    });
                });
            }
        };
    });

    /** "View All" popup: full table from a side card's <template data-ins-modal-body data-title data-sub data-icon>. */
    function openInsModal(tpl, opener) {
        const old = document.getElementById('ins-modal');
        if (old) { old.remove(); }
        const m = document.createElement('div');
        m.id = 'ins-modal';
        m.className = 'modal-backdrop ins-modal';
        m.innerHTML = '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="ins-modal-title"><div class="modal-header">' +
            '<span class="modal-icon">' + icon(tpl.getAttribute('data-icon')) + '</span><div><h2 class="modal-title" id="ins-modal-title">' + e(tpl.getAttribute('data-title')) + '</h2>' +
            '<p>' + e(tpl.getAttribute('data-sub')) + '</p></div>' +
            '<button type="button" class="btn btn-light btn-sm btn-icon-only ml-auto" data-ins-modal-close aria-label="Close">' + icon('fa-xmark') + '</button></div>' +
            '<div class="modal-body">' + tpl.innerHTML + '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-light" data-ins-modal-close>' + icon('fa-xmark') + ' <span>Close</span></button></div></div>';
        document.body.appendChild(m);
        document.body.classList.add('modal-open');
        void m.offsetWidth; // commit the closed state so the open transition runs
        m.classList.add('is-open');
        m.querySelectorAll('[data-pct]').forEach(function (b) { b.style.width = b.getAttribute('data-pct') + '%'; });
        m.querySelector('[data-ins-modal-close]').focus();
        function close() {
            m.classList.remove('is-open');
            document.body.classList.remove('modal-open');
            document.removeEventListener('keydown', onKey);
            window.setTimeout(function () { m.remove(); }, 180);
            if (opener && opener.focus) { opener.focus(); }
        }
        function onKey(ev) { if (ev.key === 'Escape') { close(); } }
        m.addEventListener('click', function (ev) { if (ev.target === m || ev.target.closest('[data-ins-modal-close]')) { close(); } });
        document.addEventListener('keydown', onKey);
    }

    function timelineItem(a, um) {
        const u = um[a.performed_by];
        const area = AREAS[areaOf(a)];
        const desc = describe(a);
        const rows = changeRows(a);
        const subject = subjectOf(a);
        const isChange = ['UPDATED', 'ACTIVATED', 'DEACTIVATED'].indexOf(a.action) !== -1 && a.old_value;
        const isCreate = a.action === 'CREATED' && rows.length;
        let body = '';
        if (isChange || isCreate) {
            body = '<table class="ins-change"><thead><tr><th>Field</th>' + (isChange ? '<th>Previous</th><th>Changed to</th>' : '<th>Value</th>') + '</tr></thead><tbody>' +
                rows.map(function (r) {
                    return '<tr><td><strong>' + e(r[0]) + '</strong></td>' + (isChange ? '<td class="ins-old">' + e(r[1]) + '</td>' : '') + '<td class="ins-new">' + e(r[2]) + '</td></tr>';
                }).join('') + '</tbody></table>';
        } else {
            body = '<p class="ins-desc">' + e(desc || 'No additional details') + '</p>';
        }
        const tone = /REJECT|DEACTIVATED|LOCKED|DENIED/.test(a.action) ? ' tone-danger' : /APPROVED|ACTIVATED|READY/.test(a.action) || (a.action === 'ROUND_CLOSED' && a.new_value && a.new_value.result === 'READY') ? ' tone-success' : '';
        return '<article class="ins-item' + tone + '"><div class="ins-item-main"><h4 class="ins-item-title">' + e(titleOf(a)) +
            (a.entity_id != null && a.entity_type !== 'AUTH' ? ' <span class="ins-id">#' + a.entity_id + '</span>' : '') + '</h4>' +
            '<div class="ins-meta"><span>' + icon('fa-user') + ' ' + e(a.performed_by_name || 'System') + (a.performed_by_designation ? ' (' + e(a.performed_by_designation) + ')' : '') + '</span>' +
            (u ? '<span>' + icon('fa-shield-halved') + ' ' + e(P.PANELS[u.panel]) + (u.role_id ? ' · ' + e(DB.find('roles', u.role_id).role_name) : '') + '</span>' : '') +
            '<span>' + icon(area[1]) + ' ' + e(area[0]) + '</span></div>' +
            '<div class="ins-details"><p class="ins-details-title">' + e(titleOf(a)) + ' - ' + (subject ? 'Activity details for ' + e(subject) : 'Activity Details') + '</p>' +
            ((isChange || isCreate) && desc ? '<p class="ins-desc">' + e(desc) + '</p>' : '') + body + '</div></div>' +
            '<div class="ins-when"><span>' + icon('fa-calendar') + ' ' + e(dayLabel(a.performed_at)) + '</span><span>' + icon('fa-clock') + ' ' + e(timeOnly(a.performed_at)) + '</span>' +
            '<span class="mono">' + e(a.ip_address || '') + '</span><span class="text-muted small">Log #' + a.log_id + '</span></div></article>';
    }

    /* ---------- side cards ---------- */
    function sideCard(title, ic, body, more) {
        return '<div class="card ins-card"><div class="card-header"><h3 class="card-title">' + icon(ic) + ' ' + title + '</h3>' +
            (more === 'modal' ? '<button type="button" class="btn btn-outline btn-sm" data-ins-modal-open>' + icon('fa-list') + ' <span>View All</span></button>'
                : more ? '<button type="button" class="btn btn-outline btn-sm" data-ins-more>' + icon('fa-list') + ' <span>View All</span></button>' : '') +
            '</div><div class="card-body">' + body + '</div></div>';
    }
    function bar(pct, cls) {
        return '<div class="bar ins-bar"><span class="bar-fill ' + (cls || 'bar-primary') + '" data-pct="' + Math.max(0, Math.min(100, pct)) + '"></span></div>';
    }
    function mini(text, cls) { return '<span class="ins-mini' + (cls ? ' ' + cls : '') + '">' + text + '</span>'; }

    /** Role users ranked by verification responses in scope. */
    function topPerformers(scope, um) {
        const map = {};
        scope.forEach(function (a) {
            if (a.entity_type !== 'TASK' || !a.performed_by) { return; }
            const r = map[a.performed_by] = map[a.performed_by] || { approved: 0, rejected: 0, last: a.performed_at, logs: 0 };
            r.logs++;
            if (a.action.indexOf('APPROVED') !== -1) { r.approved++; } else { r.rejected++; }
            if (a.performed_at > r.last) { r.last = a.performed_at; }
        });
        const list = Object.keys(map).map(function (id) {
            const u = um[id];
            const assigned = DB.where('task_assignees', { user_id: Number(id) }).length;
            const pending = DB.where('task_assignees', { user_id: Number(id) }).filter(function (x) {
                const t = DB.find('tasks', x.task_id);
                return t.task_status === 'PENDING' && DB.find('rounds', t.round_id).round_status === 'OPEN';
            }).length;
            const m = map[id];
            return { u: u, m: m, pending: pending, pct: assigned ? Math.round((m.approved + m.rejected) * 100 / assigned) : 0 };
        }).sort(function (a, b) { return (b.m.logs - a.m.logs) || (b.pct - a.pct); });
        const body = list.length ? list.map(function (x, i) {
            return '<div class="ins-rank' + (i >= 3 ? ' ins-extra' : '') + '"><div class="ins-rank-head"><span class="ins-no">' + (i + 1) + '</span><div class="ins-rank-name"><strong>' + e(x.u.name) +
                '</strong><small>' + e(DB.find('roles', x.u.role_id).role_name) + ' · ' + x.m.logs + ' response(s) · Last: ' + e(dayLabel(x.m.last).slice(0, 6) + ', ' + timeOnly(x.m.last)) + '</small></div>' +
                '<span class="ins-pct">' + x.pct + '%</span></div>' + bar(x.pct) +
                '<div class="ins-minis">' + mini(icon('fa-check') + ' Approved: ' + x.m.approved, 'ok') + mini(icon('fa-xmark') + ' Rejected: ' + x.m.rejected, x.m.rejected ? 'bad' : '') +
                mini(icon('fa-hourglass-half') + ' Pending: ' + x.pending, x.pending ? 'warn' : '') + '</div></div>';
        }).join('') : '<p class="text-muted small">No verification responses in this period.</p>';
        // full ranking for the "View All" popup
        const table = '<template data-ins-modal-body data-icon="fa-ranking-star" data-title="Role User Performers" data-sub="' + list.length + ' role user(s) · ranked by verification responses in the selected period"><div class="table-wrap"><table class="table table-stack"><thead><tr><th>#</th><th>Role user</th><th class="text-right">Responses</th>' +
            '<th class="text-right">Approved</th><th class="text-right">Rejected</th><th class="text-right">Pending</th><th>Completion</th></tr></thead><tbody>' +
            list.map(function (x, i) {
                return '<tr><td data-label="#"><span class="ins-no' + (i === 0 ? ' top' : '') + '">' + (i + 1) + '</span></td>' +
                    '<td data-label="Role user"><strong>' + e(x.u.name) + '</strong><span class="cell-sub">' + e(DB.find('roles', x.u.role_id).role_name) + ' · Last: ' + e(dayLabel(x.m.last).slice(0, 6) + ', ' + timeOnly(x.m.last)) + '</span></td>' +
                    '<td data-label="Responses" class="text-right">' + x.m.logs + '</td>' +
                    '<td data-label="Approved" class="text-right">' + mini(icon('fa-check') + ' ' + x.m.approved, 'ok') + '</td>' +
                    '<td data-label="Rejected" class="text-right">' + mini(icon('fa-xmark') + ' ' + x.m.rejected, x.m.rejected ? 'bad' : '') + '</td>' +
                    '<td data-label="Pending" class="text-right">' + mini(icon('fa-hourglass-half') + ' ' + x.pending, x.pending ? 'warn' : '') + '</td>' +
                    '<td data-label="Completion"><div class="ins-perf">' + bar(x.pct).replace(' ins-bar', '') + '<b>' + x.pct + '%</b></div></td></tr>';
            }).join('') + '</tbody></table></div></template>';
        return sideCard('Top Role User Performers', 'fa-ranking-star', body + (list.length > 3 ? table : ''), list.length > 3 ? 'modal' : false);
    }

    /** Per section: task completion, SLA and outcome. */
    function sectionPerformance() {
        const t = DB.today();
        const rows = DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).map(function (r) {
            const tasks = DB.where('tasks', { role_id: r.role_id }).map(function (x) {
                return Object.assign({}, x, { round: DB.find('rounds', x.round_id) });
            });
            const open = tasks.filter(function (x) { return x.task_status === 'PENDING' && x.round.round_status === 'OPEN'; });
            const done = tasks.filter(function (x) { return x.task_status !== 'PENDING'; });
            const overdue = open.filter(function (x) { return x.sla_due_date < t; }).length;
            const days = done.map(function (x) { return (new Date(x.responded_at.replace(' ', 'T')) - new Date(x.round.initiated_at.replace(' ', 'T'))) / 864e5; });
            const avg = days.length ? days.reduce(function (s, d) { return s + d; }, 0) / days.length : 0;
            const withinSla = done.filter(function (x) { return dateOnly(x.responded_at) <= x.sla_due_date; }).length;
            const relevant = done.length + open.length;
            return {
                r: r, total: tasks.length, done: done.length, open: open.length, overdue: overdue, avg: avg, withinSla: withinSla,
                approved: done.filter(function (x) { return x.task_status === 'APPROVED'; }).length,
                rejected: done.filter(function (x) { return x.task_status === 'REJECTED'; }).length,
                pct: relevant ? Math.round(done.length * 100 / relevant) : 0
            };
        }).filter(function (x) { return x.total > 0; }).sort(function (a, b) { return b.pct - a.pct || b.done - a.done; });
        const body = rows.length ? rows.map(function (x, i) {
            return '<div class="ins-rank' + (i >= 3 ? ' ins-extra' : '') + '"><div class="ins-rank-head"><span class="ins-no">' + (i + 1) + '</span><div class="ins-rank-name"><strong>' + e(x.r.role_name) +
                '</strong><small>' + x.total + ' task(s) · SLA ' + x.r.sla_days + ' day(s)</small></div><span class="ins-pct">' + x.pct + '%</span></div>' +
                bar(x.pct, x.overdue ? 'bar-warning' : 'bar-success') + '<div class="ins-minis">' +
                mini('Responded: ' + x.done) + mini('Pending: ' + x.open, x.open ? 'warn' : '') + mini('Overdue: ' + x.overdue, x.overdue ? 'bad' : '') +
                mini('Approved: ' + x.approved, 'ok') + mini('Rejected: ' + x.rejected, x.rejected ? 'bad' : '') +
                mini('Avg response: ' + x.avg.toFixed(1) + ' d') + mini('Within SLA: ' + x.withinSla + '/' + x.done) + '</div></div>';
        }).join('') : '<p class="text-muted small">No verification tasks yet.</p>';
        // full table for the "View All" popup
        const table = '<template data-ins-modal-body data-icon="fa-users-gear" data-title="Section Verification Performance" data-sub="' + rows.length + ' section(s) · ranked by completion · all verification rounds">' +
            '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>#</th><th>Section</th><th class="text-right">Responded</th>' +
            '<th class="text-right">Pending</th><th class="text-right">Overdue</th><th class="text-right">Approved</th><th class="text-right">Rejected</th>' +
            '<th class="text-right">Avg resp.</th><th class="text-right">Within SLA</th><th>Completion</th></tr></thead><tbody>' +
            rows.map(function (x, i) {
                return '<tr><td data-label="#"><span class="ins-no' + (i === 0 ? ' top' : '') + '">' + (i + 1) + '</span></td>' +
                    '<td data-label="Section"><strong>' + e(x.r.role_name) + '</strong><span class="cell-sub">' + x.total + ' task(s) · SLA ' + x.r.sla_days + ' d</span></td>' +
                    '<td data-label="Responded" class="text-right">' + x.done + '</td>' +
                    '<td data-label="Pending" class="text-right">' + mini(icon('fa-hourglass-half') + ' ' + x.open, x.open ? 'warn' : '') + '</td>' +
                    '<td data-label="Overdue" class="text-right">' + mini(icon('fa-clock') + ' ' + x.overdue, x.overdue ? 'bad' : '') + '</td>' +
                    '<td data-label="Approved" class="text-right">' + mini(icon('fa-check') + ' ' + x.approved, 'ok') + '</td>' +
                    '<td data-label="Rejected" class="text-right">' + mini(icon('fa-xmark') + ' ' + x.rejected, x.rejected ? 'bad' : '') + '</td>' +
                    '<td data-label="Avg response" class="text-right">' + x.avg.toFixed(1) + ' d</td>' +
                    '<td data-label="Within SLA" class="text-right">' + x.withinSla + '/' + x.done + '</td>' +
                    '<td data-label="Completion"><div class="ins-perf">' + bar(x.pct, x.overdue ? 'bar-warning' : 'bar-success').replace(' ins-bar', '') + '<b>' + x.pct + '%</b></div></td></tr>';
            }).join('') + '</tbody></table></div></template>';
        return sideCard('Section Verification Performance', 'fa-users-gear', body + (rows.length > 3 ? table : ''), rows.length > 3 ? 'modal' : false);
    }

    function operational(scope) {
        const total = scope.length || 1;
        const items = FOCUS.map(function (f) {
            const n = scope.filter(function (a) { return areaOf(a) === f[0]; }).length;
            const pct = Math.round(n * 100 / total);
            return { f: f, n: n, pct: pct, ok: pct >= EXPECTED_MIN };
        });
        const weak = items.filter(function (x) { return !x.ok; });
        const body = '<p class="ins-op-summary"><strong>' + (weak.length ? weak.length + ' focus area(s) need attention before publishing directions.' : 'All focus areas are active as expected.') + '</strong></p>' +
            items.map(function (x) {
                return '<div class="ins-focus' + (x.ok ? '' : ' warn') + '"><p class="ins-focus-title">' + icon(AREAS[x.f[0]][1]) + ' ' + e(x.f[1]) + '</p>' +
                    '<div class="ins-minis">' + mini('Actual: ' + x.pct + '%', x.ok ? 'ok' : 'warn') + mini('Expected minimum: ' + EXPECTED_MIN + '%') + mini('Logs: ' + x.n) + '</div>' +
                    bar(x.pct * 100 / Math.max(EXPECTED_MIN, 1) > 100 ? 100 : x.pct * 100 / EXPECTED_MIN, x.ok ? 'bar-success' : 'bar-warning') +
                    '<p class="ins-focus-text">' + (x.ok ? e(x.f[1]) + ' are active as expected.'
                        : e(x.f[1]) + ' are below the expected share in this period.<br><strong>Suggested direction:</strong> ' + e(x.f[2])) + '</p></div>';
            }).join('') +
            '<form method="post" data-action="publish-direction" data-confirm="Publish directions for ' + (weak.length || 'all') + ' focus area(s)? This is recorded in the audit log." data-confirm-ok="Yes, publish">' +
            '<input type="hidden" name="areas" value="' + e((weak.length ? weak : items).map(function (x) { return x.f[1]; }).join('|')) + '">' +
            '<button type="submit" class="btn btn-primary btn-block" data-loading-text="Publishing…">' + icon('fa-bullhorn') + ' <span>Publish Direction</span></button></form>';
        return sideCard('Operational Analysis', 'fa-chart-line', body, false);
    }

    function moduleActivity(scope) {
        const groups = {};
        scope.forEach(function (a) { (groups[areaOf(a)] = groups[areaOf(a)] || []).push(a); });
        const max = Math.max.apply(null, [1].concat(Object.keys(groups).map(function (k) { return groups[k].length; })));
        const body = Object.keys(groups).length ? Object.keys(groups).sort(function (a, b) { return groups[b].length - groups[a].length; }).map(function (k) {
            const top = {};
            groups[k].forEach(function (a) { const d = titleOf(a); top[d] = (top[d] || 0) + 1; });
            return '<div class="ins-module"><div class="bar-label"><span>' + icon(AREAS[k][1]) + ' ' + e(AREAS[k][0]) + '</span><strong>' + groups[k].length + '</strong></div>' +
                bar(groups[k].length * 100 / max) + '<ul class="ins-dots">' + Object.keys(top).sort(function (a, b) { return top[b] - top[a]; }).slice(0, 3).map(function (d) {
                    return '<li>' + e(d) + ' <small>(' + top[d] + ')</small></li>';
                }).join('') + '</ul></div>';
        }).join('') : '<p class="text-muted small">No activity in this period.</p>';
        return sideCard('Module Activity', 'fa-chart-simple', body, false);
    }

    function mostActive(scope, um) {
        const m = {};
        scope.forEach(function (a) {
            if (!a.performed_by) { return; }
            const r = m[a.performed_by] = m[a.performed_by] || { n: 0, last: a.performed_at };
            r.n++;
            if (a.performed_at > r.last) { r.last = a.performed_at; }
        });
        const list = Object.keys(m).sort(function (a, b) { return m[b].n - m[a].n; }).slice(0, 6);
        const body = list.length ? '<ul class="ins-users">' + list.map(function (id) {
            const u = um[id];
            return '<li><div><strong>' + e(u ? u.name : 'User #' + id) + '</strong><small>' + e((u ? u.designation : '') + ' · ' + dayLabel(m[id].last).slice(0, 6) + ', ' + timeOnly(m[id].last)) + '</small></div>' +
                '<a class="ins-count" href="' + P.url('/admin/audit-logs', { user: id }) + '" title="Show this user\'s activity">' + m[id].n + '</a></li>';
        }).join('') + '</ul>' : '<p class="text-muted small">No user activity in this period.</p>';
        return sideCard('Most Active Users', 'fa-user-clock', body, false);
    }

    function quickInsight(scope) {
        if (!scope.length) { return sideCard('Quick Insight', 'fa-lightbulb', '<p class="text-muted small">Select a wider date range to see insights.</p>', false); }
        const hours = {}, days = {};
        scope.forEach(function (a) {
            const h = Number(String(a.performed_at).slice(11, 13));
            hours[h] = (hours[h] || 0) + 1;
            const d = dateOnly(a.performed_at);
            days[d] = (days[d] || 0) + 1;
        });
        const ph = Object.keys(hours).sort(function (a, b) { return hours[b] - hours[a]; })[0];
        const pd = Object.keys(days).sort(function (a, b) { return days[b] - days[a]; })[0];
        const h12 = (Number(ph) % 12 || 12) + ' ' + (Number(ph) >= 12 ? 'PM' : 'AM');
        const appr = scope.filter(function (a) { return a.action === 'TASK_APPROVED'; }).length;
        const rej = scope.filter(function (a) { return a.action === 'TASK_REJECTED'; }).length;
        const failed = DB.count('login_attempts', function (x) { return x.success === 0; });
        const body = '<ul class="ins-insights">' +
            '<li>' + icon('fa-clock') + '<span>Peak activity is around <strong>' + h12 + '</strong> with ' + hours[ph] + ' logged action(s).</span></li>' +
            '<li>' + icon('fa-calendar-check') + '<span>Busiest day: <strong>' + e(dayLabel(pd)) + '</strong> (' + days[pd] + ' action(s)).</span></li>' +
            (appr + rej ? '<li>' + icon('fa-scale-balanced') + '<span>Section decisions: <strong>' + appr + ' approved</strong> / <strong>' + rej + ' rejected</strong> (' + Math.round(appr * 100 / (appr + rej)) + '% approval).</span></li>' : '') +
            '<li>' + icon('fa-user-lock') + '<span>Failed login attempts recorded: <strong>' + failed + '</strong>.</span></li></ul>';
        return sideCard('Quick Insight', 'fa-lightbulb', body, false);
    }

})(window, document);
