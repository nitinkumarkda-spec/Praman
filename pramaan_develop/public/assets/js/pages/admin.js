/* ===== js/pages/admin.js ===== */
/* PRAMAAN — Super Admin panel (app/Views/admin/*) */
(function (window) {
    'use strict';
    const P = window.Praman;
    const e = P.e, icon = P.icon, DB = P.DB, Auth = P.Auth, L = P.Lookups;
    const PANEL = 'SUPER_ADMIN';
    const isLocked = function (u) { return !!u.locked_until && u.locked_until > DB.now(); };
    /** Permission data for Super Admin screens: catalog, panel grants, locked codes, users' own exceptions. */
    const permData = function () { return DB.app().permissions || { modules: [], grants: {}, locked: {}, overrides: [] }; };
    /** One user's exceptions: { code: 'ALLOW' | 'DENY' } */
    const overridesOf = function (userId) {
        const out = {};
        (permData().overrides || []).forEach(function (o) { if (o.user_id === userId) { out[o.permission] = o.effect; } });
        return out;
    };
    /** "+2 / −1" badge for a user with personal exceptions ('' when none). */
    const overrideBadge = function (userId) {
        const ov = overridesOf(userId), keys = Object.keys(ov);
        if (!keys.length) { return ''; }
        const allow = keys.filter(function (k) { return ov[k] === 'ALLOW'; }).length;
        return ' <span class="badge badge-warning" title="' + e('Personal permission exceptions: ' + allow + ' extra, ' + (keys.length - allow) + ' taken away') + '">' +
            icon('fa-user-shield') + ' +' + allow + ' / −' + (keys.length - allow) + '</span>';
    };

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
                Auth.can('role.create') ? '<a href="' + P.BASE + '/admin/roles/create" class="btn btn-primary">' + icon('fa-plus') + ' <span>Add Role</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search role name or code…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Role / Section') + P.th_sort(p, 'code', 'Code') + P.th_sort(p, 'sla', 'SLA (days)') +
                '<th>Mandatory</th>' + P.th_sort(p, 'users', 'Users') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (r) {
                    const on = r.is_active === 1;
                    return '<tr><td data-label="Role"><strong>' + e(r.role_name) + '</strong></td>' +
                        '<td data-label="Code"><span class="mono">' + e(r.role_code) + '</span></td><td data-label="SLA">' + r.sla_days + '</td>' +
                        '<td data-label="Mandatory">' + (r.is_mandatory ? '<span class="badge badge-gold">' + icon('fa-star') + ' Mandatory</span>' : '<span class="badge badge-muted">Optional</span>') + '</td>' +
                        '<td data-label="Users"><a href="' + P.url('/admin/users', { role_id: r.role_id }) + '">' + r.active_users + ' active</a><span class="cell-sub">' + r.users_count + ' total</span></td>' +
                        '<td data-label="Status">' + P.active_badge(r.is_active) + '</td><td class="actions"><div class="btn-group">' +
                        (Auth.can('role.edit') ? '<a href="' + P.BASE + '/admin/roles/' + r.role_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + r.role_name) + '" aria-label="' + e('Edit ' + r.role_name) + '">' + icon('fa-pen') + '</a>' : '') +
                        (Auth.can('role.toggle') ? '<form method="post" data-action="role-toggle" data-confirm="' + e(on ? 'Deactivate role "' + r.role_name + '"? It will not be used for new schemes and verification rounds.' : 'Activate role "' + r.role_name + '"?') + '" data-confirm-variant="' + (on ? 'danger' : 'success') + '">' +
                        '<input type="hidden" name="id" value="' + r.role_id + '">' +
                        (on ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-toggle-off') + '</button>'
                            : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-toggle-on') + '</button>') +
                        '</form>' : '') + '</div></td></tr>';
                }).join('') : P.empty_state(7, p.hasFilters(), 'No roles yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }, 'role.view');

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
                '<a href="' + P.BASE + '/admin/roles" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>') +
                (isEdit && usage > 0 ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This role is mapped in <strong>' + usage + '</strong> scheme(s). Existing properties keep their snapshot.</span></div>' : '') +
                '<div class="card"><div class="card-body"><form method="post" data-action="role-save" novalidate data-validate>' +
                (isEdit ? '<input type="hidden" name="id" value="' + role.role_id + '">' : '') + '<div class="form-grid">' +
                input('role_name', 'Role name', true, 'type="text" class="form-control" maxlength="100" placeholder="e.g. Engineering Cell" data-rules="required|min:3|max:100|name" data-label="Role name"', v('role_name')) +
                input('role_code', 'Role code', true, 'type="text" class="form-control mono" maxlength="40" placeholder="e.g. ENGINEERING_CELL" data-uppercase ' + (isEdit ? 'readonly' : 'data-rules="required|code" data-label="Role code"'), v('role_code'),
                    isEdit ? 'Code cannot be changed after creation.' : 'CAPITAL letters, digits and underscore. Cannot be changed later.') +
                input('sla_days', 'SLA (days to respond)', true, 'type="number" class="form-control" min="1" max="365" data-rules="required|integer|between:1,365" data-label="SLA days"', v('sla_days', 5)) +
                input('description', 'Description', false, 'type="text" class="form-control" maxlength="255" placeholder="What does this section verify?" data-rules="max:255|text" data-label="Description"', v('description')) +
                '<div class="form-group"><label class="check"><input type="checkbox" name="is_mandatory" value="1"' + (!isEdit || role.is_mandatory ? ' checked' : '') + '>' +
                '<span><strong>Mandatory</strong><br><small class="text-muted">Every scheme must map at least one user of this role.</small></span></label></div>' +
                '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (!isEdit || role.is_active ? ' checked' : '') + '>' +
                '<span><strong>Active</strong><br><small class="text-muted">Inactive roles are skipped for new schemes and rounds.</small></span></label></div>' +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update Role' : 'Create Role') + '</span></button>' +
                '<a href="' + P.BASE + '/admin/roles" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>'
        };
    }
    P.route('/admin/roles/create', PANEL, function () { return roleForm(null); }, 'role.create');
    P.route('/admin/roles/{id}/edit', PANEL, function (ctx) {
        const r = DB.find('roles', ctx.params.id);
        return r ? roleForm(r) : P.notFound('Role not found.');
    }, 'role.edit');


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
        if (q.access === 'custom') { rows = rows.filter(function (u) { return Object.keys(overridesOf(u.user_id)).length > 0; }); }
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['panel', 'role_id', 'status', 'access'],
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
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive', locked: 'Locked' }, q.status, 'All status') + '</select>' +
            (Auth.can('user.permissions') ? '<select name="access" class="form-select" aria-label="Permissions" data-auto-submit>' + L.options({ custom: 'With personal permissions' }, q.access, 'Any permissions') + '</select>' : '');
        const me = Auth.id();
        return {
            title: 'User Master',
            html: P.pageHeader(icon('fa-users') + ' User Master', 'Panel users and role users (e.g. engineer-1 under Engineering). Many users can belong to one role.',
                Auth.can('user.create') ? '<a href="' + P.BASE + '/admin/users/create" class="btn btn-primary">' + icon('fa-user-plus') + ' <span>Add User</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search name, username, emp code, mobile, email…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Name') + P.th_sort(p, 'username', 'Username') + P.th_sort(p, 'emp_code', 'Emp. Code') + P.th_sort(p, 'panel', 'Panel') +
                P.th_sort(p, 'role', 'Role') + '<th>Contact</th>' + P.th_sort(p, 'last_login', 'Last login') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (u) {
                    const self = u.user_id === me;
                    return '<tr><td data-label="Name"><strong>' + e(u.name) + '</strong>' + (self ? ' <span class="badge badge-gold">You</span>' : '') + '<span class="cell-sub">' + e(u.designation) + '</span></td>' +
                        '<td data-label="Username"><span class="mono">' + e(u.username) + '</span></td><td data-label="Emp. Code">' + e(u.emp_code) + '</td>' +
                        '<td data-label="Panel"><span class="badge badge-primary">' + e(P.PANELS[u.panel]) + '</span>' + (Auth.can('user.permissions') ? overrideBadge(u.user_id) : '') + '</td><td data-label="Role">' + e(u.role_name || '—') + '</td>' +
                        '<td data-label="Contact">' + e(u.mobile) + '<span class="cell-sub">' + e(u.email) + '</span></td>' +
                        '<td data-label="Last login">' + e(P.fmt_datetime(u.last_login_at)) + '</td><td data-label="Status">' + P.active_badge(u.is_active) +
                        (u.is_locked ? ' <span class="badge badge-danger">' + icon('fa-lock') + ' Locked</span>' : '') + '</td><td class="actions"><div class="btn-group">' +
                        (!self && u.panel !== 'SUPER_ADMIN' && u.is_active && Auth.can('user.impersonate') ?'<form method="post" data-action="impersonate"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn-switch" title="' + e('Login as ' + u.name) + '" aria-label="' + e('Login as ' + u.name) + '">' + icon('fa-right-to-bracket') + '</button></form>' : '') +
                        (Auth.can('user.edit') ? '<a href="' + P.BASE + '/admin/users/' + u.user_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + u.name) + '" aria-label="' + e('Edit ' + u.name) + '">' + icon('fa-pen') + '</a>' : '') +
                        (Auth.can('user.permissions') ? '<a href="' + P.BASE + '/admin/users/' + u.user_id + '/permissions" class="btn btn-light btn-sm btn-icon-only" title="' + e('Permissions of ' + u.name) + '" aria-label="' + e('Permissions of ' + u.name) + '">' + icon('fa-user-shield') + '</a>' : '') +
                        (u.is_locked && Auth.can('user.unlock') ? '<form method="post" data-action="user-unlock" data-confirm="' + e('Unlock account "' + u.username + '"?') + '"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn btn-sm btn-warning btn-icon-only" title="Unlock account" aria-label="Unlock account">' + icon('fa-lock-open') + '</button></form>' : '') +
                        (!self && Auth.can('user.toggle') ? '<form method="post" data-action="user-toggle" data-confirm="' + e(u.is_active ? 'Deactivate "' + u.name + '"? They will not be able to login.' : 'Activate "' + u.name + '"?') + '" data-confirm-variant="' + (u.is_active ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="id" value="' + u.user_id + '">' +
                            (u.is_active ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-user-slash') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-user-check') + '</button>') + '</form>' : '') +
                        '</div></td></tr>';
                }).join('') : P.empty_state(9, p.hasFilters(), 'No users yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }, 'user.view');

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
            '<a href="' + P.BASE + '/admin/users" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>' +
            (isEdit && Auth.can('user.permissions') ? '<a href="' + P.BASE + '/admin/users/' + user.user_id + '/permissions" class="btn btn-light">' + icon('fa-user-shield') + ' <span>Permissions</span></a>' : '')) +
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
            '<a href="' + P.BASE + '/admin/users" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>';
        if (isEdit) {
            html += '<div>' + (Auth.can('user.reset_password') ? '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-unlock-keyhole') + ' Reset password</h3></div><div class="card-body">' +
                '<p class="text-muted mb-3">Set a temporary password. The user must change it on next login. This also unlocks the account.</p>' +
                '<form method="post" data-action="user-reset-password" novalidate data-validate autocomplete="off" data-confirm="' + e('Reset the password of "' + user.name + '"?') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="id" value="' + user.user_id + '">' + pw('reset_password', 'New temporary password', 'required|password') + '<div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="reset_confirm">Confirm password <span class="required">*</span></label>' +
                '<input type="password" id="reset_confirm" name="reset_confirm" class="form-control" maxlength="72" autocomplete="new-password" data-rules="required|same:reset_password" data-label="Confirm new password"><div class="invalid-feedback"></div></div>' +
                '<button type="submit" class="btn btn-warning" data-loading-text="Resetting…">' + icon('fa-rotate') + ' <span>Reset password</span></button></form></div></div>' : '') +
                '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-circle-info') + ' Account info</h3></div><div class="card-body"><dl class="detail-list">' +
                '<dt>Status</dt><dd>' + P.active_badge(user.is_active) + '</dd>' +
                '<dt>Last login</dt><dd>' + e(P.fmt_datetime(user.last_login_at)) + (user.last_login_ip ? ' · ' + e(user.last_login_ip) : '') + '</dd>' +
                '<dt>Password changed</dt><dd>' + e(P.fmt_datetime(user.password_changed_at)) + '</dd>' +
                '<dt>Locked until</dt><dd>' + e(P.fmt_datetime(user.locked_until)) + '</dd>' +
                '<dt>Created</dt><dd>' + e(P.fmt_datetime(user.created_at)) + '</dd></dl></div></div></div>';
        }
        return { title: isEdit ? 'Edit User' : 'Add User', html: html + '</div>' };
    }
    P.route('/admin/users/create', PANEL, function () { return userForm(null); }, 'user.create');
    P.route('/admin/users/{id}/edit', PANEL, function (ctx) {
        const u = DB.find('users', ctx.params.id);
        return u ? userForm(u) : P.notFound('User not found.');
    }, 'user.edit');

    /* ================= One user's permissions: Inherit / Allow / Deny on top of the panel ================= */
    P.route('/admin/users/{id}/permissions', PANEL, function (ctx) {
        const user = DB.find('users', ctx.params.id);
        if (!user) { return P.notFound('User not found.'); }
        const data = permData();
        const panel = user.panel, panelName = P.PANELS[panel];
        const grants = data.grants[panel] || [];
        const locked = data.locked[panel] || [];
        const ov = overridesOf(user.user_id);
        const role = user.role_id ? DB.find('roles', user.role_id) : null;
        const isSelf = user.user_id === Auth.id();
        const modules = data.modules.map(function (m) {
            return Object.assign({}, m, { items: m.items.filter(function (it) { return it.panels.indexOf(panel) !== -1; }) });
        }).filter(function (m) { return m.items.length; });
        const total = modules.reduce(function (n, m) { return n + m.items.length; }, 0);

        const yes = '<span class="badge badge-success">' + icon('fa-check') + ' Yes</span>';
        const no = '<span class="badge badge-muted">' + icon('fa-minus') + ' No</span>';
        const choice = function (code, current) {
            const opt = function (val, text, ic, cls) {
                return '<label class="yn-opt ' + cls + '"><input type="radio" name="ov[' + e(code) + ']" value="' + val + '"' + (current === val ? ' checked' : '') + ' data-perm-ov>' +
                    '<span>' + icon(ic) + ' ' + text + '</span></label>';
            };
            return '<div class="yn perm-ov" role="radiogroup" aria-label="' + e(code) + '">' +
                opt('inherit', 'Panel', 'fa-layer-group', 'yn-no') + opt('ALLOW', 'Allow', 'fa-check', 'yn-allow') + opt('DENY', 'Deny', 'fa-ban', 'yn-deny') + '</div>';
        };
        const rows = modules.map(function (m) {
            return '<tbody data-perm-group><tr class="perm-module"><th colspan="4">' + icon(m.icon) + ' ' + e(m.label) + '</th></tr>' +
                m.items.map(function (it) {
                    const fixed = locked.indexOf(it.code) !== -1;
                    const base = grants.indexOf(it.code) !== -1 || fixed;
                    return '<tr data-perm-row data-code="' + e(it.code) + '" data-base="' + (base ? 1 : 0) + '" data-search="' + e((it.label + ' ' + it.code + ' ' + m.label).toLowerCase()) + '">' +
                        '<td data-label="Permission">' + e(it.label) + '<span class="cell-sub mono">' + e(it.code) + '</span></td>' +
                        '<td data-label="' + e(panelName) + ' panel">' + (base ? yes : no) + '</td>' +
                        '<td data-label="This user">' + (fixed ? '<span class="badge badge-gold">' + icon('fa-lock') + ' Always on</span>' : choice(it.code, ov[it.code] || 'inherit')) + '</td>' +
                        '<td data-label="Result" data-perm-result></td></tr>';
                }).join('') + '</tbody>';
        }).join('');

        return {
            title: 'Permissions — ' + user.name,
            html: P.pageHeader(icon('fa-user-shield') + ' Permissions — ' + e(user.name),
                e(user.username + ' · ' + panelName + ' panel' + (role ? ' · ' + role.role_name : '') + ' · ' + user.designation),
                '<a href="' + P.BASE + '/admin/users" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to users</span></a>' +
                (Auth.can('user.edit') ? '<a href="' + P.BASE + '/admin/users/' + user.user_id + '/edit" class="btn btn-light">' + icon('fa-pen') + ' <span>Edit user</span></a>' : '') +
                (Auth.can('permission.view') ? '<a href="' + P.BASE + '/admin/permissions" class="btn btn-light">' + icon('fa-table-cells') + ' <span>Panel permissions</span></a>' : '')) +
                '<div class="alert alert-info">' + icon('fa-circle-info') + '<span><strong>Panel</strong> = same as every ' + e(panelName) + ' user (Super Admin → Permissions). ' +
                '<strong>Allow</strong> gives this user an extra permission, <strong>Deny</strong> takes one away — only for this user. Changing the user\'s panel removes these exceptions.</span></div>' +
                (user.is_active ? '' : '<div class="alert alert-warning">' + icon('fa-user-slash') + '<span>This user is inactive and cannot login; the permissions apply once the account is active again.</span></div>') +
                '<div class="stat-grid perm-stats">' +
                P.statCard('navy', 'fa-key', 0, 'Effective permissions', 'of ' + total + ' for this panel') +
                P.statCard('green', 'fa-circle-plus', 0, 'Extra (Allow)', 'beyond the panel') +
                P.statCard('red', 'fa-circle-minus', 0, 'Taken away (Deny)', 'from the panel') +
                P.statCard('gold', 'fa-pen-to-square', 0, 'Unsaved changes', 'not saved yet') + '</div>' +
                '<form method="post" data-action="user-permission-save" data-user-perm-form data-confirm="' + e('Save the permissions of ' + user.name + '?') + '">' +
                '<input type="hidden" name="id" value="' + user.user_id + '">' +
                '<div class="card"><div class="toolbar perm-toolbar"><div class="toolbar-search">' + icon('fa-magnifying-glass') +
                '<input type="search" class="form-control" placeholder="Search permission or module…" aria-label="Search permissions" data-perm-search></div>' +
                '<label class="check"><input type="checkbox" data-perm-only-changed><span>Only exceptions</span></label>' +
                '<button type="button" class="btn btn-light btn-sm" data-perm-reset>' + icon('fa-rotate-left') + ' <span>All as panel</span></button></div>' +
                '<div class="table-wrap"><table class="table table-stack table-top perm-table"><thead><tr><th>Permission</th><th>' + e(panelName) + ' panel</th><th>This user</th><th>Result</th></tr></thead>' +
                (rows || '<tbody>' + P.empty_state(4, false, 'No permissions for this panel') + '</tbody>') +
                '<tbody data-perm-empty hidden>' + P.empty_state(4, true, 'No permission matches') + '</tbody></table></div></div>' +
                '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Save permissions</span></button>' +
                '<button type="button" class="btn btn-light btn-lg" data-perm-discard>' + icon('fa-xmark') + ' <span>Discard changes</span></button></div></form>',
            after: function (root) {
                const form = root.querySelector('form[data-user-perm-form]');
                const stats = root.querySelectorAll('.perm-stats .stat-value');
                const search = form.querySelector('[data-perm-search]');
                const onlyChanged = form.querySelector('[data-perm-only-changed]');
                const saved = {};
                form.querySelectorAll('[data-perm-row]').forEach(function (tr) { saved[tr.dataset.code] = ov[tr.dataset.code] || 'inherit'; });
                const valueOf = function (tr) { const c = tr.querySelector('[data-perm-ov]:checked'); return c ? c.value : 'inherit'; };
                function refresh() {
                    let eff = 0, allow = 0, deny = 0, dirty = 0;
                    const term = search.value.trim().toLowerCase();
                    form.querySelectorAll('[data-perm-group]').forEach(function (g) {
                        let shown = 0;
                        g.querySelectorAll('[data-perm-row]').forEach(function (tr) {
                            const v = valueOf(tr), base = tr.dataset.base === '1';
                            const on = v === 'ALLOW' || (v === 'inherit' && base);
                            eff += on ? 1 : 0;
                            allow += v === 'ALLOW' ? 1 : 0;
                            deny += v === 'DENY' ? 1 : 0;
                            dirty += saved[tr.dataset.code] !== v ? 1 : 0;
                            tr.querySelector('[data-perm-result]').innerHTML = (on ? '<span class="badge badge-success">' + icon('fa-circle-check') + ' Can</span>' : '<span class="badge badge-danger">' + icon('fa-circle-xmark') + ' Cannot</span>') +
                                (v === 'inherit' ? '' : '<span class="cell-sub">' + (v === 'ALLOW' ? (base ? 'already in panel' : 'extra for this user') : (base ? 'taken away from this user' : 'panel does not have it either')) + '</span>');
                            tr.classList.toggle('row-success', v === 'ALLOW');
                            tr.classList.toggle('row-danger', v === 'DENY');
                            tr.classList.toggle('perm-dirty', saved[tr.dataset.code] !== v);
                            const show = (!term || tr.dataset.search.indexOf(term) !== -1) && (!onlyChanged.checked || v !== 'inherit');
                            tr.hidden = !show;
                            shown += show ? 1 : 0;
                        });
                        g.hidden = !shown;
                    });
                    const any = form.querySelector('[data-perm-group]:not([hidden])');
                    form.querySelector('[data-perm-empty]').hidden = !!any || !form.querySelector('[data-perm-group]');
                    [eff, allow, deny, dirty].forEach(function (n, i) { if (stats[i]) { stats[i].textContent = P.fmt_num(n); } });
                    form.classList.toggle('is-dirty', dirty > 0);
                }
                form.addEventListener('change', function (ev) {
                    const box = ev.target;
                    if (isSelf && box.name === 'ov[user.permissions]' && box.value === 'DENY') {
                        P.flash('warning', 'You cannot take away your own right to change user permissions.');
                        form.querySelector('[name="ov[user.permissions]"][value="' + saved['user.permissions'] + '"]').checked = true;
                    }
                    refresh();
                });
                search.addEventListener('input', refresh);
                search.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
                form.querySelector('[data-perm-reset]').addEventListener('click', function () {
                    form.querySelectorAll('[data-perm-ov][value="inherit"]').forEach(function (r) { r.checked = true; });
                    refresh();
                });
                form.querySelector('[data-perm-discard]').addEventListener('click', function () {
                    form.querySelectorAll('[data-perm-row]').forEach(function (tr) {
                        const r = tr.querySelector('[data-perm-ov][value="' + saved[tr.dataset.code] + '"]');
                        if (r) { r.checked = true; }
                    });
                    refresh();
                });
                refresh();
            }
        };
    }, 'user.permissions');

    /* ================= App Settings (branding, landing page, sign-in popup, limits, slides) ================= */
    P.route('/admin/settings', PANEL, function () {
        const app = DB.app();
        const val = app.settings || {};
        const groups = app.settings_groups || {};
        const meta = app.settings_meta || [];
        const canEdit = Auth.can('settings.edit');
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
                '<td class="actions">' + (canEdit ? '<button type="submit" form="slide-del-' + id + '" class="btn btn-sm btn-light text-danger btn-icon-only" title="Delete slide" aria-label="Delete slide">' + icon('fa-trash-can') + '</button>' : '') + '</td></tr>';
        }).join('');
        const delForms = slides.map(function (s) {
            return '<form method="post" data-action="slide-delete" id="slide-del-' + s.slide_id + '" hidden data-confirm="' + e('Delete slide "' + s.title + '" from the landing page?') + '" data-confirm-variant="danger" data-confirm-ok="Yes, delete">' +
                '<input type="hidden" name="id" value="' + s.slide_id + '"></form>';
        }).join('');

        return {
            title: 'App Settings',
            html: P.pageHeader(icon('fa-sliders') + ' App Settings', 'Name, tagline, landing page, sign-in popup, security and verification limits — changes apply to every screen immediately.') +
                (canEdit ? '' : '<div class="alert alert-info">' + icon('fa-eye') + '<span>View only — you do not have permission to change the settings.</span></div>') +
                '<form method="post" data-action="settings-save" novalidate data-validate data-settings-form data-confirm="Save the settings? They apply to all users immediately.">' + cards +
                (canEdit ? '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Save settings</span></button></div>' : '') + '</form>' +
                '<div class="card" id="slides"><div class="card-header"><h3 class="card-title">' + icon('fa-images') + ' Landing page slides</h3><span class="badge badge-info">' +
                slides.filter(function (s) { return s.is_active; }).length + ' active</span></div>' +
                '<form method="post" data-action="slides-save" novalidate data-validate data-slides-form><div class="table-wrap"><table class="table table-stack table-top"><thead><tr><th>Photo</th><th>Title</th><th>Photo credit</th><th>Order</th><th>Active</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (slideRows || P.empty_state(6, false, 'No slides yet')) + '</tbody></table></div>' +
                (slides.length && canEdit ?'<div class="card-body"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>Save slides</span></button></div>' : '') + '</form>' + delForms + '</div>' +
                (canEdit ? '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-cloud-arrow-up') + ' Add a slide</h3></div><div class="card-body">' +
                '<form method="post" data-action="slide-add" novalidate data-validate data-slide-add enctype="multipart/form-data"><div class="form-grid">' +
                '<div class="form-group"><label class="form-label" for="slide_image">Photo (JPG / PNG, max 3 MB, at least 600 × 400) <span class="required">*</span></label>' +
                '<input type="file" id="slide_image" name="image" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-rules="required" data-label="the photo"><div class="invalid-feedback"></div></div>' +
                '<div class="form-group"><label class="form-label" for="slide_title">Title <span class="required">*</span></label><input type="text" id="slide_title" name="title" class="form-control" maxlength="120" placeholder="e.g. Kishore Sagar, Kota" data-rules="required|min:3|max:120|text" data-label="Slide title"><div class="invalid-feedback"></div></div>' +
                '<div class="form-group span-2"><label class="form-label" for="slide_credit">Photo credit</label><input type="text" id="slide_credit" name="credit" class="form-control" maxlength="200" placeholder="e.g. Photo: KDA PR Cell" data-rules="max:200|text" data-label="Photo credit"><div class="invalid-feedback"></div></div>' +
                '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Uploading…">' + icon('fa-plus') + ' <span>Add slide</span></button></div></form></div></div>' : ''),
            after: function (root) {
                if (!canEdit) {
                    root.querySelectorAll('form[data-settings-form] input, form[data-slides-form] input').forEach(function (el) { el.disabled = true; });
                    return;
                }
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
    }, 'settings.view');

    /* ================= Searchable dropdown (same idea as FSelect + option sheet in the mobile app) =================
       groups: [{ label, items: [{ value, label, sub, icon, badge }] }] — onPick(value) runs when an option is chosen. */
    function comboHtml(id, label, current, groups, placeholder) {
        let sel = null;
        groups.forEach(function (g) { g.items.forEach(function (it) { if (it.value === current) { sel = it; } }); });
        return '<div class="combo" data-combo id="' + id + '">' +
            '<button type="button" class="combo-trigger" aria-haspopup="listbox" aria-expanded="false" aria-label="' + e(label) + '">' +
            (sel ? '<span class="combo-value">' + icon(sel.icon || 'fa-circle') + ' <strong>' + e(sel.label) + '</strong>' + (sel.sub ? '<small>' + e(sel.sub) + '</small>' : '') + '</span>'
                : '<span class="combo-value is-placeholder">' + e(placeholder || 'Select') + '</span>') + icon('fa-chevron-down combo-caret') + '</button>' +
            '<div class="combo-panel" hidden><div class="combo-search">' + icon('fa-magnifying-glass') +
            '<input type="search" class="form-control" placeholder="Type to search…" aria-label="' + e('Search ' + label) + '" autocomplete="off" data-combo-input></div>' +
            '<ul class="combo-list" role="listbox" aria-label="' + e(label) + '">' + groups.map(function (g) {
                return '<li class="combo-group" role="presentation">' + e(g.label) + '</li>' + g.items.map(function (it) {
                    return '<li class="combo-option' + (it.value === current ? ' is-selected' : '') + '" role="option" tabindex="-1" aria-selected="' + (it.value === current) + '" data-value="' + e(it.value) + '"' +
                        ' data-search="' + e((it.label + ' ' + (it.sub || '') + ' ' + g.label).toLowerCase()) + '">' + icon(it.icon || 'fa-circle', 'combo-icon') +
                        '<span class="combo-text"><strong>' + e(it.label) + '</strong>' + (it.sub ? '<small>' + e(it.sub) + '</small>' : '') + '</span>' +
                        (it.badge ? '<span class="badge badge-muted">' + e(it.badge) + '</span>' : '') + (it.value === current ? icon('fa-check combo-tick') : '') + '</li>';
                }).join('');
            }).join('') + '<li class="combo-empty" hidden>No match</li></ul></div></div>';
    }
    function initCombo(box, onPick) {
        const trigger = box.querySelector('.combo-trigger'), panel = box.querySelector('.combo-panel');
        const input = box.querySelector('[data-combo-input]'), list = box.querySelector('.combo-list');
        const options = function () { return Array.prototype.filter.call(list.querySelectorAll('.combo-option'), function (o) { return !o.hidden; }); };
        let active = -1;
        function mark(i) {
            const opts = options();
            opts.forEach(function (o, j) { o.classList.toggle('is-active', j === i); });
            active = i;
            if (opts[i]) { opts[i].scrollIntoView({ block: 'nearest' }); }
        }
        function filter() {
            const q = input.value.trim().toLowerCase();
            list.querySelectorAll('.combo-option').forEach(function (o) { o.hidden = q !== '' && o.dataset.search.indexOf(q) === -1; });
            list.querySelectorAll('.combo-group').forEach(function (g) {
                let n = g.nextElementSibling, any = false;
                while (n && !n.classList.contains('combo-group')) { if (n.classList.contains('combo-option') && !n.hidden) { any = true; } n = n.nextElementSibling; }
                g.hidden = !any;
            });
            list.querySelector('.combo-empty').hidden = options().length > 0;
            mark(options().length ? 0 : -1);
        }
        function open(state) {
            panel.hidden = !state;
            box.classList.toggle('is-open', state);
            trigger.setAttribute('aria-expanded', String(state));
            if (state) { input.value = ''; filter(); input.focus(); }
        }
        function pick(o) { if (o) { open(false); onPick(o.dataset.value); } }
        trigger.addEventListener('click', function () { open(panel.hidden); });
        input.addEventListener('input', filter);
        input.addEventListener('keydown', function (ev) {
            const n = options().length;
            if (ev.key === 'ArrowDown') { ev.preventDefault(); mark(n ? (active + 1) % n : -1); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); mark(n ? (active - 1 + n) % n : -1); }
            else if (ev.key === 'Enter') { ev.preventDefault(); pick(options()[active]); }
            else if (ev.key === 'Escape') { open(false); trigger.focus(); }
        });
        list.addEventListener('click', function (ev) { pick(ev.target.closest('.combo-option')); });
        list.addEventListener('mousemove', function (ev) {
            const o = ev.target.closest('.combo-option');
            if (o) { mark(options().indexOf(o)); }
        });
        const outside = function (ev) {
            if (!document.body.contains(box)) { document.removeEventListener('pointerdown', outside); return; }
            if (!box.contains(ev.target)) { open(false); }
        };
        document.addEventListener('pointerdown', outside);
    }

    /* ================= Permission Manager: pick a role, give it what it needs ================= */
    P.route('/admin/permissions', PANEL, function (ctx) {
        const data = permData();
        const panels = Object.keys(P.PANELS);
        const canEdit = Auth.can('permission.edit');
        const view = ctx.query.view === 'matrix' ? 'matrix' : 'role';
        const role = panels.indexOf(ctx.query.role) !== -1 ? ctx.query.role : panels[0];
        const has = function (panel, code) { return (data.grants[panel] || []).indexOf(code) !== -1; };
        const locked = function (panel, code) { return (data.locked[panel] || []).indexOf(code) !== -1; };
        const applicable = function (panel) {
            return data.modules.map(function (m) { return Object.assign({}, m, { items: m.items.filter(function (it) { return it.panels.indexOf(panel) !== -1; }) }); })
                .filter(function (m) { return m.items.length; });
        };
        const countFor = function (panel) {
            const items = [].concat.apply([], applicable(panel).map(function (m) { return m.items; }));
            return { total: items.length, on: items.filter(function (it) { return has(panel, it.code) || locked(panel, it.code); }).length };
        };
        const usersOf = function (panel) { return DB.where('users', { panel: panel }); };
        const exceptionUsers = new Set((data.overrides || []).map(function (o) { return o.user_id; }));

        // the dropdown: roles (panels) first, then users for a personal exception
        const groups = [{ label: 'Roles', items: panels.map(function (p) {
            const c = countFor(p), n = usersOf(p).length;
            return { value: 'role:' + p, label: P.PANELS[p], sub: c.on + ' of ' + c.total + ' permissions · ' + n + ' user' + (n === 1 ? '' : 's'), icon: 'fa-user-shield' };
        }) }];
        if (Auth.can('user.permissions')) {
            groups.push({ label: 'One user (personal exceptions)', items: DB.all('users').filter(function (u) { return u.is_active; })
                .sort(P.by(function (u) { return u.name; })).map(function (u) {
                    return { value: 'user:' + u.user_id, label: u.name, sub: u.username + ' · ' + P.PANELS[u.panel] + ' · ' + u.designation, icon: 'fa-user',
                        badge: exceptionUsers.has(u.user_id) ? 'exceptions' : '' };
                }) });
        }
        const tabs = '<div class="perm-tabs" role="tablist">' +
            '<a role="tab" class="perm-tab' + (view === 'role' ? ' active' : '') + '" aria-selected="' + (view === 'role') + '" href="' + P.url('/admin/permissions', { role: role }) + '">' + icon('fa-user-shield') + ' By role</a>' +
            '<a role="tab" class="perm-tab' + (view === 'matrix' ? ' active' : '') + '" aria-selected="' + (view === 'matrix') + '" href="' + P.url('/admin/permissions', { view: 'matrix' }) + '">' + icon('fa-table-cells') + ' All roles side by side</a></div>';
        const exceptionsNote = (function () {
            const n = exceptionUsers.size;
            return n && Auth.can('user.permissions') ? '<div class="alert alert-warning">' + icon('fa-user-shield') + '<span><strong>' + n + ' user(s)</strong> have personal exceptions on top of their role. ' +
                '<a href="' + P.url('/admin/users', { access: 'custom' }) + '">Show them</a></span></div>' : '';
        })();
        const readOnly = canEdit ? '' : '<div class="alert alert-info">' + icon('fa-eye') + '<span>View only — you do not have permission to change permissions.</span></div>';
        const saveBar = function (text) {
            return canEdit ? '<div class="form-actions sticky-actions"><button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + text + '</span></button>' +
                '<button type="button" class="btn btn-light btn-lg" data-perm-discard>' + icon('fa-xmark') + ' <span>Discard changes</span></button>' +
                '<span class="perm-dirty-note" data-perm-dirty hidden>' + icon('fa-pen-to-square') + ' <span></span></span></div>' : '';
        };
        const box = function (panel, it) {
            const fixed = locked(panel, it.code), on = has(panel, it.code) || fixed;
            return '<input type="checkbox" name="perm_' + panel + '[]" value="' + e(it.code) + '"' + (on ? ' checked' : '') + (fixed || !canEdit ? ' disabled' : '') +
                ' data-perm-module="' + e(it.code.split('.')[0]) + '" data-perm-panel="' + panel + '" data-saved="' + (on ? 1 : 0) + '" aria-label="' + e(it.label + ' — ' + P.PANELS[panel]) + '">';
        };

        let body;
        if (view === 'role') {
            const mods = applicable(role);
            const c = countFor(role);
            const roleUsers = usersOf(role);
            const fact = function (ic, html, href) {
                return href ? '<a class="perm-fact" href="' + href + '">' + icon(ic) + '<span>' + html + '</span></a>' : '<span class="perm-fact">' + icon(ic) + '<span>' + html + '</span></span>';
            };
            const seg = function (val, text, n) {
                return '<button type="button" class="perm-seg-btn' + (val === '' ? ' is-active' : '') + '" aria-pressed="' + (val === '') + '" data-perm-show="' + val + '">' +
                    e(text) + ' <span class="perm-seg-count" data-perm-seg-count="' + val + '">' + n + '</span></button>';
            };
            body = '<div class="card perm-picker"><div class="card-body perm-picker-row"><div class="perm-picker-field"><label class="form-label" for="perm-role">Role</label>' +
                comboHtml('perm-role', 'Role', 'role:' + role, groups, 'Select a role') + '</div>' +
                '<div class="perm-role-facts">' +
                fact('fa-key', '<strong data-perm-count>' + c.on + '</strong> / ' + c.total + ' permissions') +
                fact('fa-users', '<strong>' + roleUsers.length + '</strong> user' + (roleUsers.length === 1 ? '' : 's'), P.url('/admin/users', { panel: role })) +
                fact('fa-user-gear', '<strong>' + roleUsers.filter(function (u) { return exceptionUsers.has(u.user_id); }).length + '</strong> with personal exceptions') +
                '</div></div></div>' +
                '<form method="post" data-action="permission-save" data-perm-form data-confirm="' + e('Save the permissions of the ' + P.PANELS[role] + ' role? They apply to all its users.') + '">' +
                '<input type="hidden" name="panels[]" value="' + role + '">' +
                '<div class="card"><div class="toolbar perm-toolbar"><div class="toolbar-search">' + icon('fa-magnifying-glass') +
                '<input type="search" class="form-control" placeholder="Search a permission — e.g. add user, scheme, audit…" aria-label="Search permissions" data-perm-search></div>' +
                '<div class="perm-seg" role="group" aria-label="Show">' + seg('', 'All', c.total) + seg('on', 'Given', c.on) + seg('off', 'Not given', c.total - c.on) + '</div>' +
                (canEdit ? '<div class="toolbar-actions perm-bulk"><button type="button" class="btn btn-light btn-sm" data-perm-all="1">' + icon('fa-square-check') + ' <span>Give all</span></button>' +
                    '<button type="button" class="btn btn-light btn-sm" data-perm-all="0">' + icon('fa-square') + ' <span>Remove all</span></button></div>' : '') + '</div>' +
                '<div class="card-body perm-modules">' + (mods.length ? mods.map(function (m) {
                    const on = m.items.filter(function (it) { return has(role, it.code) || locked(role, it.code); }).length;
                    return '<section class="perm-mod" data-perm-group><header class="perm-mod-head"><h3>' + icon(m.icon) + ' ' + e(m.label) + '</h3>' +
                        '<span class="badge badge-info" data-perm-mod-count>' + on + '/' + m.items.length + '</span>' +
                        (canEdit ? '<label class="check perm-mod-all"><input type="checkbox" data-perm-mod-all' + (on === m.items.length ? ' checked' : '') + '><span>All</span></label>' : '') + '</header>' +
                        '<ul class="perm-items">' + m.items.map(function (it) {
                            const fixed = locked(role, it.code);
                            return '<li data-perm-row data-search="' + e((it.label + ' ' + it.code + ' ' + m.label).toLowerCase()) + '"><label class="check">' + box(role, it) +
                                '<span><strong>' + e(it.label) + '</strong>' + (fixed ? ' <span class="badge badge-gold">' + icon('fa-lock') + ' Always on</span>' : '') +
                                '<small class="mono">' + e(it.code) + '</small></span></label></li>';
                        }).join('') + '</ul></section>';
                }).join('') : '<p class="text-muted">This role has no permissions to set.</p>') +
                '<p class="text-muted perm-none" data-perm-empty hidden>' + icon('fa-magnifying-glass') + ' No permission matches your search.</p></div></div>' +
                saveBar('Save ' + P.PANELS[role] + ' permissions') + '</form>';
        } else {
            const rows = data.modules.map(function (m) {
                return '<tbody data-perm-group><tr class="perm-module"><th colspan="' + (panels.length + 1) + '">' + icon(m.icon) + ' ' + e(m.label) + '</th></tr>' +
                    m.items.map(function (it) {
                        return '<tr data-perm-row data-search="' + e((it.label + ' ' + it.code + ' ' + m.label).toLowerCase()) + '"><td data-label="Permission">' + e(it.label) + '<span class="cell-sub mono">' + e(it.code) + '</span></td>' +
                            panels.map(function (p) {
                                return it.panels.indexOf(p) === -1 ? '<td data-label="' + e(P.PANELS[p]) + '" class="text-center text-muted" title="Not used by this role">—</td>'
                                    : '<td data-label="' + e(P.PANELS[p]) + '" class="text-center"><label class="check perm-check">' + box(p, it) + '</label></td>';
                            }).join('') + '</tr>';
                    }).join('') + '</tbody>';
            }).join('');
            body = '<form method="post" data-action="permission-save" data-perm-form data-confirm="Save the permissions of all roles? They apply to all their users.">' +
                panels.map(function (p) { return '<input type="hidden" name="panels[]" value="' + p + '">'; }).join('') +
                '<div class="card"><div class="toolbar perm-toolbar"><div class="toolbar-search">' + icon('fa-magnifying-glass') +
                '<input type="search" class="form-control" placeholder="Search a permission…" aria-label="Search permissions" data-perm-search></div></div>' +
                '<div class="table-wrap"><table class="table table-stack table-top perm-table"><thead><tr><th>Permission</th>' +
                panels.map(function (p) { return '<th class="text-center"><a href="' + P.url('/admin/permissions', { role: p }) + '">' + e(P.PANELS[p]) + '</a></th>'; }).join('') + '</tr></thead>' +
                (rows || '<tbody>' + P.empty_state(panels.length + 1, false, 'No permissions defined') + '</tbody>') + '</table></div>' +
                '<p class="text-muted perm-none" data-perm-empty hidden>' + icon('fa-magnifying-glass') + ' No permission matches your search.</p></div>' +
                saveBar('Save all roles') + '</form>';
        }

        return {
            title: 'Permissions',
            html: P.pageHeader(icon('fa-user-shield') + ' Permission Manager', 'Pick a role and give it exactly the permissions it needs. Menu items, screens and buttons follow them, and the server checks them on every action.') +
                readOnly + exceptionsNote + tabs + body,
            after: function (root) {
                const combo = root.querySelector('[data-combo]');
                if (combo) {
                    initCombo(combo, function (v) {
                        const kv = v.split(':');
                        if (kv[0] === 'user') { P.go('/admin/users/' + kv[1] + '/permissions'); }
                        else { P.go('/admin/permissions', { role: kv[1] }); }
                    });
                }
                const form = root.querySelector('form[data-perm-form]');
                const search = form.querySelector('[data-perm-search]');
                let mode = ''; // '' all, 'on' given, 'off' not given
                const boxes = function () { return form.querySelectorAll('input[data-perm-module]'); };
                function refresh() {
                    const term = search.value.trim().toLowerCase();
                    let anyShown = false;
                    form.querySelectorAll('[data-perm-group]').forEach(function (g) {
                        let shown = 0;
                        g.querySelectorAll('[data-perm-row]').forEach(function (r) {
                            const b = r.querySelector('input[data-perm-module]');
                            const ok = (!term || r.dataset.search.indexOf(term) !== -1) && (!mode || !b || (mode === 'on') === b.checked);
                            r.hidden = !ok;
                            shown += ok ? 1 : 0;
                        });
                        g.hidden = !shown;
                        anyShown = anyShown || shown > 0;
                        const all = g.querySelectorAll('input[data-perm-module]');
                        const on = Array.prototype.filter.call(all, function (b) { return b.checked; }).length;
                        const cnt = g.querySelector('[data-perm-mod-count]');
                        if (cnt) { cnt.textContent = on + '/' + all.length; }
                        const modAll = g.querySelector('[data-perm-mod-all]');
                        if (modAll) { modAll.checked = on === all.length; modAll.indeterminate = on > 0 && on < all.length; }
                    });
                    form.querySelector('[data-perm-empty]').hidden = anyShown || !form.querySelector('[data-perm-group]');
                    let given = 0, dirty = 0;
                    boxes().forEach(function (b) {
                        given += b.checked ? 1 : 0;
                        const changed = (b.dataset.saved === '1') !== b.checked;
                        dirty += changed ? 1 : 0;
                        const row = b.closest('[data-perm-row]');
                        if (row && view === 'role') { row.classList.toggle('perm-dirty', changed); }
                    });
                    const count = root.querySelector('[data-perm-count]');
                    if (count) { count.textContent = given; }
                    const segN = { '': boxes().length, on: given, off: boxes().length - given };
                    form.querySelectorAll('[data-perm-seg-count]').forEach(function (s) { s.textContent = segN[s.dataset.permSegCount]; });
                    const note = form.querySelector('[data-perm-dirty]');
                    if (note) { note.hidden = !dirty; note.querySelector('span').textContent = dirty + ' unsaved change' + (dirty === 1 ? '' : 's'); }
                    form.classList.toggle('is-dirty', dirty > 0);
                }
                // a module's "view" is needed for its other actions; without "view" the others go too
                function linkView(b) {
                    const peers = form.querySelectorAll('input[data-perm-module="' + b.dataset.permModule + '"][data-perm-panel="' + b.dataset.permPanel + '"]:not(:disabled)');
                    const isView = /\.view$/.test(b.value);
                    peers.forEach(function (p) {
                        if (isView && !b.checked) { p.checked = false; }
                        if (!isView && b.checked && /\.view$/.test(p.value)) { p.checked = true; }
                    });
                }
                form.addEventListener('change', function (ev) {
                    const t = ev.target;
                    if (t.matches('[data-perm-mod-all]')) {
                        t.closest('[data-perm-group]').querySelectorAll('input[data-perm-module]:not(:disabled)').forEach(function (b) { b.checked = t.checked; });
                    } else if (t.matches('input[data-perm-module]')) {
                        linkView(t);
                    }
                    refresh();
                });
                form.addEventListener('click', function (ev) {
                    const segBtn = ev.target.closest('[data-perm-show]');
                    if (segBtn) {
                        mode = segBtn.dataset.permShow;
                        form.querySelectorAll('[data-perm-show]').forEach(function (b) { b.classList.toggle('is-active', b === segBtn); b.setAttribute('aria-pressed', String(b === segBtn)); });
                        refresh();
                    }
                    const all = ev.target.closest('[data-perm-all]');
                    if (all) { boxes().forEach(function (b) { if (!b.disabled) { b.checked = all.dataset.permAll === '1'; } }); refresh(); }
                    if (ev.target.closest('[data-perm-discard]')) { boxes().forEach(function (b) { b.checked = b.dataset.saved === '1'; }); refresh(); }
                });
                search.addEventListener('input', refresh);
                search.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); } });
                refresh();
            }
        };
    }, 'permission.view');
})(window);
