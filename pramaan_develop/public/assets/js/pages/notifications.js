/* ===== js/pages/notifications.js ===== */
/* PRAMAAN — notifications: topbar bell + dropdown panel, full page, toasts, desktop alerts */
(function (window, document) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth;

    const TYPES = {
        TASK: ['Tasks', 'fa-clipboard-list'], VERIFICATION: ['Verification', 'fa-list-check'], PROPERTY: ['Property', 'fa-building'],
        ACCOUNT: ['Account', 'fa-user-shield'], SYSTEM: ['System', 'fa-bullhorn']
    };
    const PREF_KEY = 'praman_notif_prefs';
    const SEEN_KEY = 'praman_notif_seen_';

    function prefs() {
        try { return Object.assign({ desktop: false, sound: true }, JSON.parse(window.localStorage.getItem(PREF_KEY) || '{}')); } catch (err) { return { desktop: false, sound: true }; }
    }
    function savePrefs(p) { try { window.localStorage.setItem(PREF_KEY, JSON.stringify(p)); } catch (err) { /* ignore */ } }
    function mine(uid) {
        return DB.where('notifications', { user_id: uid }).sort(function (a, b) { return b.notification_id - a.notification_id; });
    }
    function unreadCount(uid) { return DB.count('notifications', { user_id: uid, is_read: 0 }); }
    function ago(v) {
        const d = new Date(String(v).replace(' ', 'T'));
        const s = Math.max(0, Math.round((new Date(DB.now().replace(' ', 'T')).getTime() - d.getTime()) / 1000));
        if (s < 45) { return 'just now'; }
        if (s < 3600) { return Math.round(s / 60) + ' min ago'; }
        if (s < 86400) { return Math.round(s / 3600) + ' hr ago'; }
        if (s < 172800) { return 'Yesterday'; }
        if (s < 604800) { return Math.round(s / 86400) + ' days ago'; }
        return P.fmt_date(v);
    }
    /** Mark one notification read / unread on the server; resolves once the store has the new state. */
    function markRead(n, read) { return P.api('notif-read', { id: n.notification_id, read: read ? 1 : 0 }); }

    function item(n, compact) {
        return '<li class="notif-item tone-' + e(n.tone) + (n.is_read ? '' : ' unread') + '" data-notif-id="' + n.notification_id + '">' +
            '<button type="button" class="notif-open" data-notif-open="' + n.notification_id + '">' +
            '<span class="notif-icon">' + icon(n.icon) + '</span><span class="notif-text"><strong>' + e(n.title) + '</strong>' +
            '<span class="notif-msg' + (compact ? ' clamp' : '') + '">' + e(n.message) + '</span>' +
            '<small>' + icon('fa-clock') + ' ' + e(ago(n.created_at)) + ' · ' + e(TYPES[n.type] ? TYPES[n.type][0] : n.type) + '</small></span></button>' +
            '<span class="notif-actions"><button type="button" class="notif-act" data-notif-toggle-read="' + n.notification_id + '" title="' + (n.is_read ? 'Mark as unread' : 'Mark as read') + '" aria-label="' + (n.is_read ? 'Mark as unread' : 'Mark as read') + '">' +
            icon(n.is_read ? 'fa-envelope' : 'fa-envelope-open') + '</button><button type="button" class="notif-act" data-notif-delete="' + n.notification_id + '" title="Delete" aria-label="Delete">' + icon('fa-trash-can') + '</button></span>' +
            (n.is_read ? '' : '<span class="notif-dot" aria-label="Unread"></span>') + '</li>';
    }

    /* ---------------- topbar bell + panel ---------------- */
    function panelBody(uid, tab) {
        const all = mine(uid);
        const list = (tab === 'unread' ? all.filter(function (n) { return !n.is_read; }) : all).slice(0, 8);
        return list.length ? '<ul class="notif-list">' + list.map(function (n) { return item(n, true); }).join('') + '</ul>'
            : '<div class="notif-empty">' + icon('fa-bell-slash') + '<p>' + (tab === 'unread' ? 'You are all caught up!' : 'No notifications yet') + '</p></div>';
    }
    P.notifBell = function (user) {
        const n = unreadCount(user.user_id);
        const pr = prefs();
        return '<div class="notif-menu" data-notif-menu><button type="button" class="notif-bell' + (n ? ' has-unread' : '') + '" data-notif-bell aria-haspopup="true" aria-expanded="false" title="Notifications" aria-label="Notifications, ' + n + ' unread">' +
            icon('fa-bell') + (n ? '<span class="notif-badge" data-notif-badge>' + (n > 99 ? '99+' : n) + '</span>' : '') + '</button>' +
            '<div class="notif-panel" role="dialog" aria-label="Notifications"><div class="notif-head"><div><strong>Notifications</strong><small data-notif-sub>' + (n ? n + ' unread' : 'All caught up') + '</small></div>' +
            '<div class="notif-head-actions"><button type="button" class="notif-link" data-notif-all-read' + (n ? '' : ' disabled') + '>' + icon('fa-check-double') + ' Mark all read</button>' +
            '<button type="button" class="notif-act" data-notif-settings title="Settings" aria-label="Notification settings">' + icon('fa-gear') + '</button></div></div>' +
            '<div class="notif-settings" hidden data-notif-settings-box><label class="check"><input type="checkbox" data-notif-pref="desktop"' + (pr.desktop ? ' checked' : '') + '><span>Desktop alerts (browser pop-ups)</span></label>' +
            '<label class="check"><input type="checkbox" data-notif-pref="sound"' + (pr.sound ? ' checked' : '') + '><span>Sound for new notifications</span></label></div>' +
            '<div class="notif-tabs"><button type="button" class="active" data-notif-tab="all">All</button><button type="button" data-notif-tab="unread">Unread' + (n ? ' (' + n + ')' : '') + '</button></div>' +
            '<div class="notif-body" data-notif-body>' + panelBody(user.user_id, 'all') + '</div>' +
            '<a class="notif-foot" href="#/notifications">' + icon('fa-list') + ' View all notifications</a></div></div>';
    };
    function refreshBell(keepTab) {
        const menu = document.querySelector('[data-notif-menu]');
        const u = Auth.user();
        if (!menu || !u) { return; }
        const n = unreadCount(u.user_id);
        const bell = menu.querySelector('[data-notif-bell]');
        bell.classList.toggle('has-unread', n > 0);
        bell.setAttribute('aria-label', 'Notifications, ' + n + ' unread');
        let badge = menu.querySelector('[data-notif-badge]');
        if (n && !badge) { badge = document.createElement('span'); badge.className = 'notif-badge'; badge.setAttribute('data-notif-badge', ''); bell.appendChild(badge); }
        if (badge) { if (n) { badge.textContent = n > 99 ? '99+' : n; } else { badge.remove(); } }
        menu.querySelector('[data-notif-sub]').textContent = n ? n + ' unread' : 'All caught up';
        menu.querySelector('[data-notif-all-read]').disabled = !n;
        menu.querySelector('[data-notif-tab="unread"]').textContent = 'Unread' + (n ? ' (' + n + ')' : '');
        const active = menu.querySelector('[data-notif-tab].active');
        const tab = keepTab || (active ? active.getAttribute('data-notif-tab') : 'all');
        menu.querySelector('[data-notif-body]').innerHTML = panelBody(u.user_id, tab);
        // keep the sidebar/page list in sync when on the notifications page
        if (window.location.hash.indexOf('#/notifications') === 0) { pageListRefresh(); }
    }
    function setOpen(menu, open) {
        menu.classList.toggle('open', open);
        menu.querySelector('[data-notif-bell]').setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    document.addEventListener('click', function (ev) {
        const menu = document.querySelector('[data-notif-menu]');
        const bell = ev.target.closest('[data-notif-bell]');
        if (bell) { setOpen(menu, !menu.classList.contains('open')); return; }
        if (menu && menu.classList.contains('open') && !ev.target.closest('[data-notif-menu]')) { setOpen(menu, false); }

        const u = Auth.user();
        if (!u) { return; }
        const t = ev.target;
        let el;
        if ((el = t.closest('[data-notif-tab]'))) {
            el.parentNode.querySelectorAll('button').forEach(function (b) { b.classList.toggle('active', b === el); });
            refreshBell(el.getAttribute('data-notif-tab'));
        } else if ((el = t.closest('[data-notif-toggle-read]'))) {
            const n = DB.find('notifications', el.getAttribute('data-notif-toggle-read'));
            if (n && n.user_id === u.user_id) { markRead(n, !n.is_read).then(function () { refreshBell(); }); }
        } else if ((el = t.closest('[data-notif-delete]'))) {
            const n = DB.find('notifications', el.getAttribute('data-notif-delete'));
            if (n && n.user_id === u.user_id) {
                const li = el.closest('.notif-item');
                li.classList.add('removing');
                P.api('notif-delete', { id: n.notification_id }).then(function (res) {
                    window.setTimeout(function () {
                        refreshBell();
                        if (res.ok) { P.toast('info', 'Notification deleted.'); } else { li.classList.remove('removing'); P.toast('error', res.message); }
                    }, 200);
                });
            }
        } else if ((el = t.closest('[data-notif-open]'))) {
            const n = DB.find('notifications', el.getAttribute('data-notif-open'));
            if (n && n.user_id === u.user_id) {
                if (menu) { setOpen(menu, false); }
                markRead(n, true).then(function () {
                    if (n.link) {
                        const parts = n.link.split('?');
                        const q = {};
                        (parts[1] || '').split('&').filter(Boolean).forEach(function (kv) { const x = kv.split('='); q[x[0]] = decodeURIComponent(x[1] || ''); });
                        P.go(parts[0], q);
                    } else { refreshBell(); }
                });
            }
        } else if (t.closest('[data-notif-all-read]')) {
            P.api('notif-read-all', {}).then(function () {
                refreshBell();
                P.toast('success', 'All notifications marked as read.');
            });
        } else if (t.closest('[data-notif-settings]')) {
            const box = document.querySelector('[data-notif-settings-box]');
            box.hidden = !box.hidden;
        } else if (t.closest('[data-notif-delete-read]')) {
            P.confirm({ title: 'Delete read notifications', message: 'Delete all read notifications? Unread ones are kept.', okText: 'Yes, delete', variant: 'danger' }).then(function (yes) {
                if (!yes) { return; }
                P.api('notif-delete-read', {}).then(function (res) {
                    (res.flashes || []).forEach(function (f) { P.flash(f.type, f.message); });
                    if (!res.ok) { P.toast('error', res.message); }
                    P.render();
                });
            });
        }
    });
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape') { const m = document.querySelector('[data-notif-menu].open'); if (m) { setOpen(m, false); } }
    });
    document.addEventListener('change', function (ev) {
        const el = ev.target.closest('[data-notif-pref]');
        if (!el) { return; }
        const p = prefs();
        p[el.getAttribute('data-notif-pref')] = el.checked;
        if (el.getAttribute('data-notif-pref') === 'desktop' && el.checked) {
            if (!('Notification' in window)) { P.toast('warning', 'This browser does not support desktop notifications.'); el.checked = false; p.desktop = false; }
            else if (window.Notification.permission !== 'granted') {
                window.Notification.requestPermission().then(function (r) {
                    if (r !== 'granted') { el.checked = false; p.desktop = false; savePrefs(p); P.toast('warning', 'Desktop alerts were blocked by the browser.'); }
                    else { P.toast('success', 'Desktop alerts enabled.'); }
                });
            }
        }
        savePrefs(p);
    });

    /* ---------------- new-notification alerts (toast, sound, desktop) ---------------- */
    function beep() {
        // browsers allow sound only after the user has clicked / typed on the page
        if (navigator.userActivation && !navigator.userActivation.hasBeenActive) { return; }
        try {
            const Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) { return; }
            const ctx = new Ctx();
            const o = ctx.createOscillator(), g = ctx.createGain();
            o.type = 'sine'; o.frequency.value = 880;
            g.gain.setValueAtTime(0.0001, ctx.currentTime);
            g.gain.exponentialRampToValueAtTime(0.08, ctx.currentTime + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.35);
            o.connect(g); g.connect(ctx.destination); o.start(); o.stop(ctx.currentTime + 0.36);
        } catch (err) { /* autoplay blocked */ }
    }
    P.checkNewNotifications = function () {
        const u = Auth.user();
        if (!u) { return; }
        let seen = 0;
        try { seen = Number(window.localStorage.getItem(SEEN_KEY + u.user_id) || 0); } catch (err) { /* ignore */ }
        const fresh = mine(u.user_id).filter(function (n) { return !n.is_read && n.notification_id > seen; });
        const maxId = mine(u.user_id).reduce(function (m, n) { return Math.max(m, n.notification_id); }, seen);
        try { window.localStorage.setItem(SEEN_KEY + u.user_id, String(maxId)); } catch (err) { /* ignore */ }
        if (!fresh.length) { return; }
        refreshBell();
        const bell = document.querySelector('[data-notif-bell]');
        if (bell) { bell.classList.remove('ring'); void bell.offsetWidth; bell.classList.add('ring'); }
        if (fresh.length <= 2) { fresh.forEach(function (n) { P.toast(n.tone === 'danger' ? 'warning' : n.tone === 'success' ? 'success' : 'info', n.title + ' — ' + n.message, 6500); }); }
        else { P.toast('info', 'You have ' + fresh.length + ' new notifications. Click the bell to see them.', 6500); }
        const pr = prefs();
        if (pr.sound) { beep(); }
        if (pr.desktop && 'Notification' in window && window.Notification.permission === 'granted') {
            fresh.slice(0, 3).forEach(function (n) {
                try {
                    const dn = new window.Notification(P.APP.name + ' — ' + n.title, { body: n.message, tag: 'praman-' + n.notification_id });
                    dn.onclick = function () { window.focus(); markRead(n, true).then(function () { refreshBell(); }); if (n.link) { window.location.hash = '#' + n.link; } dn.close(); };
                } catch (err) { /* ignore */ }
            });
        }
    };
    // run after every render (core calls initPage → we chain on it)
    const prevInit = P.initPage;
    P.initPage = function (root) {
        if (prevInit) { prevInit(root); }
        window.setTimeout(P.checkNewNotifications, 350);
    };
    // core.js polls /api/state every minute → refresh the bell and alert on new rows
    P.onStateRefresh = function () {
        refreshBell();
        P.checkNewNotifications();
    };

    /* ---------------- full page ---------------- */
    let pageCtx = null;
    function pageList(ctx) {
        const u = Auth.user();
        const q = ctx.query;
        let rows = mine(u.user_id);
        if (q.filter === 'unread') { rows = rows.filter(function (n) { return !n.is_read; }); }
        else if (TYPES[q.filter]) { rows = rows.filter(function (n) { return n.type === q.filter; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'id', dir: 'desc', filters: ['filter'],
            search: function (n) { return [n.title, n.message, n.type]; },
            sortable: { id: function (n) { return n.notification_id; } }
        });
        let html = '';
        let lastDay = '';
        const t = DB.today(), y = DB.today(-1);
        p.items.forEach(function (n) {
            const d = n.created_at.slice(0, 10);
            if (d !== lastDay) {
                if (lastDay) { html += '</ul>'; }
                html += '<h4 class="notif-day">' + (d === t ? 'Today' : d === y ? 'Yesterday' : P.fmt_date(d)) + '</h4><ul class="notif-list page">';
                lastDay = d;
            }
            html += item(n, false);
        });
        if (lastDay) { html += '</ul>'; }
        return { p: p, html: html || '<div class="notif-empty big">' + icon(q.filter === 'unread' ? 'fa-mug-hot' : 'fa-bell-slash') +
            '<p>' + (q.filter === 'unread' ? 'You are all caught up!' : p.hasFilters() ? 'No matching notifications' : 'No notifications yet') + '</p></div>' };
    }
    function pageListRefresh() {
        const box = document.querySelector('[data-notif-page]');
        if (!box || !pageCtx) { return; }
        const u = Auth.user();
        const r = pageList(pageCtx);
        box.innerHTML = r.html;
        const all = mine(u.user_id);
        document.querySelectorAll('[data-notif-stat]').forEach(function (el) {
            const k = el.getAttribute('data-notif-stat');
            el.textContent = k === 'total' ? all.length : k === 'unread' ? all.filter(function (n) { return !n.is_read; }).length
                : all.filter(function (n) { return n.created_at.slice(0, 10) === DB.today(); }).length;
        });
    }

    P.route('/notifications', null, function (ctx) {
        pageCtx = ctx;
        const u = ctx.user;
        const all = mine(u.user_id);
        const unread = all.filter(function (n) { return !n.is_read; }).length;
        const r = pageList(ctx);
        const cur = ctx.query.filter || '';
        const tabs = [['', 'All', 'fa-list', all.length], ['unread', 'Unread', 'fa-envelope', unread]].concat(Object.keys(TYPES).map(function (k) {
            return [k, TYPES[k][0], TYPES[k][1], all.filter(function (n) { return n.type === k; }).length];
        }));
        return {
            title: 'Notifications',
            html: P.pageHeader(icon('fa-bell') + ' Notifications', 'Everything that needs your attention — tasks, verification results, property updates and account alerts.',
                '<button type="button" class="btn btn-light" data-notif-delete-read>' + icon('fa-broom') + ' <span>Delete read</span></button>' +
                '<button type="button" class="btn btn-primary" data-notif-all-read>' + icon('fa-check-double') + ' <span>Mark all read</span></button>') +
                '<div class="stat-grid dash-stats">' +
                '<a class="stat-card stat-navy stat-link" href="#/notifications" title="Total notifications — all alerts in system"><span class="stat-icon">' + icon('fa-bell') + '</span><div class="stat-content"><p class="stat-value" data-notif-stat="total">' + all.length + '</p><p class="stat-label" title="Total">Total</p><small class="stat-sub" title="all notifications">all notifications</small></div></a>' +
                '<a class="stat-card stat-gold stat-link" href="' + P.url('/notifications', { filter: 'unread' }) + '" title="Unread notifications — pending review"><span class="stat-icon">' + icon('fa-envelope') + '</span><div class="stat-content"><p class="stat-value" data-notif-stat="unread">' + unread + '</p><p class="stat-label" title="Unread">Unread</p><small class="stat-sub" title="need review">need review</small></div></a>' +
                '<div class="stat-card stat-green" title="Notifications received today"><span class="stat-icon">' + icon('fa-calendar-day') + '</span><div class="stat-content"><p class="stat-value" data-notif-stat="today">' +
                all.filter(function (n) { return n.created_at.slice(0, 10) === DB.today(); }).length + '</p><p class="stat-label" title="Today">Today</p><small class="stat-sub" title="received today">received today</small></div></div></div>' +
                '<nav class="tabs" aria-label="Notification filter">' + tabs.map(function (x) {
                    return '<a href="' + P.url('/notifications', { filter: x[0] }) + '" class="tab' + (cur === x[0] ? ' active' : '') + '">' + icon(x[2]) + ' ' + x[1] +
                        ' <span class="tab-count">' + x[3] + '</span></a>';
                }).join('') + '</nav>' +
                '<div class="card">' + P.list_toolbar(r.p, 'Search notifications…', cur ? '<input type="hidden" name="filter" value="' + e(cur) + '">' : '') +
                '<div class="card-body notif-page-body" data-notif-page>' + r.html + '</div>' + P.pagination(r.p) + '</div>'
        };
    });
})(window, document);
