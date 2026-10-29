// PRAMAN — route table (P.route() calls of the web app) + guard (403 audit, view redirects) + resolver.
import '../pages/account.dart';
import '../pages/admin.dart';
import '../pages/dashboards.dart';
import '../pages/insights.dart';
import '../pages/masters.dart';
import '../pages/notifications.dart';
import '../pages/oic.dart';
import '../pages/properties.dart';
import '../pages/schemes.dart';
import '../pages/tasks.dart';
import '../ui/shell.dart';
import 'format.dart';
import 'route_ctx.dart';
import 'store.dart';

class RouteDef {
  final RegExp re;
  final List<String> keys;
  final String? panel;
  final PageSpec Function(RouteCtx c) build;
  final String? Function(RouteCtx c)? redirect;
  RouteDef(this.re, this.keys, this.panel, this.build, this.redirect);
}

class _Match {
  final RouteDef def;
  final Map<String, int> params;
  const _Match(this.def, this.params);
}

class Routes {
  Routes._();
  static final List<RouteDef> _all = [];

  static void route(String path, String? panel, PageSpec Function(RouteCtx c) build, {String? Function(RouteCtx c)? redirect}) {
    final keys = <String>[];
    final pattern = path.replaceAllMapped(RegExp(r'\{(\w+)\}'), (m) {
      keys.add(m.group(1)!);
      return r'(\d+)';
    });
    _all.add(RouteDef(RegExp('^$pattern\$'), keys, panel, build, redirect));
  }

  static _Match? _match(String path) {
    for (final r in _all) {
      final m = r.re.firstMatch(path);
      if (m == null) continue;
      final params = <String, int>{};
      for (var i = 0; i < r.keys.length; i++) {
        params[r.keys[i]] = int.parse(m.group(i + 1)!);
      }
      return _Match(r, params);
    }
    return null;
  }

  /// Runs on every navigation (App._setLocation): audits blocked pages and applies view redirects.
  static String? guard(String path, Map<String, String> query, Rec user) {
    final m = _match(path);
    if (m == null) return null;
    if (m.def.panel != null && m.def.panel != user['panel']) {
      P.db.audit('ROUTE', null, 'ACCESS_DENIED', null, {'path': path}, null, user);
      return null;
    }
    final r = m.def.redirect;
    return r == null ? null : r(RouteCtx(path, query, m.params, user));
  }

  /// Page for the current location (403 / 404 pages included).
  static PageSpec resolve(String path, Map<String, String> query, Rec user) {
    final m = _match(path);
    if (m == null) return const PageSpec('Page not found', ErrorPage(status: 404, title: 'Page not found', message: 'The page or record you are looking for does not exist.'));
    if (m.def.panel != null && m.def.panel != user['panel']) {
      return const PageSpec('Access denied', ErrorPage(status: 403, title: 'Access denied', message: 'You do not have permission to open this page.'));
    }
    return m.def.build(RouteCtx(path, query, m.params, user));
  }

  static PageSpec _notFound(String msg) => PageSpec('Page not found', ErrorPage(status: 404, title: 'Page not found', message: msg));

  static void init() {
    if (_all.isNotEmpty) return;
    const sa = 'SUPER_ADMIN';
    const de = 'DATA_ENTRY';
    const oic = 'OIC';
    const ru = 'ROLE_USER';

    // shared
    route('/change-password', null, (c) => PageSpec('Change Password', ChangePasswordPage(c)));
    route('/notifications', null, (c) => PageSpec('Notifications', NotificationsPage(c)));

    // dashboards
    route('/admin/dashboard', sa, (c) => PageSpec('Dashboard', AdminDashboard(c)));
    route('/data-entry/dashboard', de, (c) => PageSpec('Dashboard', DataEntryDashboard(c)));
    route('/oic/dashboard', oic, (c) => PageSpec('Dashboard', OicDashboard(c)));
    route('/role/dashboard', ru, (c) => PageSpec('Dashboard', RoleDashboard(c)));

    // Super Admin
    route('/admin/roles', sa, (c) => PageSpec('Role Master', RoleListPage(c)));
    route('/admin/roles/create', sa, (c) => PageSpec('Add Role', RoleFormPage(c, null)));
    route('/admin/roles/{id}/edit', sa, (c) {
      final r = P.db.find('roles', c.id);
      return r == null ? _notFound('Role not found.') : PageSpec('Edit Role', RoleFormPage(c, r));
    });
    route('/admin/users', sa, (c) => PageSpec('User Master', UserListPage(c)));
    route('/admin/users/create', sa, (c) => PageSpec('Add User', UserFormPage(c, null)));
    route('/admin/users/{id}/edit', sa, (c) {
      final u = P.db.find('users', c.id);
      return u == null ? _notFound('User not found.') : PageSpec('Edit User', UserFormPage(c, u));
    });
    route('/admin/audit-logs', sa, (c) => PageSpec('Activity & Performance Insights', AuditInsightsPage(c)));
    for (final key in kMasters.keys) {
      final m = kMasters[key]!;
      route('$kMastersBase/$key', sa, (c) => PageSpec(m.title, MasterListPage(c, key)));
      route('$kMastersBase/$key/create', sa, (c) => PageSpec('Add ${m.one}', MasterFormPage(c, key, null)));
      route('$kMastersBase/$key/{id}/edit', sa, (c) {
        final r = P.db.find(m.table, c.id);
        return r == null ? _notFound('${m.one} not found.') : PageSpec('Edit ${m.one}', MasterFormPage(c, key, r));
      });
    }
    route('$kMastersBase/sectors', sa, (c) => PageSpec('Sector Master', SectorListPage(c)));
    route('$kMastersBase/sectors/create', sa, (c) => PageSpec('Add Sector', SectorFormPage(c, null)));
    route('$kMastersBase/sectors/{id}/edit', sa, (c) {
      final r = P.db.find('scheme_sectors', c.id);
      return r == null ? _notFound('Sector not found.') : PageSpec('Edit Sector', SectorFormPage(c, r));
    });
    _schemeScreens('$kMastersBase/schemes', sa, crumbs: true);
    route(kMastersBase, sa, (c) => PageSpec('Dashboard', AdminDashboard(c)), redirect: (c) => '/admin/dashboard');

    // Data Entry
    _schemeScreens('/data-entry/schemes', de);
    route('/data-entry/properties', de, (c) => PageSpec('Property Register', PropertyListPage(c)));
    route('/data-entry/properties/create', de, (c) => PageSpec('Add Property', PropertyFormPage(c, null)));
    route('/data-entry/properties/{id}', de, (c) {
      final p = P.db.find('properties', c.id);
      return PageSpec(p == null ? 'Property' : 'Property ${p['plot_no']}', PropertyDetailPage(c));
    });
    route('/data-entry/properties/{id}/edit', de, (c) {
      final p = P.db.find('properties', c.id);
      if (p == null || !truthy(p['is_active'])) return _notFound('Property not found.');
      return PageSpec('Edit Property', PropertyFormPage(c, p));
    }, redirect: (c) {
      final p = P.db.find('properties', c.id);
      if (p == null || !truthy(p['is_active']) || p['current_status'] == 'LISTED') return null;
      P.flash('warning', 'Only properties in "Listed" status can be edited. This one is ${label(p['current_status'])}.');
      return '/data-entry/properties';
    });

    // OIC
    route('/oic/properties', oic, (c) => PageSpec('Properties – Verification', OicListPage(c)));
    route('/oic/properties/{id}', oic, (c) {
      final p = P.db.find('properties', c.id);
      return PageSpec(p == null ? 'Property' : 'Property ${p['plot_no']}', OicDetailPage(c));
    });

    // Role User
    route('/role/tasks', ru, (c) => PageSpec('My Tasks', TaskListPage(c)));
    route('/role/tasks/{id}', ru, (c) {
      final t = P.db.find('verification_tasks', c.id);
      final r = t == null ? null : P.db.find('verification_rounds', t['round_id']);
      final p = r == null ? null : P.db.find('properties', r['property_id']);
      return PageSpec(p == null ? 'Task' : 'Verify Plot ${p['plot_no']}', TaskDetailPage(c));
    }, redirect: (c) {
      final db = P.db;
      final t = db.find('verification_tasks', c.id);
      if (t == null || !db.exists('verification_task_assignees', {'task_id': t['task_id'], 'user_id': c.uid})) return null;
      if (t['task_status'] == 'PENDING' || toInt(t['responded_by']) == c.uid) return null;
      final task = enrichTask(t);
      P.flash('info', 'Plot ${task['plot_no']} (${task['scheme_name']}) was already ${label(task['task_status']).toLowerCase()} by ${task['responded_by_name']} of your section.');
      return '/role/tasks';
    });
  }

  static void _schemeScreens(String base, String panel, {bool crumbs = false}) {
    route(base, panel, (c) => PageSpec('Scheme Master', SchemeListPage(c, base, crumbs: crumbs)));
    route('$base/create', panel, (c) => PageSpec('Create Scheme', SchemeFormPage(c, base, null, crumbs: crumbs)));
    route('$base/{id}/edit', panel, (c) {
      final s = P.db.find('schemes', c.id);
      return s == null ? _notFound('Scheme not found.') : PageSpec('Edit Scheme', SchemeFormPage(c, base, s, crumbs: crumbs));
    });
  }
}
