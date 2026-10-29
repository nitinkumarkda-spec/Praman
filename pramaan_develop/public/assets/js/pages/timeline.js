/* ===== js/pages/timeline.js ===== */
/* =====================================================================
   PRAMAAN — Property Action Timeline: every action ever taken on a
   property (registered, edited, sent, each section's approve / reject with
   remark + attachments, round closed, re-sent …), built from the audit
   trail. Shown on OIC, Data Entry and Role User property pages.
   ===================================================================== */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, L = P.Lookups;

    const KIND = {
        PROPERTY_CREATED: ['data', 'fa-building-circle-check', 'info'],
        PROPERTY_UPDATED: ['data', 'fa-pen-to-square', 'info'],
        ROUND_INITIATED: ['verification', 'fa-paper-plane', 'navy'],
        REINITIATED: ['verification', 'fa-rotate', 'warning'],
        TASK_APPROVED: ['decision', 'fa-circle-check', 'success'],
        TASK_REJECTED: ['decision', 'fa-circle-xmark', 'danger'],
        TASK_SIMULATED_APPROVED: ['decision', 'fa-circle-check', 'success'],
        TASK_SIMULATED_REJECTED: ['decision', 'fa-circle-xmark', 'danger'],
        ROUND_CLOSED: ['verification', 'fa-flag-checkered', 'navy']
    };
    function kindOf(a) {
        return KIND[a.entity_type === 'PROPERTY' ? 'PROPERTY_' + a.action : a.action] || ['data', 'fa-circle-dot', 'info'];
    }
    function val(k, v) {
        if (v == null || v === '') { return 'Not set'; }
        if (k === 'sector_id') { const s = DB.find('sectors', v); return s ? s.sector_name : v; }
        if (k === 'ward_id') { return DB.wardName(v); }
        if (k === 'assembly_id') { return DB.assemblyName(v); }
        if (k === 'property_type') { return L.PROPERTY_TYPES[v] || v; }
        if (k === 'status') { return L.PROPERTY_STATUSES[v] || v; }
        return String(v);
    }
    function statusPill(from, to) {
        return '<div class="tl-status">' + (from ? P.status_badge(from) + ' ' + icon('fa-arrow-right-long') + ' ' : '') + P.status_badge(to) + '</div>';
    }

    /** One timeline entry from an audit row. */
    function entry(a) {
        const n = a.new_value || {};
        const o = a.old_value || {};
        const k = kindOf(a);
        const actor = DB.find('users', a.performed_by);
        const role = actor && actor.role_id ? DB.find('roles', actor.role_id) : null;
        let title = '', body = '';
        switch (a.entity_type === 'PROPERTY' ? 'PROPERTY_' + a.action : a.action) {
            case 'PROPERTY_CREATED':
                title = 'Property registered';
                body = '<p>Plot <strong>' + e(n.plot_no || '') + '</strong>' + (n.property_type ? ' · ' + e(val('property_type', n.property_type)) : '') +
                    (n.area_value ? ' · ' + P.fmt_num(n.area_value, 2) + ' ' + (n.area_unit === 'SQFT' ? 'sq ft' : 'sq m') : '') + '</p>' + statusPill(null, 'LISTED');
                break;
            case 'PROPERTY_UPDATED': {
                title = 'Property details updated';
                const keys = Object.keys(n).filter(function (x) { return x.charAt(0) !== '_' && JSON.stringify(o[x]) !== JSON.stringify(n[x]) && ['scheme_id', 'area_sqm'].indexOf(x) === -1; });
                body = keys.length ? '<table class="tl-change"><tbody>' + keys.map(function (x) {
                    return '<tr><th>' + e(P.label(x)) + '</th><td class="old">' + e(val(x, o[x])) + '</td><td>' + icon('fa-arrow-right-long') + '</td><td class="new">' + e(val(x, n[x])) + '</td></tr>';
                }).join('') + '</tbody></table>' : '<p>Details saved (no field changed).</p>';
                break;
            }
            case 'ROUND_INITIATED':
            case 'REINITIATED':
                title = (a.action === 'REINITIATED' ? 'Re-sent for verification' : 'Sent for verification') + ' — Round R' + n.round_no;
                body = '<p>Sent to <strong>' + (n.roles || []).length + '</strong> section(s): ' + e((n.roles || []).join(', ')) + '</p>' +
                    (n.reason ? '<blockquote class="tl-quote">' + icon('fa-quote-left') + ' ' + e(n.reason) + '</blockquote>' : '') +
                    statusPill(o.status || 'LISTED', 'UNDER_VERIFICATION');
                break;
            case 'TASK_APPROVED':
            case 'TASK_REJECTED':
            case 'TASK_SIMULATED_APPROVED':
            case 'TASK_SIMULATED_REJECTED': {
                const ok = a.action.indexOf('APPROVED') !== -1;
                title = n.role + ' ' + (ok ? 'approved' : 'rejected') + (a.action.indexOf('SIMULATED') !== -1 ? ' (simulated)' : '') + (n.round_no ? ' · R' + n.round_no : '');
                body = (n.remarks ? '<blockquote class="tl-quote ' + (ok ? 'ok' : 'bad') + '">' + icon('fa-quote-left') + ' ' + e(n.remarks) + '</blockquote>' : '<p class="text-muted">No remarks provided.</p>') +
                    (n.attachments && n.attachments.length ? '<p class="tl-files-label">' + icon('fa-paperclip') + ' Attached documents</p><div class="tl-attach">' + P.attachmentChips(n.attachments) + '</div>' : '');
                break;
            }
            case 'ROUND_CLOSED':
                title = 'Round closed — ' + (n.result === 'READY' ? 'Ready for Auction' : 'Not Ready');
                body = statusPill('UNDER_VERIFICATION', n.result === 'READY' ? 'AUCTION_READY' : 'NOT_READY');
                break;
            default:
                title = e(P.label(a.action));
                body = '';
        }
        const who = actor ? e(actor.name) + (actor.designation ? ' <span class="text-muted">(' + e(actor.designation) + ')</span>' : (role ? ' <span class="text-muted">(' + e(role.role_name) + ')</span>' : '')) : 'System';
        return '<li class="tl-item tone-' + k[2] + '" data-area="' + k[0] + '"><span class="tl-dot">' + icon(k[1]) + '</span>' +
            '<div class="tl-card"><div class="tl-head"><strong>' + e(title) + '</strong><span class="tl-time">' + e(P.fmt_datetime(a.performed_at)) + '</span></div>' +
            '<div class="tl-who">' + icon('fa-user') + ' ' + who + '</div>' +
            '<div class="tl-body">' + body + '</div>' +
            '</div></li>';
    }

    P.propertyTimeline = function (propertyId, heading) {
        const rows = DB.where('audit_logs', { property_id: propertyId }).sort(function (a, b) { return b.log_id - a.log_id; });
        const counts = { all: rows.length, decision: 0, verification: 0, data: 0 };
        rows.forEach(function (r) { const k = kindOf(r); counts[k[0]] = (counts[k[0]] || 0) + 1; });
        return '<div class="card" id="timeline"><div class="card-header"><h3 class="card-title">' + icon('fa-timeline') + ' ' + e(heading || 'Property action timeline') + '</h3>' +
            '<span class="badge badge-muted">' + rows.length + ' events</span></div><div class="card-body">' +
            (rows.length ? '<div class="tl-filters">' +
                '<button type="button" class="tl-filter active" data-filter="all">All <span>' + counts.all + '</span></button>' +
                (counts.decision ? '<button type="button" class="tl-filter" data-filter="decision">' + icon('fa-circle-check') + ' Decisions <span>' + counts.decision + '</span></button>' : '') +
                (counts.verification ? '<button type="button" class="tl-filter" data-filter="verification">' + icon('fa-paper-plane') + ' Rounds <span>' + counts.verification + '</span></button>' : '') +
                (counts.data ? '<button type="button" class="tl-filter" data-filter="data">' + icon('fa-pen-to-square') + ' Edits <span>' + counts.data + '</span></button>' : '') +
                '</div><ul class="tl-list">' + rows.map(entry).join('') + '</ul>'
                : '<div class="empty-state"><p class="text-muted">No actions recorded on this property yet.</p></div>') +
            '</div></div>';
    };

    P.initPage = (function (orig) {
        return function (root) {
            if (orig) { orig(root); }
            const filters = root.querySelectorAll('.tl-filter');
            if (!filters.length) { return; }
            filters.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const f = btn.getAttribute('data-filter');
                    filters.forEach(function (b) { b.classList.toggle('active', b === btn); });
                    root.querySelectorAll('.tl-item').forEach(function (it) {
                        it.hidden = f !== 'all' && it.getAttribute('data-area') !== f;
                    });
                });
            });
        };
    })(P.initPage);

})(window, document);
