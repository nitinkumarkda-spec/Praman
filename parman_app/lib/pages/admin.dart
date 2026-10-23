// PRAMAN — Super Admin: Role Master + User Master (js/pages/admin.js).
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/lookups.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../ui/common.dart';
import '../ui/forms.dart';
import '../ui/lists.dart';

bool isLocked(Rec u) => u['locked_until'] != null && str(u['locked_until']).compareTo(P.db.now()) > 0;

/* ======================= Role Master ======================= */

class RoleListPage extends StatelessWidget {
  final RouteCtx ctx;
  const RoleListPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    var rows = db.all('roles').map((r) {
      final us = db.where('users', {'role_id': r['role_id'], 'panel': 'ROLE_USER'});
      return <String, dynamic>{...r, 'users_count': us.length, 'active_users': us.where((u) => truthy(u['is_active'])).length};
    }).toList();
    final st = ctx.q('status');
    if (st == 'active') rows = rows.where((r) => truthy(r['is_active'])).toList();
    if (st == 'inactive') rows = rows.where((r) => !truthy(r['is_active'])).toList();
    final p = paginate<Rec>(rows, ctx,
        sort: 'order',
        filters: const ['status'],
        search: (r) => [r['role_name'], r['role_code'], r['role_name_hi']],
        sortable: {
          'order': (r) => toNum(r['sort_order']),
          'name': (r) => r['role_name'],
          'code': (r) => r['role_code'],
          'sla': (r) => toNum(r['sla_days']),
          'users': (r) => toNum(r['active_users']),
        });
    return PageBody(children: [
      PageHeader(
        icon: FA.sitemap,
        title: 'Role Master',
        subtitle: 'Each role is one verification section (Engineering, Sale, Legal …). Users are mapped to roles in the User Master.',
        actions: [Btn('Add Role', icon: FA.plus, onPressed: () => P.go('/admin/roles/create'))],
      ),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search role name or code…',
          filters: const [FilterDef('status', 'Status', kActiveOpts, 'All status')],
          sorts: const [('order', '#'), ('name', 'Role / Section'), ('code', 'Code'), ('sla', 'SLA (days)'), ('users', 'Users')],
        ),
        rows: [
          for (final r in p.items)
            RecordCard(
              title: cellSub(r['role_name'], r['role_name_hi'], strong: true),
              trailing: activePill(r['is_active']),
              fields: [
                ('Order', val(r['sort_order'])),
                ('Code', val(r['role_code'], mono: true)),
                ('SLA (days)', val(r['sla_days'])),
                ('Mandatory', truthy(r['is_mandatory']) ? const Pill('Mandatory', tone: Tone.gold, icon: FA.star) : const Pill('Optional')),
                (
                  'Users',
                  InkWell(
                    onTap: () => P.go('/admin/users', {'role_id': r['role_id']}),
                    child: cellSub('${r['active_users']} active', '${r['users_count']} total'),
                  ),
                ),
              ],
              actions: [
                IconBtn(FA.pen, tooltip: 'Edit ${r['role_name']}', onPressed: () => P.go('/admin/roles/${r['role_id']}/edit')),
                _roleToggle(r),
              ],
            ),
        ],
        empty: listEmpty(p.hasFilters, 'No roles yet'),
        pager: p,
      ),
    ]);
  }

  Widget _roleToggle(Rec r) {
    final on = r['is_active'] == 1;
    return IconBtn(
      on ? FA.toggleOff : FA.toggleOn,
      variant: on ? 'light-danger' : 'success',
      tooltip: on ? 'Deactivate' : 'Activate',
      onPressed: () => confirmAction(
        confirm: on ? 'Deactivate role "${r['role_name']}"? It will not be used for new schemes and verification rounds.' : 'Activate role "${r['role_name']}"?',
        variant: on ? 'danger' : 'success',
        action: () {
          final db = P.db;
          final active = on ? 0 : 1;
          db.update('roles', r['role_id'], {'is_active': active, 'updated_by': P.uid});
          db.audit('ROLE', r['role_id'], active == 1 ? 'ACTIVATED' : 'DEACTIVATED', {'is_active': 1 - active}, {'is_active': active});
          P.flash('success', 'Role "${r['role_name']}" ${active == 1 ? 'activated.' : 'deactivated. It will not be used for new schemes / verification rounds.'}');
          P.render();
        },
      ),
    );
  }
}

class RoleFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final Rec? role;
  const RoleFormPage(this.ctx, this.role, {super.key});
  @override
  State<RoleFormPage> createState() => _RoleFormPageState();
}

class _RoleFormPageState extends State<RoleFormPage> {
  late final FormModel _f;

  @override
  void initState() {
    super.initState();
    final r = widget.role;
    _f = FormModel(r == null
        ? {'sla_days': 5, 'sort_order': P.db.count('roles') + 1, 'is_mandatory': '1', 'is_active': '1'}
        : {
            'role_name': r['role_name'], 'role_code': r['role_code'], 'role_name_hi': r['role_name_hi'], 'sla_days': r['sla_days'],
            'sort_order': r['sort_order'], 'description': r['description'],
            'is_mandatory': truthy(r['is_mandatory']) ? '1' : '', 'is_active': truthy(r['is_active']) ? '1' : '',
          });
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    await submitForm(_f, action: () {
      final db = P.db;
      final role = widget.role;
      final id = role == null ? null : toInt(role['role_id']);
      final old = role == null ? null : Map<String, Object?>.of(role);
      final data = <String, Object?>{
        'role_name': _f.trimmed('role_name'),
        'role_name_hi': _f.trimmed('role_name_hi').isEmpty ? null : _f.trimmed('role_name_hi'),
        'description': _f.trimmed('description').isEmpty ? null : _f.trimmed('description'),
        'sla_days': int.parse(_f.trimmed('sla_days')),
        'sort_order': int.parse(_f.trimmed('sort_order')),
        'is_mandatory': _f.isOn('is_mandatory') ? 1 : 0,
        'is_active': _f.isOn('is_active') ? 1 : 0,
      };
      if (db.valueExists('roles', 'role_name', data['role_name'], id)) {
        _f.fail('role_name', 'A role with this name already exists.');
        return;
      }
      if (id == null) {
        data['role_code'] = _f.trimmed('role_code').toUpperCase();
        if (db.valueExists('roles', 'role_code', data['role_code'])) {
          _f.fail('role_code', 'This role code is already in use.');
          return;
        }
        final r = db.insert('roles', {...data, 'created_by': P.uid});
        db.audit('ROLE', r['role_id'], 'CREATED', null, data);
        P.flash('success', 'Role "${data['role_name']}" created successfully.');
      } else {
        db.update('roles', id, {...data, 'updated_by': P.uid});
        db.audit('ROLE', id, 'UPDATED', old, data);
        P.flash('success', 'Role "${data['role_name']}" updated successfully.');
      }
      P.go('/admin/roles', null, true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final role = widget.role;
    final isEdit = role != null;
    final usage = isEdit ? P.db.where('scheme_role_users', {'role_id': role['role_id'], 'is_active': 1}).map((m) => m['scheme_id']).toSet().length : 0;
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        PageHeader(
          icon: FA.sitemap,
          title: isEdit ? 'Edit Role' : 'Add Role',
          subtitle: isEdit ? 'Changes apply to new schemes and new verification rounds.' : 'Create a verification section. Map users to it from the User Master.',
          actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('/admin/roles', null, true))],
        ),
        if (isEdit && usage > 0) AlertBox(type: 'info', text: 'This role is mapped in $usage scheme(s). Existing properties keep their snapshot.'),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            FText(form: _f, name: 'role_name', label: 'Role name', rules: 'required|min:3|max:100|name', placeholder: 'e.g. Engineering Cell', maxLength: 100),
            FText(
              form: _f,
              name: 'role_code',
              label: 'Role code',
              rules: isEdit ? '' : 'required|code',
              requiredMark: true,
              readOnly: isEdit,
              upper: true,
              placeholder: 'e.g. ENGINEERING_CELL',
              maxLength: 40,
              hint: isEdit ? 'Code cannot be changed after creation.' : 'CAPITAL letters, digits and underscore. Cannot be changed later.',
            ),
            FText(form: _f, name: 'role_name_hi', label: 'Role name (Hindi)', rules: 'max:100|text', placeholder: 'e.g. अभियांत्रिकी शाखा', maxLength: 100),
            FText(form: _f, name: 'sla_days', label: 'SLA (days to respond)', rules: 'required|integer|between:1,365', keyboard: TextInputType.number, maxLength: 3),
            FText(form: _f, name: 'sort_order', label: 'Display order', rules: 'required|integer|between:0,999', keyboard: TextInputType.number, maxLength: 3),
            FText(form: _f, name: 'description', label: 'Description', rules: 'max:255|text', placeholder: 'What does this section verify?', maxLength: 255),
            FCheck(form: _f, name: 'is_mandatory', title: 'Mandatory', subtitle: 'Every scheme must map at least one user of this role.'),
            FCheck(form: _f, name: 'is_active', title: 'Active', subtitle: 'Inactive roles are skipped for new schemes and rounds.'),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              Btn(isEdit ? 'Update Role' : 'Create Role', icon: FA.floppyDisk, loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
              Btn('Cancel', icon: FA.xmark, variant: 'light', onPressed: () => P.go('/admin/roles', null, true)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

/* ======================= User Master ======================= */

class UserListPage extends StatelessWidget {
  final RouteCtx ctx;
  const UserListPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final q = ctx.query;
    var rows = db.all('users').map((u) {
      final r = u['role_id'] != null ? db.find('roles', u['role_id']) : null;
      return <String, dynamic>{...u, 'role_name': r?['role_name'], 'is_locked': isLocked(u) ? 1 : 0};
    }).toList();
    if (ctx.q('panel').isNotEmpty) rows = rows.where((u) => u['panel'] == ctx.q('panel')).toList();
    if (ctx.q('role_id').isNotEmpty) rows = rows.where((u) => str(u['role_id']) == ctx.q('role_id')).toList();
    if (q['status'] == 'active') rows = rows.where((u) => truthy(u['is_active'])).toList();
    if (q['status'] == 'inactive') rows = rows.where((u) => !truthy(u['is_active'])).toList();
    if (q['status'] == 'locked') rows = rows.where((u) => u['is_locked'] == 1).toList();
    final p = paginate<Rec>(rows, ctx,
        sort: 'name',
        filters: const ['panel', 'role_id', 'status'],
        search: (u) => [u['name'], u['username'], u['emp_code'], u['mobile'], u['email'], u['designation']],
        sortable: {
          'name': (u) => u['name'],
          'username': (u) => u['username'],
          'emp_code': (u) => u['emp_code'],
          'panel': (u) => u['panel'],
          'role': (u) => u['role_name'],
          'last_login': (u) => u['last_login_at'],
        });
    final roles = db.all('roles')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
    final me = P.uid;
    return PageBody(children: [
      PageHeader(
        icon: FA.users,
        title: 'User Master',
        subtitle: 'Panel users and role users (e.g. engineer-1 under Engineering). Many users can belong to one role.',
        actions: [Btn('Add User', icon: FA.userPlus, onPressed: () => P.go('/admin/users/create'))],
      ),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search name, username, emp code, mobile, email…',
          filters: [
            const FilterDef('panel', 'Panel', App.panels, 'All panels'),
            FilterDef('role_id', 'Role', {for (final r in roles) str(r['role_id']): str(r['role_name'])}, 'All roles'),
            const FilterDef('status', 'Status', {'active': 'Active', 'inactive': 'Inactive', 'locked': 'Locked'}, 'All status'),
          ],
          sorts: const [('name', 'Name'), ('username', 'Username'), ('emp_code', 'Emp. Code'), ('panel', 'Panel'), ('role', 'Role'), ('last_login', 'Last login')],
        ),
        rows: [
          for (final u in p.items)
            RecordCard(
              title: Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 6, children: [
                Text(str(u['name']), style: T.s(14.5, weight: FontWeight.w700)),
                if (toInt(u['user_id']) == me) const Pill('You', tone: Tone.gold),
              ]),
              subtitle: Text(str(u['designation']), style: T.small),
              trailing: Wrap(spacing: 4, runSpacing: 4, alignment: WrapAlignment.end, children: [
                activePill(u['is_active']),
                if (u['is_locked'] == 1) const Pill('Locked', tone: Tone.danger, icon: FA.lock),
              ]),
              fields: [
                ('Username', val(u['username'], mono: true)),
                ('Emp. Code', val(u['emp_code'])),
                ('Panel', Align(alignment: Alignment.centerLeft, child: Pill(App.panels[u['panel']] ?? str(u['panel']), tone: Tone.primary))),
                ('Role', val(u['role_name'])),
                ('Contact', cellSub(u['mobile'], u['email'])),
                ('Last login', val(fmtDateTime(u['last_login_at']))),
              ],
              actions: _userActions(u, me),
            ),
        ],
        empty: listEmpty(p.hasFilters, 'No users yet'),
        pager: p,
      ),
    ]);
  }

  List<Widget> _userActions(Rec u, int me) {
    final self = toInt(u['user_id']) == me;
    final active = u['is_active'] == 1;
    return [
      if (!self && u['panel'] != 'SUPER_ADMIN' && active)
        Btn('Login as', icon: FA.rightToBracket, variant: 'gold', size: 'sm', onPressed: () => impersonateUser(toInt(u['user_id'])!)),
      IconBtn(FA.pen, tooltip: 'Edit ${u['name']}', onPressed: () => P.go('/admin/users/${u['user_id']}/edit')),
      if (u['is_locked'] == 1)
        IconBtn(FA.lockOpen, variant: 'warning', tooltip: 'Unlock account', onPressed: () => confirmAction(
              confirm: 'Unlock account "${u['username']}"?',
              action: () {
                P.db.update('users', u['user_id'], {'locked_until': null, 'failed_attempts': 0});
                P.db.audit('USER', u['user_id'], 'UNLOCKED', null, null);
                P.flash('success', 'Account "${u['username']}" unlocked.');
                P.render();
              },
            )),
      if (!self)
        IconBtn(active ? FA.userSlash : FA.userCheck,
            variant: active ? 'light-danger' : 'success',
            tooltip: active ? 'Deactivate' : 'Activate',
            onPressed: () => confirmAction(
                  confirm: active ? 'Deactivate "${u['name']}"? They will not be able to login.' : 'Activate "${u['name']}"?',
                  variant: active ? 'danger' : 'success',
                  action: () {
                    final db = P.db;
                    final next = active ? 0 : 1;
                    if (next == 0 && u['panel'] == 'SUPER_ADMIN' && activeAdmins(toInt(u['user_id'])) == 0) {
                      P.toast('error', 'At least one active Super Admin must remain.');
                      return;
                    }
                    db.update('users', u['user_id'], {'is_active': next, 'updated_by': P.uid});
                    db.audit('USER', u['user_id'], next == 1 ? 'ACTIVATED' : 'DEACTIVATED', {'is_active': 1 - next}, {'is_active': next});
                    P.flash('success', 'User "${u['name']}" ${next == 1 ? 'activated.' : 'deactivated. The user can no longer login.'}');
                    P.render();
                  },
                )),
    ];
  }
}

int activeAdmins([int? exceptId]) => P.db.countWhere('users', (u) => u['panel'] == 'SUPER_ADMIN' && truthy(u['is_active']) && toInt(u['user_id']) != exceptId);

/// "Login as" (P.actions.impersonate).
void impersonateUser(int id) {
  final u = P.db.find('users', id);
  if (u == null || !truthy(u['is_active']) || u['panel'] == 'SUPER_ADMIN') {
    P.toast('error', 'You cannot login as this user.');
    return;
  }
  P.impersonate(u);
  final role = u['role_id'] != null ? P.db.find('roles', u['role_id']) : null;
  P.flash('info', 'You are now logged in as ${u['name']} (${App.panels[u['panel']]}${role != null ? ' · ${role['role_name']}' : ''}). Use "Back to Admin" to return.');
  P.history.clear();
  P.go(App.homes[u['panel']] ?? '/', null, true);
}

class UserFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final Rec? user;
  const UserFormPage(this.ctx, this.user, {super.key});
  @override
  State<UserFormPage> createState() => _UserFormPageState();
}

class _UserFormPageState extends State<UserFormPage> {
  late final FormModel _f;
  final FormModel _r = FormModel();

  @override
  void initState() {
    super.initState();
    final u = widget.user;
    _f = FormModel(u == null
        ? {'panel': 'ROLE_USER', 'is_active': '1'}
        : {
            'name': u['name'], 'designation': u['designation'], 'username': u['username'], 'emp_code': u['emp_code'], 'mobile': u['mobile'],
            'email': u['email'], 'panel': u['panel'], 'role_id': u['role_id'], 'is_active': truthy(u['is_active']) ? '1' : '',
          });
  }

  @override
  void dispose() {
    _f.dispose();
    _r.dispose();
    super.dispose();
  }

  bool get _isSelf => widget.user != null && toInt(widget.user!['user_id']) == P.uid;

  Future<void> _save() async {
    final roleUser = _f.value('panel') == 'ROLE_USER';
    await submitForm(_f, exclude: roleUser ? <String>{} : <String>{'role_id'}, action: () {
      final db = P.db;
      final user = widget.user;
      final id = user == null ? null : toInt(user['user_id']);
      final old = user == null ? null : Map<String, Object?>.of(user);
      final data = <String, Object?>{
        'name': _f.trimmed('name'),
        'designation': _f.trimmed('designation'),
        'username': _f.trimmed('username').toLowerCase(),
        'emp_code': _f.trimmed('emp_code').toUpperCase(),
        'mobile': _f.trimmed('mobile'),
        'email': _f.trimmed('email').toLowerCase(),
        'panel': _isSelf ? user!['panel'] : _f.value('panel'),
        'role_id': (_isSelf ? user!['panel'] : _f.value('panel')) == 'ROLE_USER' ? toInt(_f.value('role_id')) : null,
        'is_active': _isSelf ? 1 : (_f.isOn('is_active') ? 1 : 0),
      };
      const uniq = [
        ('username', 'This username is already taken.'),
        ('emp_code', 'This employee code is already in use.'),
        ('email', 'This email is already registered.'),
        ('mobile', 'This mobile number is already registered.'),
      ];
      for (final x in uniq) {
        if (db.valueExists('users', x.$1, data[x.$1], id)) {
          _f.fail(x.$1, x.$2);
          return;
        }
      }
      if (id != null && old!['panel'] == 'SUPER_ADMIN' && (data['panel'] != 'SUPER_ADMIN' || data['is_active'] != 1) && activeAdmins(id) == 0) {
        _f.fail(null, 'At least one active Super Admin must remain.');
        return;
      }
      if (id == null) {
        final u = db.insert('users', {
          ...data,
          'password': _f.value('password'), 'must_change_password': 0, 'failed_attempts': 0, 'locked_until': null, 'last_login_at': null,
          'last_login_ip': null, 'password_changed_at': null, 'created_by': P.uid,
        });
        db.audit('USER', u['user_id'], 'CREATED', null, data);
        db.notify(u['user_id'],
            type: 'ACCOUNT', tone: 'success', icon: 'fa-hand-sparkles', title: 'Welcome to PRAMAN',
            message: 'Your account was created by ${P.user()?['name']}. Please change your password after first login.', link: '/change-password');
        P.flash('success', 'User "${data['name']}" (${data['username']}) created successfully.');
      } else {
        db.update('users', id, {...data, 'updated_by': P.uid});
        db.audit('USER', id, 'UPDATED', {'name': old!['name'], 'panel': old['panel'], 'role_id': old['role_id'], 'is_active': old['is_active']}, data);
        P.flash('success', 'User "${data['name']}" updated successfully.');
      }
      P.go('/admin/users', null, true);
    });
  }

  Future<void> _reset() async {
    final user = widget.user!;
    await submitForm(_r, confirm: 'Reset the password of "${user['name']}"?', variant: 'warning', action: () {
      final db = P.db;
      db.update('users', user['user_id'], {'password': _r.value('reset_password'), 'locked_until': null, 'failed_attempts': 0, 'password_changed_at': db.now()});
      db.audit('USER', user['user_id'], 'PASSWORD_RESET', null, null);
      db.notify(user['user_id'],
          type: 'ACCOUNT', tone: 'warning', icon: 'fa-key', title: 'Your password was reset',
          message: 'Super Admin set a temporary password for your account. Change it from Change Password.', link: '/change-password');
      P.flash('success', 'Password reset for "${user['name']}". They must change it on next login.');
      P.go('/admin/users', null, true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = widget.user;
    final isEdit = user != null;
    final db = P.db;
    final mappings = isEdit ? db.count('scheme_role_users', {'user_id': user['user_id'], 'is_active': 1}) : 0;
    final roles = db.where('roles', {'is_active': 1})..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
    final roleOpts = <String, String>{for (final r in roles) str(r['role_id']): str(r['role_name'])};
    if (isEdit && user['role_id'] != null && !roleOpts.containsKey(str(user['role_id']))) {
      roleOpts[str(user['role_id'])] = '${db.find('roles', user['role_id'])?['role_name'] ?? ''} (inactive)';
    }
    return AnimatedBuilder(
      animation: Listenable.merge([_f, _r]),
      builder: (context, _) {
        final roleUser = _f.value('panel') == 'ROLE_USER';
        return PageBody(children: [
          PageHeader(
            icon: FA.userGear,
            title: isEdit ? 'Edit User' : 'Add User',
            subtitle: isEdit ? '${user['name']} · ${user['username']}' : 'Create a login for a panel user or a role user.',
            actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('/admin/users', null, true))],
          ),
          if (mappings > 0)
            AlertBox(type: 'info', text: 'This user is mapped in $mappings scheme role mapping(s). Changing the role or deactivating removes them from new properties only.'),
          AppCard(
            title: 'User details',
            icon: FA.idCard,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              FText(form: _f, name: 'name', label: 'Full name', rules: 'required|min:3|max:120|name', maxLength: 120),
              FText(form: _f, name: 'designation', label: 'Designation', rules: 'required|min:2|max:100|text', placeholder: 'e.g. Junior Engineer', maxLength: 100),
              FText(form: _f, name: 'username', label: 'Username (login id)', rules: 'required|min:3|max:50|alpha_dash', placeholder: 'e.g. eng3', maxLength: 50),
              FText(form: _f, name: 'emp_code', label: 'Employee code', rules: 'required|ucode', placeholder: 'e.g. KDA-ENG-003', upper: true, maxLength: 30),
              FText(form: _f, name: 'mobile', label: 'Mobile', rules: 'required|mobile', placeholder: '10-digit mobile', keyboard: TextInputType.phone, maxLength: 10),
              FText(form: _f, name: 'email', label: 'Email', rules: 'required|max:150|email', keyboard: TextInputType.emailAddress, maxLength: 150),
              FSelect(
                form: _f,
                name: 'panel',
                label: 'Panel type',
                options: App.panels,
                rules: 'required',
                enabled: !_isSelf,
                hint: _isSelf ? 'You cannot change your own panel.' : null,
                onChanged: (v) {
                  if (v != 'ROLE_USER') {
                    _f.set('role_id', '');
                    _f.clearError('role_id');
                  }
                },
              ),
              if (roleUser)
                FSelect(form: _f, name: 'role_id', label: 'Role / Section', options: roleOpts, rules: 'required', placeholder: 'Select role', hint: 'The verification section this user works for.'),
              if (!isEdit) ...[
                FText(form: _f, name: 'password', label: 'Password', rules: 'required|password', password: true, prefix: FA.key, maxLength: 72, hint: '≥ 10 chars with upper, lower, digit & special character.'),
                FText(form: _f, name: 'confirm_password', label: 'Confirm password', rules: 'required|same:password', password: true, prefix: FA.key, maxLength: 72),
              ],
              FCheck(form: _f, name: 'is_active', title: 'Active', subtitle: 'Inactive users cannot login.', enabled: !_isSelf),
              const SizedBox(height: 8),
              Wrap(spacing: 8, runSpacing: 8, children: [
                Btn(isEdit ? 'Update User' : 'Create User', icon: FA.floppyDisk, loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
                Btn('Cancel', icon: FA.xmark, variant: 'light', onPressed: () => P.go('/admin/users', null, true)),
              ]),
            ]),
          ),
          if (isEdit) ...[
            AppCard(
              title: 'Reset password',
              icon: FA.unlockKeyhole,
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Padding(padding: const EdgeInsets.only(bottom: 12), child: Text('Set a temporary password. The user must change it on next login. This also unlocks the account.', style: T.small)),
                FText(form: _r, name: 'reset_password', label: 'New temporary password', rules: 'required|password', password: true, prefix: FA.key, maxLength: 72),
                FText(form: _r, name: 'reset_confirm', label: 'Confirm password', rules: 'required|same:reset_password', password: true, maxLength: 72),
                Align(alignment: Alignment.centerLeft, child: Btn('Reset password', icon: FA.rotate, variant: 'warning', loading: _r.submitting, loadingText: 'Resetting…', onPressed: _reset)),
              ]),
            ),
            AppCard(
              title: 'Account info',
              icon: FA.circleInfo,
              child: DetailList([
                ('Status', Align(alignment: Alignment.centerLeft, child: activePill(user['is_active']))),
                ('Last login', val('${fmtDateTime(user['last_login_at'])}${user['last_login_ip'] != null ? ' · ${user['last_login_ip']}' : ''}')),
                ('Password changed', val(fmtDateTime(user['password_changed_at']))),
                ('Locked until', val(fmtDateTime(user['locked_until']))),
                ('Created', val(fmtDateTime(user['created_at']))),
              ]),
            ),
          ],
        ]);
      },
    );
  }
}
