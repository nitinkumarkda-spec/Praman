/* ===== js/pages/role.js ===== */
/* PRAMAAN — Role User panel (app/Views/roleuser/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'ROLE_USER';

    /**
     * Tasks the user is assigned to (mirrors App\Models\Task).
     * view_status = NOT_REQUIRED when the task is still pending but the round was already closed.
     * A task answered by another assignee of the same section is hidden.
     */
    function tasksFor(userId) {
        const mine = DB.where('task_assignees', { user_id: userId }).map(function (a) { return a.task_id; });
        return DB.where('tasks', function (t) {
            return mine.indexOf(t.task_id) !== -1 && (t.task_status === 'PENDING' || t.responded_by === userId);
        }).map(enrich);
    }
    function enrich(t) {
        const vr = DB.find('rounds', t.round_id);
        const p = DB.find('properties', vr.property_id);
        const sc = DB.find('schemes', p.scheme_id);
        const se = DB.find('sectors', p.sector_id);
        const r = DB.find('roles', t.role_id);
        const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
        const iu = DB.find('users', vr.initiated_by);
        return Object.assign({}, t, {
            view_status: t.task_status === 'PENDING' && vr.round_status !== 'OPEN' ? 'NOT_REQUIRED' : t.task_status,
            round_no: vr.round_no, round_status: vr.round_status, initiated_at: vr.initiated_at, resend_reason: vr.resend_reason,
            property_id: p.property_id, property_code: p.property_code, plot_no: p.plot_no, property_type: p.property_type,
            area_value: p.area_value, area_unit: p.area_unit, area_sqm: p.area_sqm, property_remarks: p.remarks,
            scheme_name: sc.scheme_name, scheme_code: sc.scheme_code, scheme_type: sc.scheme_type, sector_name: se.sector_name,
            area: sc ? DB.areaName(sc.area_id) : '—',
            role_name: r.role_name, role_code: r.role_code, responded_by_name: ru ? ru.name : null,
            initiated_by_name: iu ? iu.name : '', initiated_by_designation: iu ? iu.designation : ''
        });
    }
    const isPendingOpen = function (t) { return t.view_status === 'PENDING'; };

    /* ================= Engineering Cell: site measurement + encroachment (filled before Approve / Reject) ================= */
    const SITE_ROLE = 'ENGINEERING_CELL';
    const MAX_WORDS = 100;
    function wordCount(s) { return String(s || '').trim().split(/\s+/).filter(Boolean).length; }
    function inUnit(v, from, to) { return from === to ? v : (to === 'SQM' ? v * L.SQFT_TO_SQM : v / L.SQFT_TO_SQM); }
    /** Actual vs recorded area, both expressed in the recorded unit. */
    function variance(value, unit, recValue, recUnit) {
        const actual = Math.round(inUnit(value, unit, recUnit) * 100) / 100;
        const diff = Math.round((actual - recValue) * 100) / 100;
        return {
            actual: actual, diff: Math.abs(diff), unit: recUnit,
            pct: recValue ? Math.round(Math.abs(diff) / recValue * 10000) / 100 : 0,
            variance: Math.abs(diff) < 0.01 ? 'SAME' : (diff < 0 ? 'LESS' : 'MORE')
        };
    }
    function varianceHtml(r) {
        if (r.variance === 'SAME') { return icon('fa-circle-check') + ' <span>Actual area <strong>matches</strong> the recorded area.</span>'; }
        return icon(r.variance === 'LESS' ? 'fa-arrow-trend-down' : 'fa-arrow-trend-up') + ' <span>Actual area is <strong>' + P.fmt_num(r.diff, 2) + ' ' +
            (r.unit === 'SQFT' ? 'sq ft' : 'sq m') + ' (' + P.fmt_num(r.pct, 2) + '%) ' + (r.variance === 'LESS' ? 'less' : 'more') + '</strong> than recorded.</span>';
    }
    function siteCheckHtml(task) {
        const unit = function (u) { return u === 'SQFT' ? 'sq ft' : 'sq m'; };
        return '<div class="site-check" data-site-check data-recorded-value="' + task.area_value + '" data-recorded-unit="' + e(task.area_unit) + '">' +
            '<p class="site-step"><span class="step-no">1</span> Actual site measurement</p>' +
            '<div class="site-recorded">' + icon('fa-file-lines') + ' <span>Recorded area (property register): <strong>' + P.fmt_num(task.area_value, 2) + ' ' + unit(task.area_unit) + '</strong></span></div>' +
            '<div class="form-grid"><div class="form-group"><label class="form-label" for="site_area_value">Actual measured area <span class="required">*</span></label>' +
            '<input type="text" id="site_area_value" name="site_area_value" class="form-control" inputmode="decimal" maxlength="13" placeholder="e.g. 975.50" data-rules="required|decimal:2|positive" data-label="Actual measured area" data-site-area>' +
            '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="site_area_unit">Unit <span class="required">*</span></label>' +
            '<select id="site_area_unit" name="site_area_unit" class="form-select" data-rules="required" data-label="Unit" data-site-unit>' +
            ['SQM', 'SQFT'].map(function (u) { return '<option value="' + u + '"' + (u === task.area_unit ? ' selected' : '') + '>' + e(L.AREA_UNITS[u]) + ' (' + unit(u) + ')</option>'; }).join('') +
            '</select><div class="invalid-feedback"></div></div></div>' +
            '<div class="site-variance" data-site-variance aria-live="polite"></div>' +
            '<p class="site-step"><span class="step-no">2</span> Encroachment</p>' +
            '<div class="form-group"><p class="form-label">Is there any encroachment on the plot? <span class="required">*</span></p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="encroachment" value="YES" data-rules="required" data-label="Yes or No for encroachment"> <span><strong>Yes</strong></span></label>' +
            '<label class="check"><input type="radio" name="encroachment" value="NO"> <span><strong>No</strong></span></label></div><div class="invalid-feedback"></div></div>' +
            '<div class="form-group" data-encroach-box hidden><label class="form-label" for="encroachment_details">Describe the encroachment <span class="required">*</span> <small class="text-muted">(max ' + MAX_WORDS + ' words)</small></label>' +
            '<textarea id="encroachment_details" name="encroachment_details" class="form-control" rows="3" maxlength="1000" placeholder="e.g. Temporary tin shed of about 8 sq m on the front side; boundary wall extended 2 ft into the road" data-rules="max:1000|text" data-label="Encroachment description"></textarea>' +
            '<div class="site-words"><span data-word-count>0</span> / ' + MAX_WORDS + ' words</div><div class="invalid-feedback"></div></div>' +
            '<p class="site-step"><span class="step-no">3</span> Decision</p></div>';
    }
    function initSiteCheck(form) {
        const box = form.querySelector('[data-site-check]');
        const area = box.querySelector('[data-site-area]'), unit = box.querySelector('[data-site-unit]'), out = box.querySelector('[data-site-variance]');
        const details = box.querySelector('textarea[name="encroachment_details"]'), detailsBox = box.querySelector('[data-encroach-box]'), counter = box.querySelector('[data-word-count]');
        const recValue = Number(box.getAttribute('data-recorded-value')), recUnit = box.getAttribute('data-recorded-unit');
        function syncVariance() {
            const raw = area.value.trim(), v = Number(raw);
            out.className = 'site-variance';
            if (!raw || isNaN(v) || v <= 0) { out.innerHTML = icon('fa-circle-info') + ' <span>Enter the measured area to compare it with the recorded area.</span>'; return; }
            const r = variance(v, unit.value, recValue, recUnit);
            out.classList.add('is-' + r.variance.toLowerCase());
            out.innerHTML = varianceHtml(r);
        }
        function syncEncroach() {
            const c = form.querySelector('input[name="encroachment"]:checked');
            detailsBox.hidden = !(c && c.value === 'YES');
            const n = wordCount(details.value);
            counter.textContent = n;
            counter.parentNode.classList.toggle('is-over', n > MAX_WORDS);
        }
        area.addEventListener('input', syncVariance);
        unit.addEventListener('change', syncVariance);
        details.addEventListener('input', syncEncroach);
        form.addEventListener('change', function (ev) { if (ev.target.name === 'encroachment') { syncEncroach(); } });
        if (P.Validation) {
            P.Validation.addCheck(form, function () {
                const c = form.querySelector('input[name="encroachment"]:checked');
                if (!c || c.value !== 'YES') { return null; }
                const n = wordCount(details.value);
                if (!n) { return { field: details, message: 'Describe the encroachment (required when the answer is "Yes").' }; }
                if (n > MAX_WORDS) { return { field: details, message: 'Keep the description within ' + MAX_WORDS + ' words (now ' + n + ').' }; }
                return null;
            });
        }
        syncVariance();
        syncEncroach();
    }
    /** Read-only rows shown on the task after the Engineering response. */
    function siteSummaryRows(si) {
        const r = { variance: si.variance, diff: si.difference, pct: si.difference_pct, unit: si.recorded_unit };
        return '<dt>Actual area</dt><dd>' + P.fmt_num(si.area_value, 2) + ' ' + (si.area_unit === 'SQFT' ? 'sq ft' : 'sq m') +
            '<span class="site-variance-inline is-' + si.variance.toLowerCase() + '">' + varianceHtml(r) + '</span></dd>' +
            '<dt>Encroachment</dt><dd>' + (si.encroachment === 'YES' ? '<span class="badge badge-danger">Yes</span> ' + e(si.encroachment_details || '') : '<span class="badge badge-success">No</span>') + '</dd>';
    }

    /* ================= Sale Cell: vacancy check (filled before the decision) ================= */
    const SALE_ROLE = 'SALE_CELL';
    const ALLOTMENT_MODES = L.ALLOTMENT_MODES; // from the server (app/Core/Lookups.php)
    const NOT_VACANT_MSG = 'A property that is not vacant cannot get “No Objection” — choose “Objection”.';
    function saleCheckHtml() {
        return '<div class="site-check" data-sale-check>' +
            '<p class="site-step"><span class="step-no">1</span> Vacancy status</p>' +
            '<div class="form-group"><p class="form-label">Is the property vacant? <span class="required">*</span></p>' +
            '<div class="radio-row"><label class="check"><input type="radio" name="vacant" value="YES" data-rules="required" data-label="Yes or No for vacancy"> <span><strong>Yes</strong>, vacant</span></label>' +
            '<label class="check"><input type="radio" name="vacant" value="NO"> <span><strong>No</strong>, already allotted</span></label></div><div class="invalid-feedback"></div></div>' +
            '<div class="sale-result is-ready" data-sale-ready hidden>' + icon('fa-circle-check') + ' <span><strong>Ready for auction</strong> — the property is vacant. “No Objection” is selected below.</span></div>' +
            '<div data-sale-allotment hidden><div class="sale-result is-allotted">' + icon('fa-circle-xmark') + ' <span><strong>Not ready for auction</strong> — the property is already allotted. Enter the allotment details; “Objection” is selected below.</span></div>' +
            '<div class="form-grid form-grid-3">' +
            '<div class="form-group"><label class="form-label" for="allotment_mode">Mode of allotment <span class="required">*</span></label>' +
            '<select id="allotment_mode" name="allotment_mode" class="form-select" data-rules="required" data-label="the mode of allotment" disabled data-sale-field>' +
            '<option value="">Select mode</option>' + Object.keys(ALLOTMENT_MODES).map(function (k) { return '<option value="' + k + '">' + e(ALLOTMENT_MODES[k]) + '</option>'; }).join('') +
            '</select><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="allotment_date">Date of allotment <span class="required">*</span></label>' +
            '<input type="date" id="allotment_date" name="allotment_date" class="form-control" max="' + DB.today() + '" data-rules="required|date|notfuture" data-label="Date of allotment" disabled data-sale-field>' +
            '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="allottee_name">Name of allottee <span class="required">*</span></label>' +
            '<input type="text" id="allottee_name" name="allottee_name" class="form-control" maxlength="100" placeholder="e.g. Ramesh Kumar Sharma" data-rules="required|min:3|max:100|name" data-label="Name of allottee" disabled data-sale-field>' +
            '<div class="invalid-feedback"></div></div></div></div>' +
            '<p class="site-step"><span class="step-no">2</span> Decision</p></div>';
    }
    function allotmentRemark(form) {
        const mode = form.querySelector('[name="allotment_mode"]'), date = form.querySelector('[name="allotment_date"]').value, name = form.querySelector('[name="allottee_name"]').value.trim();
        return 'Not vacant — already allotted' + (mode.value ? ' by ' + ALLOTMENT_MODES[mode.value].toLowerCase() : '') +
            (date ? ' on ' + P.fmt_date(date) : '') + (name ? ' to ' + name : '') + '.';
    }
    function initSaleCheck(form) {
        const box = form.querySelector('[data-sale-check]');
        const ready = box.querySelector('[data-sale-ready]'), allot = box.querySelector('[data-sale-allotment]');
        const fields = box.querySelectorAll('[data-sale-field]'), remark = form.querySelector('textarea[name="remarks"]');
        let autoText = '';
        function pickDecision(value) {
            const r = form.querySelector('input[name="decision"][value="' + value + '"]');
            if (r && !r.checked) { r.checked = true; r.dispatchEvent(new Event('change', { bubbles: true })); }
        }
        function syncRemark(notVacant) {
            const cur = remark.value.trim();
            if (notVacant && (!cur || cur === autoText)) { autoText = allotmentRemark(form); remark.value = autoText; }
            else if (!notVacant && cur && cur === autoText) { remark.value = ''; autoText = ''; }
        }
        function sync(fromUser) {
            const c = form.querySelector('input[name="vacant"]:checked');
            const vacant = !!c && c.value === 'YES', notVacant = !!c && c.value === 'NO';
            ready.hidden = !vacant;
            allot.hidden = !notVacant;
            fields.forEach(function (el) { el.disabled = !notVacant; if (!notVacant && P.Validation) { P.Validation.setError(el, null); } });
            if (fromUser && c) { pickDecision(vacant ? 'APPROVED' : 'REJECTED'); }
            syncRemark(notVacant);
        }
        form.addEventListener('change', function (ev) {
            if (ev.target.name === 'vacant') { sync(true); }
            else if (ev.target.hasAttribute && ev.target.hasAttribute('data-sale-field')) { syncRemark(true); }
        });
        box.querySelector('[name="allottee_name"]').addEventListener('input', function () { syncRemark(true); });
        if (P.Validation) {
            P.Validation.addCheck(form, function () {
                const c = form.querySelector('input[name="vacant"]:checked');
                if (!c || c.value !== 'NO') { return null; }
                const date = form.querySelector('[name="allotment_date"]');
                if (date.value && date.value > DB.today()) { return { field: date, message: 'Date of allotment cannot be in the future.' }; }
                const d = form.querySelector('input[name="decision"]:checked');
                if (d && d.value === 'APPROVED') { return { field: d, message: NOT_VACANT_MSG }; }
                return null;
            });
        }
        sync(false);
    }
    /** Read-only rows shown on the task after the Sale response. */
    function saleSummaryRows(sc) {
        return '<dt>Vacant</dt><dd>' + (sc.vacant === 'YES' ? '<span class="badge badge-success">Yes</span> Ready for auction' : '<span class="badge badge-danger">No</span> Already allotted') + '</dd>' +
            (sc.vacant === 'NO' ? '<dt>Mode of allotment</dt><dd>' + e(ALLOTMENT_MODES[sc.allotment_mode] || sc.allotment_mode) + '</dd>' +
                '<dt>Date of allotment</dt><dd>' + e(P.fmt_date(sc.allotment_date)) + '</dd><dt>Allottee</dt><dd>' + e(sc.allottee_name) + '</dd>' : '');
    }

    /* ================= My Tasks ================= */
    const TABS = [['', 'All', 'fa-list'], ['pending', 'Pending', 'fa-hourglass-half'], ['overdue', 'Overdue', 'fa-stopwatch'],
        ['approved', 'Approved', 'fa-check'], ['rejected', 'Rejected', 'fa-xmark'], ['closed', 'Not required', 'fa-minus']];

    P.route('/role/tasks', PANEL, function (ctx) {
        const q = ctx.query;
        const today = DB.today();
        const cur = q.status || '';
        let rows = tasksFor(ctx.user.user_id);
        const f = {
            pending: isPendingOpen,
            overdue: function (t) { return isPendingOpen(t) && t.sla_due_date < today; },
            approved: function (t) { return t.task_status === 'APPROVED'; },
            rejected: function (t) { return t.task_status === 'REJECTED'; },
            closed: function (t) { return t.view_status === 'NOT_REQUIRED'; }
        };
        if (f[cur]) { rows = rows.filter(f[cur]); }
        if (q.scheme_id) { rows = rows.filter(function (t) { return String(DB.find('properties', t.property_id).scheme_id) === q.scheme_id; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'due', dir: 'asc', filters: ['status', 'scheme_id'],
            search: function (t) { return [t.plot_no, t.property_code, t.scheme_name, t.sector_name]; },
            sortable: {
                due: function (t) { return t.sla_due_date; }, sent: function (t) { return t.initiated_at; }, plot: function (t) { return t.plot_no; },
                scheme: function (t) { return t.scheme_name; }, status: function (t) { return t.view_status; }
            }
        });
        const schemes = {};
        DB.all('schemes').forEach(function (s) { schemes[s.scheme_id] = s.scheme_name + ' (' + s.scheme_code + ')'; });
        const filters = '<select name="scheme_id" class="form-select" aria-label="Scheme" data-auto-submit>' + L.options(schemes, q.scheme_id, 'All schemes') + '</select>' +
            (cur ? '<input type="hidden" name="status" value="' + e(cur) + '">' : '');
        return {
            title: 'My Tasks',
            html: P.pageHeader(icon('fa-clipboard-list') + ' My Tasks', 'Properties assigned to you for verification. Open a task to give No Objection or Objection.') +
                '<nav class="tabs" aria-label="Task status">' + TABS.map(function (t) {
                    return '<a href="' + P.url('/role/tasks', { status: t[0], scheme_id: q.scheme_id || '' }) + '" class="tab' + (cur === t[0] ? ' active' : '') + '"' + (cur === t[0] ? ' aria-current="page"' : '') + '>' +
                        icon(t[2]) + ' ' + t[1] + '</a>';
                }).join('') + '</nav>' +
                '<div class="card">' + P.list_toolbar(p, 'Search plot no., property code, scheme, sector…', filters) +
                '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Property</th>' + P.th_sort(p, 'scheme', 'Scheme / Sector') + P.th_sort(p, 'plot', 'Plot') +
                '<th>Area</th><th>Round</th>' + P.th_sort(p, 'sent', 'Received') + P.th_sort(p, 'due', 'Due (SLA)') + P.th_sort(p, 'status', 'Status') + '<th class="text-right">Action</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (t) {
                    const pending = isPendingOpen(t);
                    const late = pending && t.sla_due_date < today;
                    return '<tr class="' + (t.view_status === 'APPROVED' ? 'row-success' : (t.view_status === 'REJECTED' ? 'row-danger' : '')) + '">' +
                        '<td data-label="Property"><span class="mono">' + e(t.property_code) + '</span></td>' +
                        '<td data-label="Scheme / Sector">' + e(t.scheme_name) + '<span class="cell-sub">' + e(t.sector_name) + '</span></td>' +
                        '<td data-label="Plot"><strong>' + e(t.plot_no) + '</strong><span class="cell-sub">' + e(L.PROPERTY_TYPES[t.property_type]) + '</span></td>' +
                        '<td data-label="Area" class="nowrap">' + P.fmt_num(t.area_sqm, 2) + ' sq m</td><td data-label="Round">R' + t.round_no + '</td>' +
                        '<td data-label="Received">' + e(P.fmt_date(t.initiated_at)) + '</td>' +
                        '<td data-label="Due"><span class="' + (late ? 'text-danger' : '') + '">' + e(P.fmt_date(t.sla_due_date)) + '</span>' +
                        (late ? ' <span class="badge badge-danger">' + icon('fa-stopwatch') + ' Overdue</span>' : '') + '</td>' +
                        '<td data-label="Status">' + P.status_badge(t.view_status) +
                        (!pending && t.responded_at ? '<span class="cell-sub">' + e((t.responded_by_name || '') + ' · ' + P.fmt_date(t.responded_at)) + '</span>' : '') + '</td>' +
                        '<td class="actions">' + (pending ? '<a href="#/role/tasks/' + t.task_id + '" class="btn btn-primary btn-sm">' + icon('fa-clipboard-check') + ' <span>Verify</span></a>'
                            : '<a href="#/role/tasks/' + t.task_id + '" class="btn btn-light btn-sm">' + icon('fa-eye') + ' <span>View</span></a>') + '</td></tr>';
                }).join('') : P.empty_state(9, p.hasFilters(), 'No task assigned to you yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    });

    /* ================= Task detail + decision ================= */
    P.route('/role/tasks/{id}', PANEL, function (ctx) {
        const me = ctx.user.user_id;
        const raw = DB.find('tasks', ctx.params.id);
        if (!raw || !DB.exists('task_assignees', { task_id: raw.task_id, user_id: me })) {
            return P.notFound('Task not found or not assigned to you.');
        }
        const task = enrich(raw);
        if (task.task_status !== 'PENDING' && task.responded_by !== me) {
            P.flash('info', 'Plot ' + task.plot_no + ' (' + task.scheme_name + ') was already ' + P.label(task.task_status).toLowerCase() + ' by ' + task.responded_by_name + ' of your section.');
            P.go('/role/tasks');
            return false;
        }
        task.assignees = DB.where('task_assignees', { task_id: task.task_id }).map(function (a) {
            const u = DB.find('users', a.user_id); return u.name + ' (' + u.designation + ')';
        }).sort().join(', ');
        const canRespond = task.view_status === 'PENDING';
        const late = canRespond && task.sla_due_date < DB.today();
        const history = DB.where('rounds', function (r) { return r.property_id === task.property_id && r.round_id !== task.round_id; })
            .sort(function (a, b) { return b.round_no - a.round_no; })
            .map(function (r) {
                const t = DB.first('tasks', { round_id: r.round_id, role_id: task.role_id });
                if (!t) { return null; }
                const ru = t.responded_by ? DB.find('users', t.responded_by) : null;
                return { task_id: t.task_id, round_no: r.round_no, task_status: t.task_status, remarks: t.remarks, responded_by_name: ru ? ru.name : null, responded_at: t.responded_at, result: r.result };
            }).filter(Boolean);

        let html = P.pageHeader(icon('fa-clipboard-check') + ' Plot ' + e(task.plot_no) + ' ' + P.status_badge(task.view_status),
            e(task.property_code + ' · ' + task.scheme_name + ' · ' + task.sector_name + ' · Round R' + task.round_no + ' · ' + task.role_name),
            '<a href="' + P.url('/role/tasks', { status: 'pending' }) + '" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to my tasks</span></a>');
        if (task.view_status === 'NOT_REQUIRED') {
            html += '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This round was already closed (another section rejected the property), so your response is no longer required. If the OIC re-sends it, you will get a new task.</span></div>';
        } else if (late) {
            html += '<div class="alert alert-warning">' + icon('fa-stopwatch', 'fa-shake') + '<span>SLA due date ' + e(P.fmt_date(task.sla_due_date)) + ' has passed. Please respond as soon as possible.</span></div>';
        }
        html += '<div class="grid-2"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-building') + ' Property details</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Property code</dt><dd class="mono">' + e(task.property_code) + '</dd><dt>Scheme</dt><dd>' + e(task.scheme_name + ' (' + task.scheme_code + ')') + '</dd>' +
            '<dt>Area</dt><dd>' + e(task.area) + '</dd>' +
            '<dt>Scheme type</dt><dd>' + e(L.SCHEME_TYPES[task.scheme_type]) + '</dd><dt>Sector</dt><dd>' + e(task.sector_name) + '</dd>' +
            '<dt>Plot no.</dt><dd><strong>' + e(task.plot_no) + '</strong></dd><dt>Plot type</dt><dd>' + e(L.PROPERTY_TYPES[task.property_type]) + '</dd>' +
            '<dt>Plot area</dt><dd>' + P.fmt_num(task.area_value, 2) + ' ' + e(L.AREA_UNITS[task.area_unit]) + (task.area_unit === 'SQFT' ? ' <span class="text-muted">(= ' + P.fmt_num(task.area_sqm, 2) + ' sq m)</span>' : '') + '</dd>' +
            (task.property_remarks ? '<dt>Remarks</dt><dd>' + e(task.property_remarks) + '</dd>' : '') + '</dl></div></div>' +
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-layer-group') + ' Verification task</h3></div><div class="card-body"><dl class="detail-list">' +
            '<dt>Section</dt><dd>' + e(task.role_name) + '</dd><dt>Round</dt><dd>R' + task.round_no + ' ' + P.status_badge(task.round_status) + '</dd>' +
            '<dt>Sent by</dt><dd>' + e(task.initiated_by_name + ' (' + task.initiated_by_designation + ')') + '</dd><dt>Received on</dt><dd>' + e(P.fmt_datetime(task.initiated_at)) + '</dd>' +
            '<dt>Due (SLA)</dt><dd class="' + (late ? 'text-danger' : '') + '">' + e(P.fmt_date(task.sla_due_date)) + '</dd>' +
            '<dt>Assigned to</dt><dd>' + e(task.assignees || '—') + ' <small class="text-muted">(any one may respond)</small></dd>' +
            (task.resend_reason ? '<dt>Re-verification reason</dt><dd>' + e(task.resend_reason) + '</dd>' : '') +
            (!canRespond && task.responded_at ? '<dt>Response</dt><dd>' + P.status_badge(task.task_status) + '</dd><dt>Responded by</dt><dd>' + e((task.responded_by_name || '') + ' · ' + P.fmt_datetime(task.responded_at)) + '</dd>' +
                (task.site_inspection ? siteSummaryRows(task.site_inspection) : '') + (task.sale_check ? saleSummaryRows(task.sale_check) : '') +
                '<dt>Remark</dt><dd>' + e(task.remarks || '—') + '</dd>' +
                '<dt>Attachments</dt><dd>' + (P.attachmentsFor({ task_id: task.task_id }).length ? P.attachmentChips(P.attachmentsFor({ task_id: task.task_id })) : '—') + '</dd>' : '') + '</dl></div></div></div>';
        if (canRespond) {
            const isSite = task.role_code === SITE_ROLE, isSale = task.role_code === SALE_ROLE;
            html += '<div class="card response-card" id="respond"><div class="card-header"><h3 class="card-title">' +
                (isSite ? icon('fa-ruler-combined') + ' Site inspection &amp; decision'
                    : isSale ? icon('fa-house-circle-check') + ' Vacancy check &amp; decision' : icon('fa-gavel') + ' Your decision') +
                '</h3><span class="text-muted small">Once submitted, the response cannot be changed.</span></div><div class="card-body">' +
                '<form method="post" data-action="task-respond" novalidate data-validate data-response-form data-confirm="Submit your decision? It cannot be changed afterwards." data-confirm-ok="Yes, submit">' +
                '<input type="hidden" name="id" value="' + task.task_id + '">' + (isSite ? siteCheckHtml(task) : '') + (isSale ? saleCheckHtml() : '') + '<div class="form-group"><p class="form-label">Does the ' + e(task.role_name) + ' clear this property for auction? <span class="required">*</span></p>' +
                '<div class="decision-row"><label class="decision decision-approve"><input type="radio" name="decision" value="APPROVED" data-rules="required" data-label="No Objection or Objection">' +
                '<span>' + icon('fa-circle-check') + ' <strong>No Objection</strong><small>Record is clear — fit for auction</small></span></label>' +
                '<label class="decision decision-reject"><input type="radio" name="decision" value="REJECTED"><span>' + icon('fa-circle-xmark') + ' <strong>Objection</strong><small>Issue found — reason required</small></span></label></div>' +
                '<div class="invalid-feedback"></div></div><div class="form-group"><label class="form-label" for="remarks">Remark <span class="required" data-remark-required hidden>* (reason for objection)</span></label>' +
                '<textarea id="remarks" name="remarks" class="form-control" rows="3" maxlength="1000" placeholder="e.g. Encroachment — temporary tin shed 8 sq m on the front side / Record clear, file no. 123" data-rules="max:1000|text" data-label="Remark"></textarea><div class="invalid-feedback"></div></div>' +
                P.uploaderHtml('Supporting documents / photos') +
                '<button type="submit" class="btn btn-primary btn-lg" data-loading-text="Submitting…">' + icon('fa-paper-plane') + ' <span>Submit decision</span></button></form></div></div>';
        }
        html += '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-clock-rotate-left') + ' ' + e(task.role_name) + ' — earlier rounds of this property</h3></div>' +
            '<div class="table-wrap"><table class="table table-stack"><thead><tr><th>Round</th><th>Response</th><th>Remark</th><th>By / Date</th><th>Round result</th></tr></thead><tbody>' +
            (history.length ? history.map(function (h) {
                return '<tr><td data-label="Round">R' + h.round_no + '</td><td data-label="Response">' + P.status_badge(h.task_status) + '</td><td data-label="Remark">' + e(h.remarks || '—') + P.attachmentChips(P.attachmentsFor({ task_id: h.task_id })) + '</td>' +
                    '<td data-label="By / Date">' + e((h.responded_by_name || '—') + ' · ' + P.fmt_date(h.responded_at)) + '</td><td data-label="Result">' + (h.result ? P.status_badge(h.result) : '—') + '</td></tr>';
            }).join('') : P.empty_state(5, false, 'This is the first round for this property')) + '</tbody></table></div></div>';
        html += P.propertyTimeline(task.property_id, 'Property action timeline — every action on Plot ' + task.plot_no);
        return {
            title: 'Verify Plot ' + task.plot_no, html: html,
            after: function (root) {
                const f = root.querySelector('form[data-response-form]');
                if (f) {
                    P.initUploader(f);
                    if (f.querySelector('[data-site-check]')) { initSiteCheck(f); }
                    if (f.querySelector('[data-sale-check]')) { initSaleCheck(f); }
                }
            }
        };
    });

})(window);
