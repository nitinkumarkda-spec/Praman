/* ===== js/pages/dashboards.js ===== */
/* PRAMAAN — dashboards of all 4 panels: clickable stat cards + charts */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, L = P.Lookups, C = P.COLORS;

    function statusParts(counts, onlyNonZero) {
        const keys = Object.keys(counts).filter(function (k) { return !onlyNonZero || counts[k] > 0; });
        return {
            keys: keys, labels: keys.map(P.label), data: keys.map(function (k) { return counts[k]; }),
            colors: keys.map(function (k) { return P.STATUS_COLORS[k] || P.COLORS.slate; })
        };
    }
    function roundsResult() {
        return {
            open: DB.count('rounds', { round_status: 'OPEN' }),
            ready: DB.count('rounds', { round_status: 'CLOSED', result: 'READY' }),
            notReady: DB.count('rounds', { round_status: 'CLOSED', result: 'NOT_READY' })
        };
    }
    function sectionOutcomes() {
        const roles = DB.all('roles').sort(P.by(function (r) { return r.sort_order; }));
        const t = DB.today();
        const rows = roles.map(function (r) {
            const tasks = DB.where('tasks', { role_id: r.role_id });
            const openPending = tasks.filter(function (x) { return x.task_status === 'PENDING' && DB.find('rounds', x.round_id).round_status === 'OPEN'; });
            return {
                name: r.role_name.replace(' Cell', ''),
                approved: tasks.filter(function (x) { return x.task_status === 'APPROVED'; }).length,
                rejected: tasks.filter(function (x) { return x.task_status === 'REJECTED'; }).length,
                pending: openPending.filter(function (x) { return x.sla_due_date >= t; }).length,
                overdue: openPending.filter(function (x) { return x.sla_due_date < t; }).length
            };
        });
        return rows;
    }
    const grid = function (cls, html) { return '<div class="chart-grid ' + cls + '">' + html + '</div>'; };
    /** Section outcomes as summary tiles + one 100%-stacked bar per section (readable at any width). */
    function sectionOutcomeCard(rows, title, sub) {
        const keys = [['approved', 'Approved', C.green], ['pending', 'Pending', C.amber], ['overdue', 'Overdue', C.purple], ['rejected', 'Rejected', C.red]];
        const sum = function (k) { return rows.reduce(function (t, r) { return t + r[k]; }, 0); };
        const tiles = keys.map(function (k) {
            return '<div class="so-tile"><b>' + P.fmt_num(sum(k[0])) + '</b><span><i class="so-dot" data-css-bg="' + k[2] + '"></i>' + k[1] + '</span></div>';
        }).join('');
        const list = rows.map(function (r) {
            const total = keys.reduce(function (t, k) { return t + r[k[0]]; }, 0);
            const segs = total ? keys.filter(function (k) { return r[k[0]] > 0; }).map(function (k) {
                return '<i data-css-w="' + (r[k[0]] * 100 / total).toFixed(2) + '" data-css-bg="' + k[2] + '" title="' + k[1] + ': ' + r[k[0]] + '"></i>';
            }).join('') : '';
            const tip = keys.map(function (k) { return k[1] + ' ' + r[k[0]]; }).join(' · ');
            return '<li class="so-row" title="' + e(r.name + ' — ' + tip) + '"><span class="so-name">' + e(r.name) + '</span><span class="so-bar">' + segs + '</span><span class="so-total">' + total + '</span></li>';
        }).join('');
        return '<div class="card chart-card"><div class="card-header"><h3 class="card-title">' + icon('fa-list-check') + ' ' + e(title) + '</h3>' +
            (sub ? '<span class="text-muted small">' + e(sub) + '</span>' : '') + '</div>' +
            '<div class="card-body"><div class="so-sum">' + tiles + '</div><ul class="so-list">' + list + '</ul></div></div>';
    }

    /* ======================= SUPER ADMIN ======================= */
    P.route('/admin/dashboard', 'SUPER_ADMIN', function (ctx) {
        const isLocked = function (u) { return !!u.locked_until && u.locked_until > DB.now(); };
        const c = {
            users_total: DB.count('users'), users_active: DB.count('users', { is_active: 1 }), users_locked: DB.count('users', isLocked),
            roles_total: DB.count('roles'), roles_active: DB.count('roles', { is_active: 1 }),
            schemes_total: DB.count('schemes'), schemes_active: DB.count('schemes', { is_active: 1 }),
            properties_total: DB.count('properties', { is_active: 1 }),
            failed_24h: DB.count('login_attempts', function (a) { return a.success === 0 && a.attempted_at > DB.now(-1440); }),
            logs_today: DB.count('audit_logs', function (a) { return a.performed_at.slice(0, 10) === DB.today(); })
        };
        const byRole = DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).map(function (r) {
            const us = DB.where('users', { role_id: r.role_id });
            return { r: r, total: us.length, active: us.filter(function (u) { return u.is_active; }).length };
        });
        return {
            title: 'Dashboard',
            html: P.pageHeader('Welcome, ' + e(ctx.user.name), 'System overview — users, roles, schemes, properties, verification and activity.',
                '<a href="#/admin/audit-logs" class="btn btn-light">' + icon('fa-chart-line') + ' <span>Activity &amp; Audit</span></a>' +
                '<a href="#/admin/users/create" class="btn btn-primary">' + icon('fa-user-plus') + ' <span>Add User</span></a>') +
                '<div class="stat-grid dash-stats">' +
                P.statCard('navy', 'fa-users', c.users_total, 'Users', P.fmt_num(c.users_active) + ' active', '#/admin/users') +
                P.statCard('gold', 'fa-sitemap', c.roles_total, 'Roles / Sections', P.fmt_num(c.roles_active) + ' active', '#/admin/roles') +
                P.statCard('blue', 'fa-map', c.schemes_total, 'Schemes', P.fmt_num(c.schemes_active) + ' active', P.url('/admin/audit-logs', { area: 'SCHEME', quick: 'area:SCHEME' })) +
                P.statCard('green', 'fa-building', c.properties_total, 'Properties', 'registered', P.url('/admin/audit-logs', { area: 'PROPERTY', quick: 'area:PROPERTY' })) +
                P.statCard('red', 'fa-user-lock', c.users_locked, 'Locked accounts', P.fmt_num(c.failed_24h) + ' failed logins (24h)', P.url('/admin/users', { status: 'locked' })) +
                P.statCard('navy', 'fa-gavel', c.logs_today, 'Actions today', 'audit entries', P.url('/admin/audit-logs', { quick: 'today', from: DB.today(), to: DB.today() })) +
                '</div>' +
                grid('cols-2', P.chartCard('ch-status', 'Property status', 'fa-chart-pie', { height: 300, sub: 'click a slice' }) +
                    sectionOutcomeCard(sectionOutcomes(), 'Section verification outcomes', 'tasks per section, all rounds')) +
                '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-sitemap') + ' Users per role</h3><a href="#/admin/roles" class="small">Role Master ' + icon('fa-arrow-right') + '</a></div>' +
                '<div class="card-body p-0"><div class="table-wrap"><table class="table table-stack table-top"><thead><tr><th>Role / Section</th><th class="text-right">Users</th><th class="text-right">Active</th><th>Status</th></tr></thead><tbody>' +
                byRole.map(function (x) {
                    return '<tr><td data-label="Role"><a href="' + P.url('/admin/users', { role_id: x.r.role_id }) + '">' + e(x.r.role_name) + '</a></td><td data-label="Users" class="text-right">' + x.total + '</td>' +
                        '<td data-label="Active" class="text-right">' + x.active + '</td><td data-label="Status">' + P.active_badge(x.r.is_active) + '</td></tr>';
                }).join('') + '</tbody></table></div></div></div>' +
                P.activity(P.recentActivity(8), 'Recent system activity') + '</div>',
            after: function () {
                const st = statusParts(P.propertyStatusCounts(), true);
                P.pie('ch-status', st.labels, st.data, st.colors, true, function (i) { P.go('/admin/audit-logs', { area: 'PROPERTY', quick: 'area:PROPERTY', q: st.labels[i] }); });
            }
        };
    });

    /* ======================= DATA ENTRY ======================= */
    P.route('/data-entry/dashboard', 'DATA_ENTRY', function (ctx) {
        const me = ctx.user.user_id;
        const activeSectors = DB.count('sectors', function (s) { const sc = DB.find('schemes', s.scheme_id); return s.is_active && sc && sc.is_active; });
        return {
            title: 'Dashboard',
            html: P.pageHeader('Welcome, ' + e(ctx.user.name), 'Scheme, sector and property register.',
                '<a href="#/data-entry/schemes/create" class="btn btn-light">' + icon('fa-map') + ' <span>Create Scheme</span></a>' +
                '<a href="#/data-entry/properties/create" class="btn btn-primary">' + icon('fa-circle-plus') + ' <span>Add Property</span></a>') +
                '<div class="stat-grid dash-stats">' +
                P.statCard('navy', 'fa-map', DB.count('schemes', { is_active: 1 }), 'Active schemes', P.fmt_num(DB.count('schemes')) + ' in register', P.url('/data-entry/schemes', { status: 'active' })) +
                P.statCard('gold', 'fa-table-cells', activeSectors, 'Active sectors', 'in active schemes', '#/data-entry/schemes') +
                P.statCard('blue', 'fa-building', DB.count('properties', { is_active: 1 }), 'All properties', 'registered', '#/data-entry/properties') +
                P.statCard('green', 'fa-list', DB.count('properties', { is_active: 1, current_status: 'LISTED' }), 'Listed (editable)', 'ready to send', P.url('/data-entry/properties', { status: 'LISTED' })) +
                P.statCard('red', 'fa-user-pen', DB.count('properties', { is_active: 1, created_by: me }), 'Entered by me', 'your records', '#/data-entry/properties') +
                '</div>' +
                grid('cols-3', P.chartCard('ch-status', 'My properties by status', 'fa-chart-pie', { sub: 'click a slice' }) +
                    P.chartCard('ch-types', 'Properties by type', 'fa-shapes') +
                    P.chartCard('ch-units', 'Area by scheme (sq m)', 'fa-ruler-combined')) +
                grid('cols-2', P.chartCard('ch-entries', 'Properties entered — last 14 days', 'fa-chart-line', { height: 280 }) +
                    P.chartCard('ch-schemes', 'Properties per scheme', 'fa-map', { height: 280, href: '#/data-entry/schemes' })) +
                '<div class="grid-2">' + P.status_overview(P.propertyStatusCounts(me), 'My properties by status') + P.activity(P.recentActivity(8, me), 'My recent activity') + '</div>',
            after: function () {
                const st = statusParts(P.propertyStatusCounts(me), true);
                P.pie('ch-status', st.labels, st.data, st.colors, true, function (i) { P.go('/data-entry/properties', { status: st.keys[i] }); });
                const types = Object.keys(L.PROPERTY_TYPES);
                P.pie('ch-types', types.map(function (t) { return L.PROPERTY_TYPES[t]; }), types.map(function (t) { return DB.count('properties', { property_type: t, is_active: 1 }); }),
                    null, false, function (i) { P.go('/data-entry/properties', { type: types[i] }); });
                const sch = DB.all('schemes');
                P.bars('ch-units', sch.map(function (s) { return s.scheme_code; }), [{
                    label: 'Area (sq m)', backgroundColor: C.teal,
                    data: sch.map(function (s) { return Math.round(DB.where('properties', { scheme_id: s.scheme_id, is_active: 1 }).reduce(function (t, p) { return t + p.area_sqm; }, 0)); })
                }], { horizontal: true });
                const days = P.lastDays(14);
                const props = DB.where('properties', { is_active: 1 });
                P.lines('ch-entries', days.labels, [
                    { label: 'All', data: P.countByDay(props, 'created_at', days.keys), color: C.navy },
                    { label: 'By me', data: P.countByDay(props, 'created_at', days.keys, function (p) { return p.created_by === me; }), color: C.gold }
                ]);
                P.bars('ch-schemes', sch.map(function (s) { return s.scheme_code; }), [{
                    label: 'Properties', data: sch.map(function (s) { return DB.count('properties', { scheme_id: s.scheme_id, is_active: 1 }); }), backgroundColor: C.navy
                }, {
                    label: 'Active sectors', data: sch.map(function (s) { return DB.Scheme.sectors(s.scheme_id, true).length; }), backgroundColor: C.gold
                }], { onClick: function (i) { P.go('/data-entry/properties', { scheme_id: sch[i].scheme_id }); } });
            }
        };
    });

    /* ======================= OIC ======================= */
    P.route('/oic/dashboard', 'OIC', function (ctx) {
        const sc = P.propertyStatusCounts();
        const rr = roundsResult();
        const openIds = DB.where('rounds', { round_status: 'OPEN' }).map(function (r) { return r.round_id; });
        const pending = DB.count('tasks', function (t) { return t.task_status === 'PENDING' && openIds.indexOf(t.round_id) !== -1; });
        const dueCount = DB.count('properties', function (p) { return p.is_active && DB.Verification.isDue(p); });
        return {
            title: 'Dashboard',
            html: P.pageHeader('Welcome, ' + e(ctx.user.name), 'Verification rounds and auction readiness.',
                '<a href="' + P.url('/oic/properties', { status: 'LISTED' }) + '" class="btn btn-primary">' + icon('fa-paper-plane') + ' <span>Send for verification</span></a>' +
                '<a href="' + P.url('/oic/properties', { status: 'NOT_READY' }) + '" class="btn btn-light">' + icon('fa-rotate') + ' <span>Not ready properties</span></a>') +
                '<div class="stat-grid dash-stats">' +
                P.statCard('blue', 'fa-list', sc.LISTED, 'Listed (to send)', 'ready to verify', P.url('/oic/properties', { status: 'LISTED' })) +
                P.statCard('gold', 'fa-hourglass-half', sc.UNDER_VERIFICATION, 'Under verification', P.fmt_num(pending) + ' pending role tasks', P.url('/oic/properties', { status: 'UNDER_VERIFICATION' })) +
                P.statCard('green', 'fa-circle-check', sc.AUCTION_READY, 'Auction ready', dueCount ? P.fmt_num(dueCount) + ' due for re-verify' : 'cleared by all cells', P.url('/oic/properties', { status: dueCount ? 'REVERIFY_DUE' : 'AUCTION_READY' })) +
                P.statCard('red', 'fa-calendar-xmark', sc.EXPIRED || 0, 'Validity expired', 'need periodic re-verification', P.url('/oic/properties', { status: 'EXPIRED' })) +
                P.statCard('red', 'fa-circle-xmark', sc.NOT_READY, 'Not ready', 'rejected / issues found', P.url('/oic/properties', { status: 'NOT_READY' })) +
                P.statCard('navy', 'fa-folder-open', rr.open, 'Open rounds', P.fmt_num(rr.ready) + ' ready / ' + P.fmt_num(rr.notReady) + ' not ready closed', P.url('/oic/properties', { status: 'UNDER_VERIFICATION' })) +
                '</div>' +
                grid('cols-3', P.chartCard('ch-status', 'Property status', 'fa-chart-pie', { sub: 'click a slice' }) +
                    P.chartCard('ch-rounds', 'Verification rounds', 'fa-layer-group') +
                    P.chartCard('ch-ready', 'Readiness by scheme', 'fa-gavel')) +
                grid('cols-2', P.chartCard('ch-sections', 'Section responses (all rounds)', 'fa-list-check', { height: 280 }) +
                    P.chartCard('ch-trend', 'Sent vs decided — last 14 days', 'fa-chart-line', { height: 280 })) +
                '<div class="grid-2">' + P.status_overview(sc) + P.activity(P.recentActivity(8, ctx.user.user_id), 'My recent activity') + '</div>',
            after: function () {
                const st = statusParts(sc, true);
                P.pie('ch-status', st.labels, st.data, st.colors, true, function (i) { P.go('/oic/properties', { status: st.keys[i] }); });
                P.pie('ch-rounds', ['Open', 'Closed — Ready', 'Closed — Not ready'], [rr.open, rr.ready, rr.notReady], [C.amber, C.green, C.red]);
                const sch = DB.all('schemes');
                P.bars('ch-ready', sch.map(function (s) { return s.scheme_code; }), ['AUCTION_READY', 'UNDER_VERIFICATION', 'NOT_READY', 'LISTED'].map(function (k) {
                    return { label: P.label(k), backgroundColor: P.STATUS_COLORS[k], data: sch.map(function (s) { return DB.count('properties', { scheme_id: s.scheme_id, current_status: k, is_active: 1 }); }) };
                }), { stacked: true, onClick: function (i) { P.go('/oic/properties', { scheme_id: sch[i].scheme_id }); } });
                const so = sectionOutcomes();
                P.bars('ch-sections', so.map(function (x) { return x.name; }), [
                    { label: 'Approved', data: so.map(function (x) { return x.approved; }), backgroundColor: C.green },
                    { label: 'Rejected', data: so.map(function (x) { return x.rejected; }), backgroundColor: C.red },
                    { label: 'Pending', data: so.map(function (x) { return x.pending; }), backgroundColor: C.amber },
                    { label: 'Overdue', data: so.map(function (x) { return x.overdue; }), backgroundColor: C.dark }
                ], { stacked: true });
                const days = P.lastDays(14);
                const logs = DB.all('audit_logs');
                P.lines('ch-trend', days.labels, [
                    { label: 'Sent / re-sent', data: P.countByDay(logs, 'performed_at', days.keys, function (a) { return a.action === 'ROUND_INITIATED' || a.action === 'REINITIATED'; }), color: C.navy },
                    { label: 'Section decisions', data: P.countByDay(logs, 'performed_at', days.keys, function (a) { return a.entity_type === 'TASK'; }), color: C.gold },
                    { label: 'Rounds closed', data: P.countByDay(logs, 'performed_at', days.keys, function (a) { return a.action === 'ROUND_CLOSED'; }), color: C.green }
                ]);
            }
        };
    });

    /* ======================= ROLE USER ======================= */
    function myTasks(uid) {
        const mine = DB.where('task_assignees', { user_id: uid }).map(function (a) { return a.task_id; });
        return DB.where('tasks', function (t) { return mine.indexOf(t.task_id) !== -1 && (t.task_status === 'PENDING' || t.responded_by === uid); })
            .map(function (t) {
                const vr = DB.find('rounds', t.round_id);
                const p = DB.find('properties', vr.property_id);
                return Object.assign({}, t, {
                    view: t.task_status === 'PENDING' && vr.round_status !== 'OPEN' ? 'NOT_REQUIRED' : t.task_status,
                    round_no: vr.round_no, initiated_at: vr.initiated_at, property: p, scheme: DB.find('schemes', p.scheme_id), sector: DB.find('sectors', p.sector_id)
                });
            });
    }
    P.route('/role/dashboard', 'ROLE_USER', function (ctx) {
        const me = ctx.user.user_id;
        const t = DB.today();
        const all = myTasks(me);
        const pending = all.filter(function (x) { return x.view === 'PENDING'; });
        const c = {
            pending: pending.length, overdue: pending.filter(function (x) { return x.sla_due_date < t; }).length,
            approved: all.filter(function (x) { return x.task_status === 'APPROVED' && x.responded_by === me; }).length,
            rejected: all.filter(function (x) { return x.task_status === 'REJECTED' && x.responded_by === me; }).length,
            closed: all.filter(function (x) { return x.view === 'NOT_REQUIRED'; }).length
        };
        const urgent = pending.slice().sort(P.by(function (x) { return x.sla_due_date; })).slice(0, 6);
        return {
            title: 'Dashboard',
            html: P.pageHeader('Welcome, ' + e(ctx.user.name), e((ctx.user.role_name || '') + ' · ' + ctx.user.designation) + ' — properties sent to you for verification.',
                '<a href="' + P.url('/role/tasks', { status: 'pending' }) + '" class="btn btn-primary">' + icon('fa-clipboard-list') + ' <span>Pending tasks</span></a>') +
                '<div class="stat-grid dash-stats">' +
                P.statCard('gold', 'fa-hourglass-half', c.pending, 'Pending', 'awaiting your response', P.url('/role/tasks', { status: 'pending' })) +
                P.statCard('red', 'fa-stopwatch' + (c.overdue > 0 ? ' fa-shake' : ''), c.overdue, 'Overdue (SLA)', c.overdue > 0 ? 'action required now' : 'all SLA on track', P.url('/role/tasks', { status: 'overdue' })) +
                P.statCard('green', 'fa-circle-check', c.approved, 'Approved by me', 'verified & approved', P.url('/role/tasks', { status: 'approved' })) +
                P.statCard('navy', 'fa-circle-xmark', c.rejected, 'Rejected by me', 'objections raised', P.url('/role/tasks', { status: 'rejected' })) +
                P.statCard('blue', 'fa-minus', c.closed, 'Not required', 'round closed by another section', P.url('/role/tasks', { status: 'closed' })) +
                '</div>' +
                grid('cols-3', P.chartCard('ch-mine', 'My tasks by status', 'fa-chart-pie', { sub: 'click a slice' }) +
                    P.chartCard('ch-scheme', 'My tasks by scheme', 'fa-map') +
                    P.chartCard('ch-sla', 'Pending — days to SLA', 'fa-stopwatch')) +
                grid('cols-1', P.chartCard('ch-trend', 'Received vs responded — last 14 days', 'fa-chart-line', { height: 260 })) +
                '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-fire') + ' Most urgent</h3>' +
                '<a href="' + P.url('/role/tasks', { status: 'pending' }) + '" class="small">View all ' + icon('fa-arrow-right') + '</a></div><div class="card-body p-0">' +
                (urgent.length ? '<ul class="activity-list">' + urgent.map(function (x) {
                    const late = x.sla_due_date < t;
                    return '<li><span class="activity-icon">' + icon('fa-building') + '</span><div class="activity-body">' +
                        '<a href="#/role/tasks/' + x.task_id + '"><strong>Plot ' + e(x.property.plot_no) + '</strong> · ' + e(L.PROPERTY_TYPES[x.property.property_type]) + '</a>' +
                        '<span class="text-muted">' + e(x.scheme.scheme_name + ' / ' + x.sector.sector_name) + ' · R' + x.round_no + '</span>' +
                        '<small class="' + (late ? 'text-danger' : 'text-muted') + '"><i class="fa-regular fa-clock" aria-hidden="true"></i> Due ' + e(P.fmt_date(x.sla_due_date)) + (late ? ' — overdue' : '') + '</small></div>' +
                        '<a href="#/role/tasks/' + x.task_id + '" class="btn btn-primary btn-sm ml-auto">' + icon('fa-clipboard-check') + ' <span>Verify</span></a></li>';
                }).join('') + '</ul>' : '<div class="empty-state compact">' + icon('fa-mug-hot', 'empty-icon') + '<p class="empty-title">No pending task</p><p class="text-muted">New properties sent by the OIC will appear here.</p></div>') +
                '</div></div>' + P.activity(P.recentActivity(8, me), 'My recent activity') + '</div>',
            after: function () {
                const keys = ['PENDING', 'APPROVED', 'REJECTED', 'NOT_REQUIRED'];
                const tab = { PENDING: 'pending', APPROVED: 'approved', REJECTED: 'rejected', NOT_REQUIRED: 'closed' };
                P.pie('ch-mine', keys.map(P.label), keys.map(function (k) { return all.filter(function (x) { return x.view === k; }).length; }),
                    [C.amber, C.green, C.red, C.slate], true, function (i) { P.go('/role/tasks', { status: tab[keys[i]] }); });
                const sch = DB.all('schemes');
                P.bars('ch-scheme', sch.map(function (s) { return s.scheme_code; }), [
                    { label: 'Pending', data: sch.map(function (s) { return pending.filter(function (x) { return x.scheme.scheme_id === s.scheme_id; }).length; }), backgroundColor: C.amber },
                    { label: 'Responded', data: sch.map(function (s) { return all.filter(function (x) { return x.scheme.scheme_id === s.scheme_id && x.task_status !== 'PENDING'; }).length; }), backgroundColor: C.green }
                ], { stacked: true, onClick: function (i) { P.go('/role/tasks', { scheme_id: sch[i].scheme_id }); } });
                const buckets = { 'Overdue': 0, 'Due today': 0, '1–3 days': 0, '4+ days': 0 };
                pending.forEach(function (x) {
                    const d = Math.round((new Date(x.sla_due_date + 'T00:00:00') - new Date(t + 'T00:00:00')) / 864e5);
                    buckets[d < 0 ? 'Overdue' : d === 0 ? 'Due today' : d <= 3 ? '1–3 days' : '4+ days']++;
                });
                P.bars('ch-sla', Object.keys(buckets), [{ label: 'Tasks', data: Object.keys(buckets).map(function (k) { return buckets[k]; }), backgroundColor: [C.red, C.amber, C.gold, C.green] }]);
                const days = P.lastDays(14);
                P.lines('ch-trend', days.labels, [
                    { label: 'Received', data: P.countByDay(all, 'initiated_at', days.keys), color: C.navy },
                    { label: 'Responded by me', data: P.countByDay(all, 'responded_at', days.keys, function (x) { return x.responded_by === me; }), color: C.green }
                ]);
            }
        };
    });
})(window);
