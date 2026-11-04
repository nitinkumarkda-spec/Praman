/* =====================================================================
   PRAMAAN — client data store.
   Holds the rows the server sent for the signed-in user (GET /api/state,
   or the JSON embedded in the page). Screens read it with the helpers
   below; every change goes to the server (Praman.api) and the store is
   replaced with the fresh state from the response. Nothing is written
   here directly and nothing is kept in localStorage.
   ===================================================================== */
(function (window, document) {
    'use strict';

    /* ---------- Table registry: key => primary key (same tables as database/schema.sql) ---------- */
    const TABLES = {
        roles: 'role_id',
        users: 'user_id',
        schemes: 'scheme_id',
        sectors: 'sector_id',
        scheme_users: 'map_id',
        properties: 'property_id',
        rounds: 'round_id',
        tasks: 'task_id',
        task_assignees: 'assignee_id',
        audit_logs: 'log_id',
        login_attempts: 'attempt_id',
        notifications: 'notification_id',
        plot_types: 'plot_type_id',
        units: 'unit_id',
        statuses: 'status_id',
        areas: 'area_id',
        attachments: 'attachment_id'
    };

    let data = {};
    let index = {};
    let session = null;
    let app = { settings: {}, lookups: {}, slides: [] };
    let csrf = '';
    let skewMs = 0; // server clock - browser clock

    /* ---------- date helpers (server time, MySQL-like strings) ---------- */
    function pad(n) { return String(n).padStart(2, '0'); }
    function fmt(d) {
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + ' ' +
            pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds());
    }
    function base() { return new Date(Date.now() + skewMs); }
    function now(offsetMinutes) {
        const d = base();
        if (offsetMinutes) { d.setMinutes(d.getMinutes() + offsetMinutes); }
        return fmt(d);
    }
    function today(offsetDays) {
        const d = base();
        if (offsetDays) { d.setDate(d.getDate() + offsetDays); }
        return fmt(d).slice(0, 10);
    }
    function parse(v) {
        const m = String(v || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        return m ? new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : null;
    }

    /* ---------- loading ---------- */
    function load(state) {
        const t = (state && state.tables) || {};
        data = {};
        index = {};
        Object.keys(TABLES).forEach(function (k) { data[k] = Array.isArray(t[k]) ? t[k] : []; });
        session = state ? state.session || null : null;
        if (state && state.app) { app = state.app; }
        if (state && state.csrf) { csrf = state.csrf; }
        const srv = state && parse(state.now);
        if (srv) { skewMs = srv.getTime() - Date.now(); }
    }
    function table(key) {
        if (!Object.prototype.hasOwnProperty.call(TABLES, key)) { throw new Error('Unknown table key: ' + key); }
        return data[key] || [];
    }
    function byId(t) {
        if (!index[t]) {
            const pk = TABLES[t];
            const m = new Map();
            table(t).forEach(function (r) { m.set(r[pk], r); });
            index[t] = m;
        }
        return index[t];
    }

    /* ---------- generic read helpers ---------- */
    function matcher(where) {
        if (!where) { return function () { return true; }; }
        if (typeof where === 'function') { return where; }
        return function (row) {
            return Object.keys(where).every(function (k) {
                const v = where[k];
                if (Array.isArray(v)) { return v.indexOf(row[k]) !== -1; }
                return row[k] === v;
            });
        };
    }
    const DB = {
        TABLES: TABLES,
        now: now,
        today: today,
        all: function (t) { return table(t).slice(); },
        find: function (t, id) { return byId(t).get(Number(id)) || null; },
        where: function (t, where) { return table(t).filter(matcher(where)); },
        first: function (t, where) { return table(t).find(matcher(where)) || null; },
        count: function (t, where) { return table(t).filter(matcher(where)).length; },
        exists: function (t, where) { return table(t).some(matcher(where)); },
        /** Case-insensitive uniqueness check (the server checks again). */
        valueExists: function (t, col, value, exceptId) {
            const pk = TABLES[t];
            const v = String(value).trim().toLowerCase();
            return table(t).some(function (r) {
                return String(r[col] || '').toLowerCase() === v && (exceptId == null || r[pk] !== Number(exceptId));
            });
        },
        load: load,
        session: function () { return session; },
        /** Settings, code lists, landing slides … (App Settings screen + config on the server). */
        app: function () { return app; },
        setting: function (key, fallback) { const v = (app.settings || {})[key]; return v === undefined || v === null ? fallback : v; },
        lookup: function (name) { return (app.lookups || {})[name] || {}; },
        csrf: function () { return csrf; },
        raw: function () { return data; }
    };

    DB.panelUsers = function (panel) {
        return DB.where('users', { panel: panel, is_active: 1 }).map(function (u) { return u.user_id; });
    };

    /* =================================================================
       Verification rules used for display (the server enforces them)
       ================================================================= */
    /** Fixed ids of the workflow's statuses in the Status Master (same as App\Codes\PropertyStatus). */
    const ST = DB.ST = { LISTED: 1, UNDER_VERIFICATION: 2, AUCTION_READY: 3, NOT_READY: 4, EXPIRED: 5, SENT_FOR_AUCTION: 6, SOLD: 7, WITHHELD: 8, ARCHIVED: 9 };
    const SENDABLE = [ST.LISTED, ST.NOT_READY, ST.EXPIRED];
    DB.Verification = {
        SENDABLE: SENDABLE,
        get REVERIFY_ALERT_DAYS() { return Number(DB.setting('reverify_alert_days', 15)); },
        /** Last date of the re-verification window: Auction Ready valid till this date or earlier is "due". */
        horizon: function () { return today(DB.Verification.REVERIFY_ALERT_DAYS); },
        isDue: function (p) {
            return p.status_id === ST.AUCTION_READY && !!p.valid_till && p.valid_till <= DB.Verification.horizon();
        },
        /** Can OIC start a new round now? Listed / Not Ready / Expired, or Auction Ready inside the window. */
        canSend: function (p) { return SENDABLE.indexOf(p.status_id) !== -1 || DB.Verification.isDue(p); }
    };

    DB.areaName = function (id) {
        const a = DB.find('areas', id);
        return a ? a.area_name : '—';
    };

    /* =================================================================
       Property / scheme helpers
       ================================================================= */
    DB.Property = {
        missingMandatoryRoles: function (schemeId) {
            return DB.where('roles', function (r) { return r.is_active && r.is_mandatory; })
                .sort(function (a, b) { return a.sort_order - b.sort_order; })
                .filter(function (r) {
                    return !DB.where('scheme_users', { scheme_id: schemeId, role_id: r.role_id, is_active: 1 }).some(function (m) {
                        const u = DB.find('users', m.user_id);
                        return u && u.is_active && u.panel === 'ROLE_USER' && u.role_id === r.role_id;
                    });
                }).map(function (r) { return r.role_name; });
        }
    };

    DB.Scheme = {
        sectors: function (schemeId, activeOnly) {
            return DB.where('sectors', function (s) {
                return s.scheme_id === Number(schemeId) && (!activeOnly || s.is_active === 1);
            }).sort(function (a, b) { return a.sort_order - b.sort_order; });
        },
        /** Active schemes (eligible for property entry; a sector is optional). */
        eligible: function () {
            return DB.where('schemes', { is_active: 1 })
                .sort(function (a, b) { return a.scheme_name.localeCompare(b.scheme_name); });
        },
        /** role_id => user_ids currently mapped */
        mapping: function (schemeId) {
            const out = {};
            DB.where('scheme_users', { scheme_id: Number(schemeId), is_active: 1 }).forEach(function (m) {
                (out[m.role_id] = out[m.role_id] || []).push(m.user_id);
            });
            return out;
        },
        /** Active roles with their active role users — the mapping form options. */
        roleUserOptions: function () {
            return DB.where('roles', { is_active: 1 }).sort(function (a, b) { return a.sort_order - b.sort_order; }).map(function (r) {
                return {
                    role: r,
                    users: DB.where('users', { is_active: 1, panel: 'ROLE_USER', role_id: r.role_id })
                        .sort(function (a, b) { return a.name.localeCompare(b.name); })
                };
            });
        }
    };

    /* ---------- first load: the state embedded by the server ---------- */
    let initial = null;
    try {
        const el = document.getElementById('praman-state');
        initial = el ? JSON.parse(el.textContent || 'null') : null;
    } catch (err) { initial = null; }
    load(initial);
    DB.initialFlashes = (initial && initial.flashes) || [];

    window.PramanDB = DB;
})(window, document);
