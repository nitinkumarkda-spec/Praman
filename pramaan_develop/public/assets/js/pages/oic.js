/* ===== js/pages/oic.js ===== */
/* PRAMAAN — OIC panel (app/Views/oic/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'OIC';

    /* ================= Properties – Verification ================= */
    const VF = {
        APPROVED: ['ok', 'fa-circle-check', 'Approved'], REJECTED: ['bad', 'fa-circle-xmark', 'Rejected'],
        NO_RESPONSE: ['late', 'fa-triangle-exclamation', 'No response'], PENDING: ['wait', 'fa-hourglass-half', 'Pending'],
        SKIPPED: ['skip', 'fa-minus', 'Not required (round closed)']
    };
    /** Verification column: latest round — how many sections responded and what each one said
     *  (bar = one segment per section; hover a segment / group for who, when and the remark). */
    function verificationCell(r) {
        const round = DB.where('rounds', { property_id: r.property_id }).sort(function (a, b) { return b.round_no - a.round_no; })[0];
        if (!round) {
            return '<div class="vf vf-empty"><span class="vf-none">' + icon('fa-hourglass-start') + ' Not sent yet</span><span class="vf-sub">' + r.role_count + ' sections mapped</span></div>';
        }
        const open = round.round_status === 'OPEN';
        const groups = { APPROVED: [], REJECTED: [], NO_RESPONSE: [], PENDING: [], SKIPPED: [] };
        const segs = DB.where('tasks', { round_id: round.round_id }).map(function (t) {
            return { t: t, role: DB.find('roles', t.role_id), user: t.responded_by ? DB.find('users', t.responded_by) : null };
        }).sort(function (a, b) { return a.role.sort_order - b.role.sort_order; }).map(function (x) {
            const key = x.t.task_status === 'PENDING' && !open ? 'SKIPPED' : (groups[x.t.task_status] ? x.t.task_status : 'PENDING');
            const tip = x.role.role_name + ' — ' + VF[key][2] + (x.user ? ' by ' + x.user.name + ' (' + x.user.designation + ')' : '') +
                (x.t.responded_at ? ' on ' + P.fmt_datetime(x.t.responded_at) : (key === 'PENDING' ? ' · due ' + P.fmt_date(x.t.sla_due_date) : '')) +
                (x.t.remarks ? ' · “' + x.t.remarks + '”' : '');
            groups[key].push({ name: x.role.role_name.replace(/\s+Cell$/i, ''), tip: tip });
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

    /** "N roles" badge → box with every mapped section (role) and its user(s) for that property. */
    function initials(name) {
        return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
    }
    function openRoleUsers(id, opener) {
        const prop = DB.find('properties', id);
        if (!prop) { return; }
        const sc = DB.find('schemes', prop.scheme_id) || {}, se = DB.find('sectors', prop.sector_id) || {};
        const groups = [];
        DB.Property.roleUsers(id).forEach(function (x) {
            let g = groups[groups.length - 1];
            if (!g || g.role_id !== x.role_id) {
                g = { role_id: x.role_id, role_name: x.role_name, role_active: x.role_active, users: [] };
                groups.push(g);
            }
            g.users.push(x);
        });
        const userCount = groups.reduce(function (n, g) { return n + g.users.length; }, 0);
        const body = groups.length ? '<ol class="ru-list">' + groups.map(function (g, i) {
            return '<li class="ru-item"><span class="ru-no">' + (i + 1) + '</span>' +
                '<div class="ru-role"><strong>' + e(g.role_name) + '</strong>' +
                (g.role_active ? '' : '<span class="badge badge-muted">role inactive</span>') + '</div>' +
                '<div class="ru-users">' + g.users.map(function (u) {
                    return '<div class="ru-user"><span class="ru-avatar" aria-hidden="true">' + e(initials(u.name)) + '</span>' +
                        '<span class="ru-name"><strong>' + e(u.name) + '</strong><small>' + e(u.designation) + '</small></span>' +
                        (u.user_active ? '' : '<span class="badge badge-danger">inactive</span>') + '</div>';
                }).join('') + '</div></li>';
        }).join('') + '</ol>' : '<p class="ru-empty">No role users assigned.</p>';

        const old = document.getElementById('ru-modal');
        if (old) { old.remove(); }
        const m = document.createElement('div');
        m.id = 'ru-modal';
        m.className = 'modal-backdrop ru-modal';
        m.innerHTML = '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="ru-modal-title"><div class="modal-header">' +
            '<span class="modal-icon">' + icon('fa-user-tie') + '</span><div><h2 class="modal-title" id="ru-modal-title">Assigned role users</h2>' +
            '<p>Plot <strong>' + e(prop.plot_no) + '</strong> · ' + e(sc.scheme_name) + ' / ' + e(se.sector_name) + ' · ' + groups.length + ' roles · ' + userCount + ' users</p></div>' +
            '<button type="button" class="btn btn-light btn-sm btn-icon-only ru-close" data-ru-close aria-label="Close">' + icon('fa-xmark') + '</button></div>' +
            '<div class="modal-body">' + body + '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-light" data-ru-close>' + icon('fa-xmark') + ' <span>Close</span></button></div></div>';
        document.body.appendChild(m);
        document.body.classList.add('modal-open');
        void m.offsetWidth; // commit the closed state so the open transition runs
        m.classList.add('is-open');
        m.querySelector('[data-ru-close]').focus();
        function close() {
            m.classList.remove('is-open');
            document.body.classList.remove('modal-open');
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('hashchange', close);
            window.setTimeout(function () { m.remove(); }, 180);
            if (opener && opener.isConnected) { opener.focus(); }
        }
        function onKey(ev) { if (ev.key === 'Escape') { close(); } }
        m.addEventListener('click', function (ev) { if (ev.target === m || ev.target.closest('[data-ru-close]')) { close(); } });
        document.addEventListener('keydown', onKey);
        window.addEventListener('hashchange', close);
    }
    document.addEventListener('click', function (ev) {
        const b = ev.target.closest('[data-role-users]');
        if (b) { openRoleUsers(Number(b.getAttribute('data-role-users')), b); }
    });

    P.route('/oic/properties', PANEL, function (ctx) {
        const q = ctx.query;
        const p = P.paginate(P.filterProperties(P.propertyRows(), q), ctx, {
            sort: 'created', dir: 'desc', filters: ['scheme_id', 'sector_id', 'assembly_id', 'type', 'status'],
            search: P.propertySearch, sortable: P.propertySortable
        });
        const sendable = DB.Verification.SENDABLE; // bulk send; Auction Ready due for re-verification is re-sent from its detail page
        const rows = p.items.map(function (r, i) {
            const can = sendable.indexOf(r.current_status) !== -1;
            const due = DB.Verification.isDue(r);
            const cls = due ? 'row-warning' : r.current_status === 'AUCTION_READY' ? 'row-success' : (['NOT_READY', 'EXPIRED'].indexOf(r.current_status) !== -1 ? 'row-danger' : '');
            return '<tr class="' + cls + '"><td class="cell-check" data-label="Select">' +
                (can ? '<input type="checkbox" name="ids[]" value="' + r.property_id + '" aria-label="Select plot ' + e(r.plot_no) + '" data-status="' + r.current_status + '">'
                    : '<input type="checkbox" disabled aria-label="Not sendable">') + '</td>' +
                '<td data-label="S.No" class="cell-no">' + (p.from() + i) + '</td>' +
                '<td data-label="Scheme">' + e(r.scheme_name) + '</td><td data-label="Sector">' + e(r.sector_name) + '</td>' +
                '<td data-label="Plot"><strong>' + e(r.plot_no) + '</strong></td><td data-label="Type">' + e(L.PROPERTY_TYPES[r.property_type]) + '</td>' +
                '<td data-label="Area"' + (r.area_unit === 'SQFT' ? ' title="= ' + P.fmt_num(r.area_sqm, 2) + ' sq m"' : '') + '>' +
                P.fmt_num(r.area_value, 2) + ' ' + (r.area_unit === 'SQFT' ? 'sq&nbsp;ft' : 'sq&nbsp;m') + '</td>' +
                '<td data-label="Role users"><button type="button" class="badge badge-muted ru-trigger" data-role-users="' + r.property_id + '" aria-haspopup="dialog" title="View assigned role users">' +
                icon('fa-user-tie') + ' ' + r.role_count + ' roles ' + icon('fa-chevron-down', 'ru-caret') + '</button></td>' +
                '<td data-label="Verification" class="cell-vf">' + verificationCell(r) + '</td>' +
                '<td data-label="Status">' + P.status_badge(r.current_status) +
                (r.round_no ? '<span class="cell-sub">R' + r.round_no + (r.current_status === 'UNDER_VERIFICATION' ? ' · ' + r.tasks_approved + '/' + r.tasks_total + ' approved' : '') + '</span>' : '') +
                (r.current_status === 'AUCTION_READY' || r.current_status === 'EXPIRED'
                    ? '<span class="cell-sub' + (due || r.current_status === 'EXPIRED' ? ' text-danger' : '') + '">' + (r.current_status === 'EXPIRED' ? 'Expired ' : 'Valid till ') +
                        e(P.fmt_date(r.valid_till)) + (due ? ' (' + P.dueText(r.valid_till) + ')' : '') + '</span>' : '') + '</td>' +
                '<td class="actions"><div class="btn-group btn-group-icons"><a href="#/oic/properties/' + r.property_id + '" class="btn btn-light btn-sm btn-icon-only" title="View status" aria-label="View status">' + icon('fa-eye') + '</a>' +
                '<a href="#/oic/properties/' + r.property_id + '#timeline" class="btn btn-light btn-sm btn-icon-only" title="Action timeline" aria-label="Action timeline">' + icon('fa-timeline') + '</a>' +
                (r.current_status === 'LISTED' ? '<button type="submit" form="send-' + r.property_id + '" class="btn btn-primary btn-sm btn-icon-only" title="Send for verification" data-loading-text="Sending…">' + icon('fa-paper-plane') + '<span class="sr-only">Send for verification</span></button>'
                    : r.current_status === 'NOT_READY' ? '<a href="#/oic/properties/' + r.property_id + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-send for verification" aria-label="Re-send for verification">' + icon('fa-rotate') + '</a>'
                    : r.current_status === 'EXPIRED' || due ? '<a href="#/oic/properties/' + r.property_id + '#resend" class="btn btn-warning btn-sm btn-icon-only" title="Re-verify (periodic)" aria-label="Re-verify">' + icon('fa-calendar-check') + '</a>' : '') +
                '</div></td></tr>';
        }).join('');
        const single = p.items.filter(function (r) { return r.current_status === 'LISTED'; }).map(function (r) {
            return '<form method="post" data-action="oic-send" id="send-' + r.property_id + '" hidden data-confirm="' +
                e('Send plot ' + r.plot_no + ' (' + r.scheme_name + ' / ' + r.sector_name + ') for verification to ' + r.role_count + ' role(s)?') + '" data-confirm-ok="Yes, send">' +
                '<input type="hidden" name="ids[]" value="' + r.property_id + '"></form>';
        }).join('');
        return {
            title: 'Properties – Verification',
            html: P.pageHeader(icon('fa-list-check') + ' Properties – Verification',
                'Send <strong>Listed</strong> properties for verification to all mapped role users. <strong>Not Ready</strong> ones can be re-sent (new round) with a reason; <strong>Validity Expired</strong> ones go for periodic re-verification.') +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., code, scheme, sector…', P.propertyFilters(q, { REVERIFY_DUE: '⚠ Re-verification due' })) +
                '<form method="post" data-action="oic-send" id="bulk-send-form" data-confirm="Send the selected properties for verification? A new round will be created for each and all mapped role users will get a task." data-confirm-ok="Yes, send" data-bulk-form data-validate novalidate>' +
                '<div class="bulk-bar"><span class="bulk-count">' + icon('fa-square-check') + ' <strong data-bulk-count="ids[]">0</strong> selected</span>' +
                '<input type="text" name="reason" class="form-control form-control-sm bulk-reason" maxlength="255" placeholder="Re-verification reason (required for Not Ready, optional for Validity Expired)" aria-label="Re-verification reason" data-rules="max:255|text" data-label="Re-verification reason" data-bulk-reason>' +
                '<button type="submit" class="btn btn-primary btn-sm" data-bulk-requires="ids[]" disabled data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send selected for verification</span></button></div>' +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr><th class="cell-check"><input type="checkbox" data-check-all="ids[]" aria-label="Select all sendable properties"></th>' +
                '<th class="cell-no">S.No</th>' + P.th_sort(p, 'scheme', 'Scheme') + P.th_sort(p, 'sector', 'Sector') + P.th_sort(p, 'plot', 'Plot') + P.th_sort(p, 'type', 'Type') +
                P.th_sort(p, 'area', 'Area') + '<th>Assigned role users</th><th class="cell-vf">Verification</th>' + P.th_sort(p, 'status', 'Status') + '<th class="text-right">Actions</th></tr></thead><tbody>' +
                (rows || P.empty_state(11, p.hasFilters(), 'No properties yet — Data Entry adds them')) +
                '</tbody></table></div></form>' + single + P.pagination(p) + '</div>'
        };
    });


    /* ================= Property status / rounds ================= */
    P.route('/oic/properties/{id}', PANEL, function (ctx) {
        const prop = P.propertyRows().find(function (r) { return r.property_id === ctx.params.id; });
        if (!prop) { return P.notFound('Property not found.'); }
        const roleUsers = DB.Property.roleUsers(prop.property_id);
        const rounds = DB.where('rounds', { property_id: prop.property_id }).sort(function (a, b) { return b.round_no - a.round_no; }).map(function (r) {
            const tasks = DB.where('tasks', { round_id: r.round_id }).map(function (t) {
                const role = DB.find('roles', t.role_id);
                const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
                const assignees = DB.where('task_assignees', { task_id: t.task_id }).map(function (a) {
                    const u = DB.find('users', a.user_id); return u.name + ' (' + u.designation + ')';
                }).sort().join(', ');
                return Object.assign({}, t, { role_name: role.role_name, sort_order: role.sort_order, is_mandatory: role.is_mandatory, responded_by_name: ru ? ru.name : null, assignees: assignees });
            }).sort(function (a, b) { return a.sort_order - b.sort_order; });
            const iu = DB.find('users', r.initiated_by);
            return Object.assign({}, r, { tasks: tasks, initiated_by_name: iu ? iu.name : '' });
        });
        const current = rounds[0] || null;
        const st = prop.current_status;
        let html = P.pageHeader(icon('fa-clipboard-check') + ' Plot ' + e(prop.plot_no) + ' ' + P.status_badge(st),
            e(prop.property_code + ' · ' + prop.scheme_name + ' · ' + prop.sector_name),
            '<a href="#/oic/properties" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>' +
            (st === 'LISTED' ? '<form method="post" data-action="oic-send" data-confirm="Send this property for verification to all mapped role users?" data-confirm-ok="Yes, send">' +
                '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><button type="submit" class="btn btn-primary" data-loading-text="Sending…">' + icon('fa-paper-plane') + ' <span>Send for verification</span></button></form>' : ''));

        const due = DB.Verification.isDue(prop);
        const till = prop.valid_till;
        if (due && current) {
            html += '<div class="alert alert-warning">' + icon('fa-calendar-day', 'fa-shake') + '<span><strong>Re-verification due.</strong> Approved in round R' + current.round_no +
                ', but the validity ends ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + '). Auction it before that date, or <a href="#/oic/properties/' + prop.property_id + '#resend">start re-verification</a> now.</span></div>';
        } else if (st === 'AUCTION_READY' && current) {
            html += '<div class="alert alert-success">' + icon('fa-circle-check') + '<span><strong>Ready for Auction.</strong> All roles approved in round R' + current.round_no + '. Valid till ' + e(P.fmt_date(till)) + ' (' + P.dueText(till) + ').</span></div>';
        } else if (st === 'EXPIRED') {
            html += '<div class="alert alert-error">' + icon('fa-calendar-xmark') + '<span><strong>Validity expired on ' + e(P.fmt_date(till)) + '.</strong> The approval of round R' + (current ? current.round_no : '—') +
                ' can no longer be used for an auction. <a href="#/oic/properties/' + prop.property_id + '#resend">Send it for re-verification</a> (new round).</span></div>';
        } else if (st === 'NOT_READY' && current) {
            const rej = current.tasks.filter(function (t) { return t.task_status === 'REJECTED' || t.task_status === 'NO_RESPONSE'; });
            html += '<div class="alert alert-error">' + icon('fa-circle-xmark', 'fa-beat') + '<span><strong>Not Ready — round R' + current.round_no + ' rejected.</strong> Reasons:<ul class="reason-list">' +
                rej.map(function (t) { return '<li><strong>' + e(t.role_name) + ':</strong> ' + e(t.remarks || 'No response') + ' <small>(' + e(t.responded_by_name || '') + ', ' + e(P.fmt_datetime(t.responded_at)) + ')</small></li>'; }).join('') +
                '</ul></span></div>';
        }
        html += '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Property code</dt><dd class="mono">' + e(prop.property_code) + '</dd><dt>Scheme</dt><dd>' + e(prop.scheme_name + ' (' + prop.scheme_code + ')') + '</dd>' +
            '<dt>Sector</dt><dd>' + e(prop.sector_name) + '</dd><dt>Plot no.</dt><dd>' + e(prop.plot_no) + '</dd>' +
            '<dt>Plot type</dt><dd>' + e(L.PROPERTY_TYPES[prop.property_type]) + '</dd>' +
            '<dt>Area</dt><dd>' + P.fmt_num(prop.area_value, 2) + ' ' + e(L.AREA_UNITS[prop.area_unit]) + (prop.area_unit === 'SQFT' ? ' <span class="text-muted">(= ' + P.fmt_num(prop.area_sqm, 2) + ' sq m)</span>' : '') + '</dd>' +
            '<dt>Created</dt><dd>' + e(P.fmt_datetime(prop.created_at) + ' · ' + prop.created_by_name) + '</dd>' +
            (prop.remarks ? '<dt>Remarks</dt><dd>' + e(prop.remarks) + '</dd>' : '') + '</dl></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-user-tie') + ' Assigned role users</h3><span class="text-muted small">Snapshot taken when the property was created</span></div>' +
            '<div class="card-body p-0"><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Role</th><th>User</th><th>Designation</th></tr></thead><tbody>' +
            (roleUsers.length ? roleUsers.map(function (ru) {
                return '<tr><td data-label="Role">' + e(ru.role_name) + (ru.role_active ? '' : ' <span class="badge badge-muted">role inactive</span>') + '</td>' +
                    '<td data-label="User">' + e(ru.name) + (ru.user_active ? '' : ' <span class="badge badge-danger">inactive</span>') + '</td><td data-label="Designation">' + e(ru.designation) + '</td></tr>';
            }).join('') : P.empty_state(3, false, 'No role users assigned')) + '</tbody></table></div></div></div></div>';

        if (st !== 'LISTED' && DB.Verification.canSend(prop)) {
            const need = st === 'NOT_READY';
            const nextNo = (current ? current.round_no : 0) + 1;
            html += '<div class="card" id="resend"><div class="card-header"><h3 class="card-title">' + icon('fa-rotate') + ' ' + (need ? 'Send again for verification' : 'Periodic re-verification') + ' (new round R' + nextNo + ')</h3>' +
                (need ? '' : '<span class="text-muted small">Every mapped section verifies the property again; it stays Under Verification until the round closes.</span>') + '</div><div class="card-body">' +
                '<form method="post" data-action="oic-send" id="resend-form" novalidate data-validate data-confirm="Start a new verification round? The old round stays in history." data-confirm-ok="Yes, ' + (need ? 're-send' : 're-verify') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="ids[]" value="' + prop.property_id + '"><div class="form-group"><label class="form-label" for="reason">Reason for re-verification ' + (need ? '<span class="required">*</span>' : '<small class="text-muted">(optional)</small>') + '</label>' +
                '<input type="text" id="reason" name="reason" class="form-control" maxlength="255" placeholder="' +
                e(need ? 'e.g. Encroachment removed, record corrected' : 'Default: Periodic re-verification — validity ' + (st === 'EXPIRED' ? 'expired' : 'ends') + ' on ' + P.fmt_date(till)) +
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
                        '<td data-label="Remark">' + e(t.remarks || '—') + '</td><td data-label="Attachments">' + (P.attachmentChips(P.attachmentsFor({ task_id: t.task_id })) || '—') + '</td><td data-label="Date">' +
                        (t.responded_at ? e(P.fmt_datetime(t.responded_at)) + '<span class="cell-sub">' + e(t.responded_by_name || '') + '</span>' : '<span class="text-muted">SLA ' + e(P.fmt_date(t.sla_due_date)) + '</span>') + '</td></tr>';
                }).join('') + '</tbody></table></div>' +
                (round.result === 'NOT_READY' && round.result_note ? '<div class="card-footer"><strong>Result:</strong> ' + e(round.result_note).replace(/\n/g, '<br>') + '</div>'
                    : round.result === 'READY' ? '<div class="card-footer"><strong>Result:</strong> All roles approved · valid ' + e(P.fmt_date(round.valid_from)) + ' – ' + e(P.fmt_date(round.valid_till)) + '</div>' : '') +
                '</details>';
        });
        html += P.propertyTimeline(prop.property_id);
        return { title: 'Property ' + prop.plot_no, html: html };
    });
})(window);
