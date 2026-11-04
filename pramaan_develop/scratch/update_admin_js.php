<?php
$adminJsPath = __DIR__ . '/../public/assets/js/pages/admin.js';
$content = file_get_contents($adminJsPath);
$norm = str_replace("\r\n", "\n", $content);

$oldCode = <<<JS
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['role_id', 'status', 'access'],
            search: function (u) { return [u.name, u.username, u.emp_code, u.mobile, u.email, u.designation]; },
            sortable: {
                name: function (u) { return u.name; }, username: function (u) { return u.username; }, emp_code: function (u) { return u.emp_code; },
                role: function (u) { return u.role_name; }, last_login: function (u) { return u.last_login_at; }
            }
        });
        const roles = {};
        DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).forEach(function (r) { roles[r.role_id] = r.role_name; });
        const filters = 
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
                P.th_sort(p, 'name', 'Name') + P.th_sort(p, 'username', 'Username') + P.th_sort(p, 'emp_code', 'Emp. Code') + P.th_sort(p, 'role', 'Role') + '<th>Contact</th>' + P.th_sort(p, 'last_login', 'Last login') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (u) {
                    const self = u.user_id === me;
                    return '<tr><td data-label="Name"><strong>' + e(u.name) + '</strong>' + (self ? ' <span class="badge badge-gold">You</span>' : '') + '<span class="cell-sub">' + e(u.designation) + '</span></td>' +
                        '<td data-label="Username"><span class="mono">' + e(u.username) + '</span></td><td data-label="Emp. Code">' + e(u.emp_code) + '</td>' +
                        '<td data-label="Role"><span class="badge badge-primary">' + e(u.role_name || (u.role_id === 1 ? 'Admin' : '—')) + '</span>' + (Auth.can('user.permissions') ? overrideBadge(u.user_id) : '') + '</td>' +
                        '<td data-label="Contact">' + e(u.mobile) + '<span class="cell-sub">' + e(u.email) + '</span></td>' +
                        '<td data-label="Last login">' + e(P.fmt_datetime(u.last_login_at)) + '</td><td data-label="Status">' + P.active_badge(u.is_active) +
                        (u.is_locked ? ' <span class="badge badge-danger">' + icon('fa-lock') + ' Locked</span>' : '') + '</td><td class="actions"><div class="btn-group">' +
                        (!self && Number(u.role_id) !== 1 && u.is_active && Auth.can('user.impersonate') ?'<form method="post" data-action="impersonate"><input type="hidden" name="id" value="' + u.user_id + '">' +
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
                }).join('') : P.empty_state(8, p.hasFilters(), 'No users yet')) +
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
            '<div class="form-group"><label class="form-label" for="role_id">Role <span class="required">*</span></label>' +
            '<select id="role_id" name="role_id" class="form-select" data-rules="required" data-label="Role"' + (isSelf ? ' disabled' : '') + '>' + roleOpts + '</select>' +
            (isSelf ? '<input type="hidden" name="role_id" value="' + e(user.role_id || 1) + '"><div class="form-hint">You cannot change your own role.</div>' : '') +
            '<div class="form-hint">Permissions and module access are inherited from the assigned role.</div><div class="invalid-feedback"></div></div>' +
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
JS;

$newCode = <<<JS
        const p = P.paginate(rows, ctx, {
            sort: 'name', dir: 'asc', filters: ['role_id', 'status', 'access'],
            search: function (u) { return [u.name, u.mobile, u.email]; },
            sortable: {
                name: function (u) { return u.name; }, mobile: function (u) { return u.mobile; },
                role: function (u) { return u.role_name; }, last_login: function (u) { return u.last_login_at; }
            }
        });
        const roles = {};
        DB.all('roles').sort(P.by(function (r) { return r.sort_order; })).forEach(function (r) { roles[r.role_id] = r.role_name; });
        const filters = 
            '<select name="role_id" class="form-select" aria-label="Role" data-auto-submit>' + L.options(roles, q.role_id, 'All roles') + '</select>' +
            '<select name="status" class="form-select" aria-label="Status" data-auto-submit>' + L.options({ active: 'Active', inactive: 'Inactive', locked: 'Locked' }, q.status, 'All status') + '</select>' +
            (Auth.can('user.permissions') ? '<select name="access" class="form-select" aria-label="Permissions" data-auto-submit>' + L.options({ custom: 'With personal permissions' }, q.access, 'Any permissions') + '</select>' : '');
        const me = Auth.id();
        return {
            title: 'User Master',
            html: P.pageHeader(icon('fa-users') + ' User Master', 'Manage system users and role users. Users sign in using their registered mobile number.',
                Auth.can('user.create') ? '<a href="' + P.BASE + '/admin/users/create" class="btn btn-primary">' + icon('fa-user-plus') + ' <span>Add User</span></a>' : '') +
                '<div class="card">' + P.list_toolbar(p, 'Search name, mobile, email…', filters) +
                '<div class="table-wrap"><table class="table table-stack table-top"><thead><tr>' +
                P.th_sort(p, 'name', 'Name') + P.th_sort(p, 'role', 'Role') + P.th_sort(p, 'mobile', 'Mobile (Login ID)') + '<th>Email</th>' + P.th_sort(p, 'last_login', 'Last login') + '<th>Status</th><th class="text-right">Actions</th></tr></thead><tbody>' +
                (p.items.length ? p.items.map(function (u) {
                    const self = u.user_id === me;
                    return '<tr><td data-label="Name"><strong>' + e(u.name) + '</strong>' + (self ? ' <span class="badge badge-gold">You</span>' : '') + '</td>' +
                        '<td data-label="Role"><span class="badge badge-primary">' + e(u.role_name || (u.role_id === 1 ? 'Admin' : '—')) + '</span>' + (Auth.can('user.permissions') ? overrideBadge(u.user_id) : '') + '</td>' +
                        '<td data-label="Mobile (Login ID)"><span class="mono"><strong>' + e(u.mobile) + '</strong></span></td>' +
                        '<td data-label="Email">' + (u.email ? e(u.email) : '<span class="text-muted">—</span>') + '</td>' +
                        '<td data-label="Last login">' + e(P.fmt_datetime(u.last_login_at)) + '</td><td data-label="Status">' + P.active_badge(u.is_active) +
                        (u.is_locked ? ' <span class="badge badge-danger">' + icon('fa-lock') + ' Locked</span>' : '') + '</td><td class="actions"><div class="btn-group">' +
                        (!self && Number(u.role_id) !== 1 && u.is_active && Auth.can('user.impersonate') ? '<form method="post" data-action="impersonate"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn-switch" title="' + e('Login as ' + u.name) + '" aria-label="' + e('Login as ' + u.name) + '">' + icon('fa-right-to-bracket') + '</button></form>' : '') +
                        (Auth.can('user.edit') ? '<a href="' + P.BASE + '/admin/users/' + u.user_id + '/edit" class="btn btn-light btn-sm btn-icon-only" title="' + e('Edit ' + u.name) + '" aria-label="' + e('Edit ' + u.name) + '">' + icon('fa-pen') + '</a>' : '') +
                        (Auth.can('user.permissions') ? '<a href="' + P.BASE + '/admin/users/' + u.user_id + '/permissions" class="btn btn-light btn-sm btn-icon-only" title="' + e('Permissions of ' + u.name) + '" aria-label="' + e('Permissions of ' + u.name) + '">' + icon('fa-user-shield') + '</a>' : '') +
                        (u.is_locked && Auth.can('user.unlock') ? '<form method="post" data-action="user-unlock" data-confirm="' + e('Unlock account "' + u.name + ' (' + u.mobile + ')"?') + '"><input type="hidden" name="id" value="' + u.user_id + '">' +
                            '<button type="submit" class="btn btn-sm btn-warning btn-icon-only" title="Unlock account" aria-label="Unlock account">' + icon('fa-lock-open') + '</button></form>' : '') +
                        (!self && Auth.can('user.toggle') ? '<form method="post" data-action="user-toggle" data-confirm="' + e(u.is_active ? 'Deactivate "' + u.name + '"? They will not be able to login.' : 'Activate "' + u.name + '"?') + '" data-confirm-variant="' + (u.is_active ? 'danger' : 'success') + '">' +
                            '<input type="hidden" name="id" value="' + u.user_id + '">' +
                            (u.is_active ? '<button type="submit" class="btn btn-sm btn-light text-danger btn-icon-only" title="Deactivate" aria-label="Deactivate">' + icon('fa-user-slash') + '</button>'
                                : '<button type="submit" class="btn btn-sm btn-success btn-icon-only" title="Activate" aria-label="Activate">' + icon('fa-user-check') + '</button>') + '</form>' : '') +
                        '</div></td></tr>';
                }).join('') : P.empty_state(7, p.hasFilters(), 'No users yet')) +
                '</tbody></table></div>' + P.pagination(p) + '</div>'
        };
    }, 'user.view');

    function generateStrongPassword(len) {
        len = len || 10;
        const uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        const lowers = 'abcdefghijkmnopqrstuvwxyz';
        const digits = '23456789';
        const specials = '@#$%&*!_';
        const all = uppers + lowers + digits + specials;
        const pwd = [
            uppers[Math.floor(Math.random() * uppers.length)],
            lowers[Math.floor(Math.random() * lowers.length)],
            digits[Math.floor(Math.random() * digits.length)],
            specials[Math.floor(Math.random() * specials.length)]
        ];
        for (let i = pwd.length; i < len; i++) {
            pwd.push(all[Math.floor(Math.random() * all.length)]);
        }
        for (let i = pwd.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            const tmp = pwd[i]; pwd[i] = pwd[j]; pwd[j] = tmp;
        }
        return pwd.join('');
    }

    function userForm(user) {
        const isEdit = !!user;
        const isSelf = isEdit && user.user_id === Auth.id();
        const v = function (k, d) { return isEdit ? (user[k] == null ? '' : user[k]) : (d || ''); };
        const roles = DB.where('roles', { is_active: 1 }).sort(P.by(function (r) { return r.sort_order; }));
        const mappings = isEdit ? DB.count('scheme_users', { user_id: user.user_id, is_active: 1 }) : 0;
        const autoPassword = !isEdit ? generateStrongPassword(10) : '';

        const txt = function (id, text, attrs, value, hint) {
            return '<div class="form-group"><label class="form-label" for="' + id + '">' + text + ' <span class="required">*</span></label>' +
                '<input id="' + id + '" name="' + id + '" value="' + e(value) + '" ' + attrs + '>' +
                (hint ? '<div class="form-hint">' + hint + '</div>' : '') +
                '<div class="invalid-feedback"></div></div>';
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
            isEdit ? e(user.name + ' · ' + user.mobile) : 'Create a new user with mobile number as login ID.',
            '<a href="' + P.BASE + '/admin/users" class="btn btn-light">' + icon('fa-arrow-left') + ' <span>Back to list</span></a>' +
            (isEdit && Auth.can('user.permissions') ? '<a href="' + P.BASE + '/admin/users/' + user.user_id + '/permissions" class="btn btn-light">' + icon('fa-user-shield') + ' <span>Permissions</span></a>' : '')) +
            (mappings > 0 ? '<div class="alert alert-info">' + icon('fa-circle-info') + '<span>This user is mapped in <strong>' + mappings + '</strong> scheme role mapping(s). Changing the role or deactivating removes them from <em>new</em> properties only.</span></div>' : '') +
            '<div class="' + (isEdit ? 'grid-2' : '') + '"><div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-id-card') + ' User details</h3></div><div class="card-body">' +
            '<form method="post" data-action="user-save" novalidate data-validate autocomplete="off">' + (isEdit ? '<input type="hidden" name="id" value="' + user.user_id + '">' : '') +
            '<div class="form-grid">' +
            txt('name', 'Full name (पूरा नाम)', 'type="text" class="form-control" maxlength="120" placeholder="e.g. Ramesh Kumar" data-rules="required|min:3|max:120|name" data-label="Full name"', v('name')) +
            txt('mobile', 'Mobile Number (लॉगिन आईडी)', 'type="tel" class="form-control" maxlength="10" inputmode="numeric" placeholder="10-digit mobile" data-rules="required|mobile" data-label="Mobile"', v('mobile'), 'Used as Login ID to sign into the system.') +
            '<div class="form-group"><label class="form-label" for="email">Email <small class="text-muted">(Optional / वैकल्पिक)</small></label>' +
            '<input type="email" id="email" name="email" value="' + e(v('email')) + '" class="form-control" maxlength="150" placeholder="e.g. user@kda.local" data-rules="max:150|email" data-label="Email"><div class="invalid-feedback"></div></div>' +
            '<div class="form-group"><label class="form-label" for="role_id">Role <span class="required">*</span></label>' +
            '<select id="role_id" name="role_id" class="form-select" data-rules="required" data-label="Role"' + (isSelf ? ' disabled' : '') + '>' + roleOpts + '</select>' +
            (isSelf ? '<input type="hidden" name="role_id" value="' + e(user.role_id || 1) + '"><div class="form-hint">You cannot change your own role.</div>' : '') +
            '<div class="form-hint">Permissions and module access are inherited from the assigned role.</div><div class="invalid-feedback"></div></div>' +
            (!isEdit ? '<div class="form-group" style="grid-column: 1 / -1;">' +
                '<label class="form-label" for="password">Password (ऑटो-जनरेटेड पासवर्ड) <span class="required">*</span></label>' +
                '<div style="display:flex;gap:8px;align-items:center;">' +
                '<div class="input-icon input-password" style="flex:1;">' + icon('fa-key') +
                '<input type="text" id="password" name="password" class="form-control mono" maxlength="72" value="' + autoPassword + '" data-rules="required|password" data-label="Password" autocomplete="new-password">' +
                '<button type="button" class="toggle-password" data-toggle-password="password" aria-label="Toggle password visibility">' + icon('fa-eye-slash') + '</button></div>' +
                '<button type="button" class="btn btn-light" id="btn-copy-pwd" title="Copy password">' + icon('fa-copy') + ' <span>Copy</span></button>' +
                '<button type="button" class="btn btn-light" id="btn-regen-pwd" title="Generate another password">' + icon('fa-rotate') + ' <span>Regenerate</span></button></div>' +
                '<div class="form-hint" style="color:var(--green-700,#15803d);font-weight:500;margin-top:4px;">' +
                icon('fa-circle-check') + ' 10-char strong password auto-generated (Upper + Lower + Digit + Special character). You can copy or edit it.</div>' +
                '<div class="invalid-feedback"></div></div>' : '') +
            '<div class="form-group"><label class="check"><input type="checkbox" name="is_active" value="1"' + (!isEdit || user.is_active ? ' checked' : '') + (isSelf ? ' disabled checked' : '') + '>' +
            '<span><strong>Active</strong><br><small class="text-muted">Inactive users cannot login.</small></span></label></div>' +
            '</div><div class="form-actions"><button type="submit" class="btn btn-primary" data-loading-text="Saving…">' + icon('fa-floppy-disk') + ' <span>' + (isEdit ? 'Update User' : 'Create User') + '</span></button>' +
            '<a href="' + P.BASE + '/admin/users" class="btn btn-light">' + icon('fa-xmark') + ' <span>Cancel</span></a></div></form></div></div>';

        if (isEdit) {
            html += '<div>' + (Auth.can('user.reset_password') ? '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-unlock-keyhole') + ' Reset password</h3></div><div class="card-body">' +
                '<p class="text-muted mb-3">Set a temporary password. The user must change it on next login. This also unlocks the account.</p>' +
                '<form method="post" data-action="user-reset-password" novalidate data-validate autocomplete="off" data-confirm="' + e('Reset the password of "' + user.name + '"?') + '" data-confirm-variant="warning">' +
                '<input type="hidden" name="id" value="' + user.user_id + '">' +
                '<div class="form-group"><label class="form-label" for="reset_password">New temporary password <span class="required">*</span></label>' +
                '<div class="input-icon input-password">' + icon('fa-key') + '<input type="password" id="reset_password" name="reset_password" class="form-control" maxlength="72" autocomplete="new-password" data-rules="required|password" data-label="New temporary password">' +
                '<button type="button" class="toggle-password" data-toggle-password="reset_password" aria-label="Show password">' + icon('fa-eye') + '</button></div><div class="invalid-feedback"></div></div>' +
                '<button type="submit" class="btn btn-warning" data-loading-text="Resetting…">' + icon('fa-rotate') + ' <span>Reset password</span></button></form></div></div>' : '') +
                '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-circle-info') + ' Account info</h3></div><div class="card-body"><dl class="detail-list">' +
                '<dt>Status</dt><dd>' + P.active_badge(user.is_active) + '</dd>' +
                '<dt>Last login</dt><dd>' + e(P.fmt_datetime(user.last_login_at)) + (user.last_login_ip ? ' · ' + e(user.last_login_ip) : '') + '</dd>' +
                '<dt>Password changed</dt><dd>' + e(P.fmt_datetime(user.password_changed_at)) + '</dd>' +
                '<dt>Locked until</dt><dd>' + e(P.fmt_datetime(user.locked_until)) + '</dd>' +
                '<dt>Created</dt><dd>' + e(P.fmt_datetime(user.created_at)) + '</dd></dl></div></div></div>';
        }

        return {
            title: isEdit ? 'Edit User' : 'Add User',
            html: html + '</div>',
            after: function (root) {
                const copyBtn = root.querySelector('#btn-copy-pwd');
                const regenBtn = root.querySelector('#btn-regen-pwd');
                const pwdInput = root.querySelector('#password');
                if (copyBtn && pwdInput) {
                    copyBtn.onclick = function () {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(pwdInput.value);
                        } else {
                            pwdInput.select();
                            document.execCommand('copy');
                        }
                        const orig = copyBtn.innerHTML;
                        copyBtn.innerHTML = icon('fa-check') + ' <span>Copied!</span>';
                        copyBtn.classList.add('btn-success');
                        setTimeout(function () {
                            copyBtn.innerHTML = orig;
                            copyBtn.classList.remove('btn-success');
                        }, 1800);
                    };
                }
                if (regenBtn && pwdInput) {
                    regenBtn.onclick = function () {
                        pwdInput.value = generateStrongPassword(10);
                        pwdInput.dispatchEvent(new Event('input', { bubbles: true }));
                    };
                }
            }
        };
    }
    P.route('/admin/users/create', PANEL, function () { return userForm(null); }, 'user.create');
    P.route('/admin/users/{id}/edit', PANEL, function (ctx) {
        const u = DB.find('users', ctx.params.id);
        return u ? userForm(u) : P.notFound('User not found.');
    }, 'user.edit');
JS;

$oldNorm = str_replace("\r\n", "\n", $oldCode);
$newNorm = str_replace("\r\n", "\n", $newCode);

if (strpos($norm, $oldNorm) !== false) {
    $norm = str_replace($oldNorm, $newNorm, $norm);
    
    // Also replace lines 279 and 557
    $norm = str_replace(
        "e(user.username + ' · ' + roleName + ' panel' + (role ? ' · ' + role.role_name : '') + ' · ' + user.designation)",
        "e(user.name + ' (' + user.mobile + ') · ' + roleName + (role ? ' · ' + role.role_name : ''))",
        $norm
    );
    $norm = str_replace(
        "sub: u.username + ' · ' + (r ? r.role_name : 'No role') + ' · ' + u.designation",
        "sub: u.mobile + ' · ' + (r ? r.role_name : 'No role')",
        $norm
    );

    file_put_contents($adminJsPath, str_replace("\n", "\r\n", $norm));
    echo "public/assets/js/pages/admin.js updated successfully.\n";
} else {
    echo "Could not find old code in admin.js\n";
}

