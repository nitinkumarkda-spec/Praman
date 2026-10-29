/* ===== js/pages/admin.js ===== */
/* PRAMAAN — Super Admin panel (app/Views/admin/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'SUPER_ADMIN';
    const isLocked = function (u) { return !!u.locked_until && u.locked_until > DB.now(); };

    /* ================= Role Master ================= */
    function roleRows() {
        return DB.all('roles').map(function (r) {
            const us = DB.where('users', { role_id: r.role_id, panel: 'ROLE_USER' });
            return Object.assign({}, r, { users_count: us.length, active_users: us.filter(function (u) { return u.is_active; }).length });
        });
    }
    P.route('/admin/roles', PANEL, function (ctx) {
        let rows = roleRows();
        const st = ctx.query.status || '';
        if (st === 'active') { rows = rows.filter(function (r) { return r.is_active; }); }
        if (st === 'inactive') { rows = rows.filter(function (r) { return !r.is_active; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'order', dir: 'asc', filters: ['status'],
            search: function (r) { return [r.role_name, r.role_code, r.role_name_hi]; },
            sortable: {
                order: function (r) { return r.sort_order; }, name: function (r) { return r.role_name; }, code: function (r) { return r.role_code; },
                sla: function (r) { return r.sla_days; }, users: function (r) { return r.active_users; }
            }
        });
        const filters = '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' +
            L.options({ active: 'Active', inactive: 'Inactive' }, st, 'All status') + '</select>';
        return {
            title: 'Role Master',
            html: P.pageHeader(icon('fa-sitemap') + ' Role Master', 'Each role is one verification section (Engineering, Sale, Legal …). Users are mapped to roles in the User Master.',
                '<a href="#/admin/roles/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Role</span></a>') +
                '<div class="card">' + P.list_toolbar(p, 'Search role name or code…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'order', '#') + P.th_sort(p, 'name', 'Role / Section') + P.th_sort(p, 'code', 'Code') + P.th_sort(p, 'sla', 'SLA (days)') +
                '<th>Mandatory</th>' + P.th_sort(p, 'users', 'Users') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (r) {
                    const on = r.is_active === 1;
                    return '<tr><td data-label="Order">' + r.sort_order + '</td><td data-label="Role"><strong>' + e(r.role_name) + '</strong>' +
                        (r.role_name_hi ? '<span class="cell-sub">' + e(r.role_name_hi) + '</span>' : '') + '</td>' +
                        '<td data-label="Code"><span class="mono">' + e(r.role_code) + '</span></td><td data-label="SLA">' + r.sla_days + '</td>' +
                        '<td data-label="Mandatory">' + (r.is_mandatory ? '<span class="badge badge-gold">' + icon('fa-star') + ' Mandatory</span>' : '<span class="badge badge-muted">Optional</span>') + '</td>' +
                        '<td data-label="Users"><a href="' + P.url('/admin/users', { role_id: r.role_id }) + '">' + r.active_users + ' active</a><span class="cell-sub">' + r.users_count + ' total</span></td>' +
                        '<td data-label="Status">' + P.active_badge(r.is_active) + '</td><td class="actions"><div class="btn-group">' +
                        '<a href="#/admin/roles/' + r.role_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + r.role_name) + '" aria-label="' + e('Edit ' + r.role_name) + '">' + icon('fa-pen') + '</a>' +
                        '<form method="post" data-action="role-toggle" data-confirm="' + e(on ? 'Deactivate role "' + r.role_name + '"? It will not be used for new schemes and verification rounds.' : 'Activate role "' + r.role_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                        '<input type="hidden" name="id" value="' + r.role_id + '">' +
                        (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                            : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') +
                        '</form></div></td></tr>';
                }).join('') : P.empty_state(8, p.hasFilters(), 'No roles yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    });

    function roleForm(role) {
        const isEdit = !!role;
        const v = function (k, d) { return isEdit ? (role[k] == null ? '' : role[k]) : (d === undefined ? '' : d); };
        const usage = isEdit ? new Set(DB.where('scheme_users', { role_id: role.role_id, is_active: 1 }).map(function (m) { return m.scheme_id; })).size : 0;
        const input = function (id, text, req, attrs, value, hint) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + text + (req ? ' <span class="required">*</span>' : '') + '</label>' +
                '<input id="' + id + '" name="' + id + '" value="' + e(value) + '" ' + attrs + '>' + (hint ? '<div class="form-hint">' + hint + '</div>' : '') + '<div class="invalid-feedback"></div></div>';
        };
        return {
            title: isEdit ? 'Edit Role' : 'Add Role',
            html: P.pageHeader(icon('fa-sitemap') + ' ' + (isEdit ? 'Edit Role' : 'Add Role'),
                isEdit ? 'Changes apply to new schemes and new verification rounds.' : 'Create a verification section. Map users to it from the User Master.',
                '<a href="#/admin/roles" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
                (isEdit && usage > 0 ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This role is mapped in <strong>' + usage + '</strong> scheme(s). Existing properties keep their snapshot.</span></div>' : '') +
                '<div class="card"><div class="card-body"><form method="post" data-action="role-save" novalidate data-validate>' +
                (isEdit ? '<input type="hidden" name="id" value="' + role.role_id + '">' : '') + '<div class="form-grid">' +
                input('role_name', 'Role name', true, 'type="text" class="form-control" maxlength="100" placeholder="e.g. Engineering Cell" data-rules="required|min:3|max:100|name" data-label="Role name"', v('role_name')) +
                input('role_code', 'Role code', true, 'type="text" class="form-control mono" maxlength="40" placeholder="e.g. ENGINEERING_CELL" data-uppercase ' + (isEdit ? 'readonly' : 'data-rules="required|code" data-label="Role code"'), v('role_code'),
                    isEdit ? 'Code cannot be changed after creation.' : 'CAPITAL letters, digits and underscore. Cannot be changed later.') +
                input('role_name_hi', 'Role name (Hindi)', false, 'type="text" class="form-control" maxlength="100" placeholder="e.g. अभियांत्रिकी शाखा" data-rules="max:100|text" data-label="Role name (Hindi)"', v('role_name_hi')) +
                input('sla_days', 'SLA (days to respond)', true, 'type="number" class="form-control" min="1" max="365" data-rules="required|integer|between:1,365" data-label="SLA days"', v('sla_days', 5)) +
                input('sort_order', 'Display order', true, 'type="number" class="form-control" min="0" max="999" data-rules="required|integer|between:0,999" data-label="Display order"', v('sort_order', DB.count('roles') + 1)) +
                input('description', 'Description', false, 'type="text" class="form-control" maxlength="255" placeholder="What does this section verify?" data-rules="max:255|text" data-label="Description"', v('description')) +
                '<div class="form-group"><label class="check"><input type="checkbox" name="is_mandatory" value="1"' + (!isEdit || role.is_mandatory ? ' checked' : '') + '>' +
                '<span><strong>Mandatory</strong><br><small class="text-muted">Every scheme must map at least one user of this role.</small></span></label></div>' +
                '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (!isEdit || role.is_active ? ' checked' : '') + '>' +
                '<span><strong>Active</strong><br><small class="text-muted">Inactive roles are skipped for new schemes and rounds.</small></span></label></div>' +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update Role' : 'Create Role') + '</span></button>' +
                '<a href="#/admin/roles" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>'
        };
    }
    P.route('/admin/roles/create', PANEL, function () { return roleForm(null); });
    P.route('/admin/roles/{id}/edit', PANEL, function (ctx) {
        const r = DB.find('roles', ctx.params.id);
        return r ? roleForm(r) : P.notFound('Role not found.');
    });


    /* ================= User Master ================= */
    P.route('/admin/users', PANEL, function (ctx) {
        const q = ctx.query;
        let rows = DB.all('users').map(function (u) {
            const r = u.role_id ? DB.find('roles', u.role_id) : null;
            return Object.assign({}, u, { role_name: r ? r.role_name : null, is_locked: isLocked(u) ? 1 : 0 });
        });
        if (q.panel) { rows = rows.filter(function (u) { return u.panel === q.panel; }); }
        if (q.role_id) { rows = rows.filter(function (u) { return String(u.role_id) === q.role_id; }); }
        if (q.status === 'active') { rows = rows.filter(function (u) { return u.is_active; }); }
        if (q.status === 'inactive') { rows = rows.filter(function (u) { return !u.is_active; }); }
        if (q.status === 'locked') { rows = rows.filter(function (u) { return u.is_locked; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['panel', 'role_id', 'status'],
            search: function (u) { return [u.name, u.username, u.emp_code, u.mobile, u.email, u.designation]; },
            sortable: {
                name: function (u) { return u.name; }, username: function (u) { return u.username; }, emp_code: function (u) { return u.emp_code; },
                panel: function (u) { return u.panel; }, role: function (u) { return u.role_name; }, last_login: function (u) { return u.last_login_at; }
            }
        });
        const roles = {};
        DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).forEach(function (r) { roles[r.role_id] = r.role_name; });
        const filters = '<select name="panel" class="form-select" aria-label="Panel" data-auto-submit>' + L.options(P.PANELS, q.panel, 'All panels') + '</select>' +
            '<select name="role_id" class="form-select" aria-label="Role" data-auto-submit>' + L.options(roles, q.role_id, 'All roles') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive', locked: 'Locked' }, q.status, 'All status') + '</select>';
        const me = Auth.id();
        return {
            title: 'User Master',
            html: P.pageHeader(icon('fa-users') + ' User Master', 'Panel users and role users (e.g. engineer-1 under Engineering). Many users can belong to one role.',
                '<a href="#/admin/users/create" class="btn btn-primary">' + icon('fa-user-plus') + ' <span>Add User</span></a>') +
                '<div class="card">' + P.list_toolbar(p, 'Search name, username, emp code, mobile, email…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Name') + P.th_sort(p, 'username', 'Username') + P.th_sort(p, 'emp_code', 'Emp. Code') + P.th_sort(p, 'panel', 'Panel') +
                P.th_sort(p, 'role', 'Role') + '<th>Contact</th>' + P.th_sort(p, 'last_login', 'Last login') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (u) {
                    const self = u.user_id === me;
                    return '<tr><td data-label="Name"><strong>' + e(u.name) + '</strong>' + (self ? ' <span class="badge badge-gold">You</span>' : '') + '<span class="cell-sub">' + e(u.designation) + '</span></td>' +
                        '<td data-label="Username"><span class="mono">' + e(u.username) + '</span></td><td data-label="Emp. Code">' + e(u.emp_code) + '</td>' +
                        '<td data-label="Panel"><span class="badge badge-primary">' + e(P.PANELS[u.panel]) + '</span></td><td data-label="Role">' + e(u.role_name || '—') + '</td>' +
                        '<td data-label="Contact">' + e(u.mobile) + '<span class="cell-sub">' + e(u.email) + '</span></td>' +
                        '<td data-label="Last login">' + e(P.fmt_datetime(u.last_login_at)) + '</td><td data-label="Status">' + P.active_badge(u.is_active) +
                        (u.is_locked ? ' <span class="badge badge-danger">' + icon('fa-lock') + ' Locked</span>' : '') + '</td><td class="actions"><div class="btn-group">' +
                        (!self && u.panel !== 'SUPER_ADMIN' && u.is_active ? '<form method="post" data-action="impersonate"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn-switch" title="' + e('Login as ' + u.name) + '" aria-label="' + e('Login as ' + u.name) + '">' + icon('fa-right-to-bracket') + '</button></form>' : '') +
                        '<a href="#/admin/users/' + u.user_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + u.name) + '" aria-label="' + e('Edit ' + u.name) + '">' + icon('fa-pen') + '</a>' +
                        (u.is_locked ? '<form method="post" data-action="user-unlock" data-confirm="' + e('Unlock account "' + u.username + '"?') + '"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn btn-sm btn-warning btn-icon-only" title="Unlock account" aria-label="Unlock account">' + icon('fa-lock-open') + '</button></form>' : '') +
                        (!self ? '<form method="post" data-action="user-toggle" data-confirm="' + e(u.is_active ? 'Deactivate "' + u.name + '"? They will not be able to login.' : 'Activate "' + u.name + '"?') + '" data-confirm-variant="' + (u.is_active ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="id" value="' + u.user_id + '">' +
                            (u.is_active ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-user-slash') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-user-check') + '</button>') + '</form>' : '') +
                        '</div></td></tr>';
                }).join('') : P.empty_state(9, p.hasFilters(), 'No users yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    });

    function userForm(user) {
        const isEdit = !!user;
        const isSelf = isEdit && user.user_id === Auth.id();
        const v = function (k, d) { return isEdit ? (user[k] == null ? '' : user[k]) : (d || ''); };
        const panel = v('panel', 'ROLE_USER');
        const roles = DB.where('roles', { is_active: 1 }).sort(P.by(function (r) { return r.sort_order; }));
        const mappings = isEdit ? DB.count('scheme_users', { user_id: user.user_id, is_active: 1 }) : 0;
        const txt = function (id, text, attrs, value) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + text + ' <span class="required">*</span></label>' +
                '<input id="' + id + '" name="' + id + '" value="' + e(value) + '" ' + attrs + '><div class="invalid-feedback"></div></div>';
        };
        const pw = function (id, text, rules) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + text + ' <span class="required">*</span></label>' +
                '<div class="input-icon input-password">' + icon('fa-key') + '<input type="password" id="' + id + '" name="' + id + '" class="form-control" maxlength="72" autocomplete="new-password" data-rules="' + rules + '" data-label="' + text + '">' +
                '<button type="button" class="toggle-password" data-toggle-password="' + id + '" aria-label="Show password">' + icon('fa-eye') + '</button></div>';
        };
        let roleOpts = '<option value="">Select role</option>';
        let found = false;
        roles.forEach(function (r) {
            const sel = isEdit && user.role_id === r.role_id;
            found = found || sel;
            roleOpts += '<option value="' + r.role_id + '"' + (sel ? ' selected' : '') + '>' + e(r.role_name) + '</option>';
        });
        if (isEdit && !found && user.role_id) { roleOpts += '<option value="' + user.role_id + '" selected>' + e(DB.find('roles', user.role_id).role_name) + ' (inactive)</option>'; }

        let html = P.pageHeader(icon('fa-user-gear') + ' ' + (isEdit ? 'Edit User' : 'Add User'),
            isEdit ? e(user.name + ' · ' + user.username) : 'Create a login for a panel user or a role user.',
            '<a href="#/admin/users" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
            (mappings > 0 ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This user is mapped in <strong>' + mappings + '</strong> scheme role mapping(s). Changing the role or deactivating removes them from <em>new</em> properties only.</span></div>' : '') +
            '<div class="' + (isEdit ? 'grid-2' : '') + '"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-id-card') + ' User details</h3></div><div class="card-body">' +
            '<form method="post" data-action="user-save" novalidate data-validate autocomplete="off">' + (isEdit ? '<input type="hidden" name="id" value="' + user.user_id + '">' : '') +
            '<div class="form-grid">' +
            txt('name', 'Full name', 'type="text" class="form-control" maxlength="120" data-rules="required|min:3|max:120|name" data-label="Full name"', v('name')) +
            txt('designation', 'Designation', 'type="text" class="form-control" maxlength="100" placeholder="e.g. Junior Engineer" data-rules="required|min:2|max:100|text" data-label="Designation"', v('designation')) +
            txt('username', 'Username (login id)', 'type="text" class="form-control mono" maxlength="50" placeholder="e.g. eng3" autocomplete="off" data-rules="required|min:3|max:50|alpha_dash" data-label="Username"', v('username')) +
            txt('emp_code', 'Employee code', 'type="text" class="form-control" maxlength="30" placeholder="e.g. KDA-ENG-003" data-uppercase data-rules="required|ucode" data-label="Employee code"', v('emp_code')) +
            txt('mobile', 'Mobile', 'type="tel" class="form-control" maxlength="10" inputmode="numeric" placeholder="10-digit mobile" data-rules="required|mobile" data-label="Mobile"', v('mobile')) +
            txt('email', 'Email', 'type="email" class="form-control" maxlength="150" data-rules="required|max:150|email" data-label="Email"', v('email')) +
            '<div class="form-group"><label class="form-label" for="panel">Panel type <span class="required">*</span></label>' +
            '<select id="panel" name="panel" class="form-select" data-rules="required" data-label="Panel type" data-panel-select' + (isSelf ? ' disabled' : '') + '>' + L.options(P.PANELS, panel) + '</select>' +
            (isSelf ? '<input type="hidden" name="panel" value="' + e(user.panel) + '"><div class="form-hint">You cannot change your own panel.</div>' : '') + '<div class="invalid-feedback"></div></div>' +
            '<div class="form-group" data-role-group' + (panel === 'ROLE_USER' ? '' : ' hidden') + '><label class="form-label" for="role_id">Role / Section <span class="required">*</span></label>' +
            '<select id="role_id" name="role_id" class="form-select" data-label="Role"' + (panel === 'ROLE_USER' ? ' data-rules="required"' : '') + '>' + roleOpts + '</select>' +
            '<div class="form-hint">The verification section this user works for.</div><div class="invalid-feedback"></div></div>' +
            (!isEdit ? pw('password', 'Password', 'required|password') + '<div class="form-hint">≥ ' + P.SECURITY.password_min + ' chars with upper, lower, digit &amp; special character.</div><div class="invalid-feedback"></div></div>' +
                pw('confirm_password', 'Confirm password', 'required|same:password') + '<div class="invalid-feedback"></div></div>' : '') +
            '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (!isEdit || user.is_active ? ' checked' : '') + (isSelf ? ' disabled checked' : '') + '>' +
            '<span><strong>Active</strong><br><small class="text-muted">Inactive users cannot login.</small></span></label></div>' +
            '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update User' : 'Create User') + '</span></button>' +
            '<a href="#/admin/users" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>';
        if (isEdit) {
            html += '<div><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-unlock-keyhole') + ' Reset password</h3></div><div class="card-body">' +
                '<p class="text-muted mb-3">Set a temporary password. The user must change it on next login. This also unlocks the account.</p>' +
                '<form method="post" data-action="user-reset-password" novalidate data-validate autocomplete="off" data-confirm="' + e('Reset the password of "' + user.name + '"?') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="id" value="' + user.user_id + '">' + pw('reset_password', 'New temporary password', 'required|password') + '<div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="reset_confirm">Confirm password <span class="required">*</span></label>' +
                '<input type="password" id="reset_confirm" name="reset_confirm" class="form-control" maxlength="72" autocomplete="new-password" data-rules="required|same:reset_password" data-label="Confirm new password"><div class="invalid-feedback"></div></div>' +
                '<button type="submit" class="btn btn-warning" data-loading-text="Resetting…">' + icon('fa-rotate') + ' <span>Reset password</span></button></form></div></div>' +
                '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-circle-info') + ' Account info</h3></div><div class="card-body"><dl class="detail-list">' +
                '<dt>Status</dt><dd>' + P.active_badge(user.is_active) + '</dd>' +
                '<dt>Last login</dt><dd>' + e(P.fmt_datetime(user.last_login_at)) + (user.last_login_ip ? ' · ' + e(user.last_login_ip) : '') + '</dd>' +
                '<dt>Password changed</dt><dd>' + e(P.fmt_datetime(user.password_changed_at)) + '</dd>' +
                '<dt>Locked until</dt><dd>' + e(P.fmt_datetime(user.locked_until)) + '</dd>' +
                '<dt>Created</dt><dd>' + e(P.fmt_datetime(user.created_at)) + '</dd></dl></div></div></div>';
        }
        return { title: isEdit ? 'Edit User' : 'Add User', html: html + '</div>' };
    }
    P.route('/admin/users/create', PANEL, function () { return userForm(null); });
    P.route('/admin/users/{id}/edit', PANEL, function (ctx) {
        const u = DB.find('users', ctx.params.id);
        return u ? userForm(u) : P.notFound('User not found.');
    });

    /* ================= App Settings (branding, landing page, sign-in popup, limits, slides) ================= */
    P.route('/admin/settings', PANEL, function () {
        const app = DB.app();
        const val = app.settings || {};
        const groups = app.settings_groups || {};
        const meta = app.settings_meta || [];
        const field = function (m) {
            const v = val[m.key];
            const req = /required/.test(m.rules || '');
            if (m.type === 'bool') {
                return '<div class="form-group span-2"><label class="check"><input type="checkbox" name="' + e(m.key) + '" value="1"' + (v ? ' checked' : '') + '>' +
                    '<span><strong>' + e(m.label) + '</strong>' + (m.hint ? '<br><small class="text-muted">' + e(m.hint) + '</small>' : '') + '</span></label></div>';
            }
            const max = (/max:(\d+)/.exec(m.rules || '') || [])[1];
            const rng = /between:(\d+),(\d+)/.exec(m.rules || '');
            return '<div class="form-group"><label class="form-label" for="set_' + e(m.key) + '">' + e(m.label) + (req ? ' <span class="required">*</span>' : '') + '</label>' +
                '<input id="set_' + e(m.key) + '" name="' + e(m.key) + '" class="form-control" value="' + e(v == null ? '' : v) + '"' +
                (m.type === 'int' ? ' type="number"' + (rng ? ' min="' + rng[1] + '" max="' + rng[2] + '"' : '') : m.type === 'url' ? ' type="url" inputmode="url"' : ' type="text"') +
                (max ? ' maxlength="' + max + '"' : '') + (m.rules ? ' data-rules="' + e(m.rules) + '"' : '') + ' data-label="' + e(m.label) + '" autocomplete="off">' +
                (m.hint ? '<div class="form-hint">' + e(m.hint) + '</div>' : '') + '<div class="invalid-feedback"></div></div>';
        };
        const cards = Object.keys(groups).map(function (g) {
            const rows = meta.filter(function (m) { return m.group === g; });
            if (!rows.length) { return ''; }
            return '<div class="card"><div class="card-header"><h3 class="card-title">' + icon(groups[g][1]) + ' ' + e(groups[g][0]) + '</h3></div>' +
                '<div class="card-body"><div class="form-grid">' + rows.map(field).join('') + '</div></div></div>';
        }).join('');

        const slides = (app.slides || []).slice().sort(P.by(function (s) { return s.sort_order; }));
        const src = function (s) { return P.BASE + '/assets/img/slides/' + encodeURIComponent(s.image); };
        const slideRows = slides.map(function (s) {
            const id = s.slide_id;
            return '<tr><td data-label="Photo"><img src="' + e(src(s)) + '" alt="" width="96" height="86" class="slide-thumb" loading="lazy"></td>' +
                '<td data-label="Title"><div class="form-group"><input type="text" name="title_' + id + '" class="form-control" maxlength="120" value="' + e(s.title) + '" data-rules="required|min:3|max:120|text" data-label="Slide title" aria-label="Slide title"><div class="invalid-feedback"></div></div></td>' +
                '<td data-label="Photo credit"><div class="form-group"><input type="text" name="credit_' + id + '" class="form-control" maxlength="200" value="' + e(s.credit || '') + '" data-rules="max:200|text" data-label="Photo credit" aria-label="Photo credit"><div class="invalid-feedback"></div></div></td>' +
                '<td data-label="Order"><div class="form-group"><input type="number" name="order_' + id + '" class="form-control" min="0" max="99" value="' + s.sort_order + '" data-rules="required|integer|between:0,99" data-label="Order" aria-label="Order"><div class="invalid-feedback"></div></div></td>' +
                '<td data-label="Active"><label class="check"><input type="checkbox" name="active_' + id + '" value="1"' + (s.is_active ? ' checked' : '') + ' data-slide-active><span>Show</span></label></td>' +
                '<td class="actions"><button type="submit" form="slide-del-' + id + '" class="btn btn-sm btn-light text-danger btn-icon-only" title="Delete slide" aria-label="Delete slide">' + icon('fa-trash-can') + '</button></td></tr>';
        }).join('');
        const delForms = slides.map(function (s) {
            return '<form method="post" data-action="slide-delete" id="slide-del-' + s.slide_id + '" hidden data-confirm="' + e('Delete slide "' + s.title + '" from the landing page?') + '" data-confirm-variant="danger" data-confirm-ok="Yes, delete">' +
                '<input type="hidden" name="id" value="' + s.slide_id + '"></form>';
        }).join('');

        return {
            title: 'App Settings',
            html: P.pageHeader(icon('fa-sliders') + ' App Settings', 'Name, tagline, landing page, sign-in popup, security and verification limits — changes apply to every screen immediately.') +
                '<form method="post" data-action="settings-save" novalidate data-validate data-settings-form data-confirm="Save the settings? They apply to all users immediately.">' + cards +
                '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Save settings</span></button></div></form>' +
                '<div class="card" id="slides"><div class="card-header"><h3 class="card-title">' + icon('fa-images') + ' Landing page slides</h3><span class="badge badge-info">' +
                slides.filter(function (s) { return s.is_active; }).length + ' active</span></div>' +
                '<form method="post" data-action="slides-save" novalidate data-validate data-slides-form><div class="table-wrap"><table class="table table-stack table-top"><thead><tr><th>Photo</th><th>Title</th><th>Photo credit</th><th>Order</th><th>Active</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (slideRows || P.empty_state(6, false, 'No slides yet')) + '</tbody></table></div>' +
                (slides.length ? '<div class="card-body"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Save slides</span></button></div>' : '') + '</form>' + delForms + '</div>' +
                '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-cloud-arrow-up') + ' Add a slide</h3></div><div class="card-body">' +
                '<form method="post" data-action="slide-add" novalidate data-validate data-slide-add enctype="multipart/form-data"><div class="form-grid">' +
                '<div class="form-group"><label class="form-label" for="slide_image">Photo (JPG / PNG, max 3 MB, at least 600 × 400) <span class="required">*</span></label>' +
                '<input type="file" id="slide_image" name="image" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-rules="required" data-label="the photo"><div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="slide_title">Title <span class="required">*</span></label><input type="text" id="slide_title" name="title" class="form-control" maxlength="120" placeholder="e.g. Kishore Sagar, Kota" data-rules="required|min:3|max:120|text" data-label="Slide title"><div class="invalid-feedback"></div></div>' +
                '<div class="form-group span-2"><label class="form-label" for="slide_credit">Photo credit</label><input type="text" id="slide_credit" name="credit" class="form-control" maxlength="200" placeholder="e.g. Photo: KDA PR Cell" data-rules="max:200|text" data-label="Photo credit"><div class="invalid-feedback"></div></div>' +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Uploading…">' + icon('fa-plus') + ' <span>Add slide</span></button></div></form></div></div>',
            after: function (root) {
                if (!P.Validation) { return; }
                const set = root.querySelector('form[data-settings-form]');
                P.Validation.addCheck(set, function () {
                    const v = set.querySelector('[name="validity_days"]'), a = set.querySelector('[name="reverify_alert_days"]');
                    if (v && a && Number(a.value) >= Number(v.value)) { return { field: a, message: 'The re-verification alert must start before the validity ends.' }; }
                    return null;
                });
                const sl = root.querySelector('form[data-slides-form]');
                if (sl) {
                    P.Validation.addCheck(sl, function () {
                        return sl.querySelector('[data-slide-active]:checked') ? null : { field: null, message: 'Keep at least one slide active — the landing page needs a photo.' };
                    });
                }
                const add = root.querySelector('form[data-slide-add]');
                P.Validation.addCheck(add, function () {
                    const input = add.querySelector('[name="image"]');
                    const f = input.files && input.files[0];
                    if (!f) { return null; }
                    if (!/\.(jpe?g|png)$/i.test(f.name) || (f.type && !/^image\/(jpeg|png)$/.test(f.type))) { return { field: input, message: 'Only JPG or PNG photos are allowed.' }; }
                    if (f.size > 3 * 1024 * 1024) { return { field: input, message: 'The photo is larger than 3 MB.' }; }
                    return null;
                });
            }
        };
    });
})(window);
