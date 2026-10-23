// PRAMAN — Super Admin: Activity & Performance Insights (js/pages/insights.js) — full audit trail with
// filters, a readable activity timeline and analytics cards (performers, sections, focus areas …).
import 'dart:convert';

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../ui/common.dart';
import '../ui/forms.dart';
import '../ui/lists.dart';

const Map<String, (String, IconData)> kAreas = {
  'AUTH': ('Auth', FA.rightToBracket),
  'VERIFICATION': ('Verification', FA.listCheck),
  'PROPERTY': ('Property', FA.building),
  'SCHEME': ('Scheme', FA.map),
  'MASTERS': ('Masters', FA.tableCellsLarge),
  'SYSTEM': ('System', FA.gear),
};

String areaOf(Rec a) {
  switch (str(a['entity_type'])) {
    case 'AUTH':
      return 'AUTH';
    case 'ROUND':
    case 'TASK':
      return 'VERIFICATION';
    case 'PROPERTY':
      return 'PROPERTY';
    case 'SCHEME':
      return 'SCHEME';
    case 'ZONE':
    case 'WARD':
    case 'ASSEMBLY':
    case 'PLOT_TYPE':
    case 'STATUS':
    case 'SECTOR':
      return 'MASTERS';
    case 'USER':
    case 'ROLE':
      return const ['IMPERSONATION_STARTED', 'IMPERSONATION_ENDED', 'ACCOUNT_LOCKED', 'PASSWORD_CHANGED'].contains(a['action']) ? 'AUTH' : 'MASTERS';
    default:
      return 'SYSTEM';
  }
}

const List<(String, String, IconData)> kQuick = [
  ('today', 'Today', FA.calendarDay),
  ('yesterday', 'Yesterday', FA.clockRotateLeft),
  ('7d', 'Last 7 Days', FA.calendarWeek),
  ('all', 'All Logs', FA.list),
  ('area:VERIFICATION', 'Verification updates', FA.listCheck),
  ('area:PROPERTY', 'Property updates', FA.building),
  ('area:SCHEME', 'Scheme updates', FA.map),
];

const List<(String, String, String)> kFocus = [
  ('PROPERTY', 'Property register updates', 'Data Entry should keep registering vacant properties and correcting plot details.'),
  ('VERIFICATION', 'Verification updates', 'OIC should send Listed properties and sections should respond to pending tasks within SLA.'),
  ('SCHEME', 'Scheme master updates', 'Scheme sectors and role-user mapping should be reviewed so new properties get the right verifiers.'),
];
const int kExpectedMin = 20;

Map<String, dynamic> _map(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};
String _dateOnly(Object? v) {
  final s = str(v);
  return s.length >= 10 ? s.substring(0, 10) : s;
}

String _short(Object? v) => '${fmtDay(v).length >= 6 ? fmtDay(v).substring(0, 6) : fmtDay(v)}, ${fmtTime(v)}';

String _fmtVal(String k, Object? v) {
  final db = P.db;
  if (v == null || str(v).isEmpty) return 'Not set';
  if (v is List) return v.join(', ');
  if (v is Map) return jsonEncode(v);
  if (k == 'is_active' || k == 'is_mandatory') return toInt(v) == 1 ? 'Yes' : 'No';
  if (k == 'role_id') return str(db.find('roles', v)?['role_name'] ?? v);
  if (k == 'zone_id') return db.zoneName(v);
  if (k == 'ward_id') return db.wardName(v);
  if (k == 'assembly_id') return db.assemblyName(v);
  if (k == 'panel') return App.panels[v] ?? str(v);
  if (RegExp(r'status|result|type$').hasMatch(k) && RegExp(r'^[A-Z_]+$').hasMatch(str(v))) return label(v);
  return str(v);
}

String? _propertyLabel(Object? id) {
  final db = P.db;
  final p = id == null ? null : db.find('properties', id);
  if (p == null) return null;
  final sc = db.find('schemes', p['scheme_id']);
  final se = db.find('scheme_sectors', p['sector_id']);
  return 'Plot ${p['plot_no']} (${p['property_code']}) · ${sc?['scheme_name']} / ${se?['sector_name']}';
}

String? subjectOf(Rec a) {
  if (a['property_id'] != null) return _propertyLabel(a['property_id']);
  final db = P.db;
  final t = a['entity_type'];
  final row = t == 'SCHEME' ? db.find('schemes', a['entity_id']) : (t == 'ROLE' ? db.find('roles', a['entity_id']) : (t == 'USER' ? db.find('users', a['entity_id']) : null));
  if (row == null) return null;
  if (t == 'SCHEME') return '${row['scheme_name']} (${row['scheme_code']})';
  if (t == 'ROLE') return '${row['role_name']} (${row['role_code']})';
  return '${row['name']} (${row['username']})';
}

String titleOf(Rec a) {
  const crud = {'CREATED': 'Created', 'UPDATED': 'Updated', 'ACTIVATED': 'Activated', 'DEACTIVATED': 'Deactivated'};
  final act = str(a['action']);
  if (crud.containsKey(act)) return '${crud[act]} ${label(a['entity_type'])}';
  const map = {
    'ROUND_INITIATED': 'Sent for Verification', 'REINITIATED': 'Re-sent for Verification', 'ROUND_CLOSED': 'Verification Round Closed',
    'TASK_APPROVED': 'Section Approved', 'TASK_REJECTED': 'Section Rejected', 'DATABASE_SEEDED': 'Database Seeded',
    'IMPERSONATION_STARTED': 'Login as User', 'IMPERSONATION_ENDED': 'Back to Super Admin', 'DIRECTION_PUBLISHED': 'Direction Published',
  };
  return map[act] ?? label(act);
}

/// Plain-language sentence for the details box.
String? describe(Rec a) {
  final n = _map(a['new_value']);
  final o = _map(a['old_value']);
  switch (str(a['action'])) {
    case 'LOGIN':
      return str(n['message'] ?? 'Login successful');
    case 'LOGOUT':
      return str(n['message'] ?? 'User logged out');
    case 'ACCOUNT_LOCKED':
      return 'Account locked for ${n['minutes'] ?? 15} minutes after repeated failed logins';
    case 'PASSWORD_CHANGED':
      return 'User changed own password';
    case 'PASSWORD_RESET':
      return 'Password reset by Super Admin (account unlocked)';
    case 'UNLOCKED':
      return 'Account unlocked by Super Admin';
    case 'IMPERSONATION_STARTED':
      return 'Super Admin opened the panel of ${n['target'] ?? 'a user'}';
    case 'IMPERSONATION_ENDED':
      return 'Super Admin returned to own panel';
    case 'ACCESS_DENIED':
      return 'Blocked access to ${n['path'] ?? 'a restricted page'}';
    case 'DATABASE_SEEDED':
      return 'Initial data loaded: ${n['roles'] ?? 0} roles, ${n['users'] ?? 0} users';
    case 'ROUND_INITIATED':
    case 'REINITIATED':
      {
        final roles = (n['roles'] as List? ?? const []).map((e) => e.toString()).toList();
        return 'Round R${n['round_no']} sent to ${roles.length} section(s): ${roles.join(', ')}'
            '${n['reason'] != null ? ' — Reason: ${n['reason']}' : ''}${o['status'] != null ? ' (previous status: ${label(o['status'])})' : ''}';
      }
    case 'ROUND_CLOSED':
      return 'Round closed — result: ${label(n['result'])}${n['result'] == 'READY' ? ' (property is Auction Ready)' : ' (property is Not Ready)'}';
    case 'TASK_APPROVED':
    case 'TASK_REJECTED':
    case 'TASK_SIMULATED_APPROVED':
    case 'TASK_SIMULATED_REJECTED':
      return '${n['role']} ${str(a['action']).contains('APPROVED') ? 'approved' : 'rejected'} round R${n['round_no']}${n['remarks'] != null ? ' — "${n['remarks']}"' : ''}';
    case 'DIRECTION_PUBLISHED':
      return 'Direction published for: ${(n['areas'] as List? ?? const []).join(', ')}';
    default:
      return null;
  }
}

List<(String, String, String)> changeRows(Rec a) {
  final o = _map(a['old_value']);
  final n = _map(a['new_value']);
  final keys = <String>[];
  for (final k in [...o.keys, ...n.keys]) {
    if (!k.startsWith('_') && k != 'message' && !keys.contains(k)) keys.add(k);
  }
  return keys
      .where((k) => a['old_value'] != null ? jsonEncode(o[k]) != jsonEncode(n[k]) : true)
      .map((k) => (label(k), _fmtVal(k, o[k]), _fmtVal(k, n[k])))
      .toList();
}

(String, String) rangeFor(String key) {
  final db = P.db;
  final t = db.today();
  if (key == 'today') return (t, t);
  if (key == 'yesterday') {
    final y = db.today(-1);
    return (y, y);
  }
  if (key == '7d') return (db.today(-6), t);
  return ('', '');
}

class AuditInsightsPage extends StatefulWidget {
  final RouteCtx ctx;
  const AuditInsightsPage(this.ctx, {super.key});
  @override
  State<AuditInsightsPage> createState() => _AuditInsightsPageState();
}

class _AuditInsightsPageState extends State<AuditInsightsPage> {
  late final FormModel _f;
  bool _filtersOpen = false;

  @override
  void initState() {
    super.initState();
    final q = widget.ctx.query;
    _f = FormModel({
      'q': q['q'], 'user': q['user'], 'role': q['role'], 'area': q['area'], 'from': q['from'], 'to': q['to'], 'skip_auth': q['skip_auth'] == '1' ? '1' : '',
    });
    _filtersOpen = ['user', 'role', 'area', 'from', 'to', 'skip_auth'].any((k) => (q[k] ?? '').isNotEmpty);
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  void _apply() {
    final q = widget.ctx.query;
    P.go(widget.ctx.path, {
      'q': _f.trimmed('q'), 'user': _f.value('user'), 'role': _f.value('role'), 'area': _f.value('area'), 'from': _f.value('from'), 'to': _f.value('to'),
      'skip_auth': _f.isOn('skip_auth') ? '1' : '', 'per_page': q['per_page'],
    }, true);
  }

  @override
  Widget build(BuildContext context) {
    final ctx = widget.ctx;
    final q = ctx.query;
    final db = P.db;
    final um = <int, Rec>{for (final u in db.all('users')) toInt(u['user_id'])!: u};
    final all = db.all('audit_logs')..sort((x, y) => (toInt(y['log_id']) ?? 0).compareTo(toInt(x['log_id']) ?? 0));
    final filtered = all.where((a) {
      final d = _dateOnly(a['performed_at']);
      if ((q['from'] ?? '').isNotEmpty && d.compareTo(q['from']!) < 0) return false;
      if ((q['to'] ?? '').isNotEmpty && d.compareTo(q['to']!) > 0) return false;
      if ((q['user'] ?? '').isNotEmpty && str(a['performed_by']) != q['user']) return false;
      if ((q['area'] ?? '').isNotEmpty && areaOf(a) != q['area']) return false;
      if (q['skip_auth'] == '1' && areaOf(a) == 'AUTH') return false;
      final role = q['role'] ?? '';
      if (role.isNotEmpty) {
        final u = um[toInt(a['performed_by'])];
        if (u == null) return false;
        if (role.startsWith('P:') && u['panel'] != role.substring(2)) return false;
        if (role.startsWith('R:') && str(u['role_id']) != role.substring(2)) return false;
      }
      return true;
    }).toList();
    final p = paginate<Rec>(filtered, ctx,
        sort: 'id',
        dir: 'desc',
        filters: const ['user', 'role', 'area', 'from', 'to', 'skip_auth', 'quick'],
        search: (a) => [a['action'], titleOf(a), a['performed_by_name'], a['performed_by_designation'], a['ip_address'], a['entity_type'], describe(a), subjectOf(a), jsonEncode(a['old_value']), jsonEncode(a['new_value'])],
        sortable: {'id': (a) => toNum(a['log_id'])});
    final s = (q['q'] ?? '').toLowerCase();
    final scope = s.isEmpty
        ? filtered
        : filtered.where((a) => [a['action'], titleOf(a), a['performed_by_name'], a['ip_address'], describe(a), subjectOf(a), jsonEncode(a['new_value'])].any((v) => str(v).toLowerCase().contains(s))).toList();
    final t = db.today();
    final activeUsers = scope.map((a) => a['performed_by']).where((x) => x != null).toSet().length;
    final modules = scope.map(areaOf).toSet().length;
    final last = scope.isEmpty ? null : scope.first;
    final quick = q['quick'] ?? (((q['from'] ?? '').isNotEmpty || (q['to'] ?? '').isNotEmpty || (q['area'] ?? '').isNotEmpty) ? '' : 'all');

    final userOpts = <String, String>{};
    final users = um.values.toList()..sort((a, b) => str(a['name']).compareTo(str(b['name'])));
    for (final u in users) {
      userOpts[str(u['user_id'])] = '${u['name']} (${App.panels[u['panel']]})';
    }
    final roleOpts = <String, String>{};
    App.panels.forEach((k, v) {
      if (k != 'ROLE_USER') roleOpts['P:$k'] = '$v panel';
    });
    final roles = db.all('roles')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
    for (final r in roles) {
      roleOpts['R:${r['role_id']}'] = '${r['role_name']} (Role User)';
    }

    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        const Crumbs([('Home', '/admin/dashboard'), ('Administration', null), ('Activity & Performance Insights', null)]),
        const PageHeader(icon: FA.chartLine, title: 'Activity & Performance Insights', subtitle: 'Monitor user activity, section performance, operational trends and audit history in one place.'),
        TileGrid(children: [
          StatCard(color: 'navy', icon: FA.database, value: fmtNum(scope.length), label: 'Filtered logs', sub: 'of ${fmtNum(all.length)} total'),
          StatCard(color: 'green', icon: FA.calendarDay, value: fmtNum(all.where((a) => _dateOnly(a['performed_at']) == t).length), label: 'Today'),
          StatCard(color: 'blue', icon: FA.userCheck, value: fmtNum(activeUsers), label: 'Active users'),
          StatCard(color: 'gold', icon: FA.layerGroup, value: fmtNum(modules), label: 'Modules'),
          StatCard(color: 'red', icon: FA.clock, value: last == null ? '—' : _short(last['performed_at']), label: 'Last activity'),
        ]),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(children: [
                for (final c in kQuick)
                  Padding(
                    padding: const EdgeInsets.only(right: 6),
                    child: _QuickChip(
                      text: c.$2,
                      icon: c.$3,
                      active: quick == c.$1,
                      onTap: () {
                        if (c.$1.startsWith('area:')) {
                          P.go(ctx.path, {'area': c.$1.substring(5), 'quick': c.$1}, true);
                        } else {
                          final r = rangeFor(c.$1);
                          P.go(ctx.path, {'from': r.$1, 'to': r.$2, 'quick': c.$1}, true);
                        }
                      },
                    ),
                  ),
              ]),
            ),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(
                child: FText(form: _f, name: 'q', label: 'Keyword', showLabel: false, placeholder: 'Action, user, details, IP…', prefix: FA.magnifyingGlass, maxLength: 100, action: TextInputAction.search, onSubmitted: (_) => _apply()),
              ),
              const SizedBox(width: 8),
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: IconBtn(FA.sliders, size: 46, variant: _filtersOpen ? 'primary' : 'light', tooltip: 'More filters', onPressed: () => setState(() => _filtersOpen = !_filtersOpen)),
              ),
            ]),
            AnimatedSize(
              duration: const Duration(milliseconds: 220),
              alignment: Alignment.topCenter,
              child: !_filtersOpen
                  ? const SizedBox(width: double.infinity)
                  : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      FSelect(form: _f, name: 'user', label: 'User', options: userOpts, placeholder: 'All Users'),
                      FSelect(form: _f, name: 'role', label: 'Role', options: roleOpts, placeholder: 'All Roles'),
                      FSelect(form: _f, name: 'area', label: 'Activity Area', options: {for (final e in kAreas.entries) e.key: e.value.$1}, placeholder: 'All Areas'),
                      Row(children: [
                        Expanded(child: FDate(form: _f, name: 'from', label: 'From')),
                        const SizedBox(width: 10),
                        Expanded(child: FDate(form: _f, name: 'to', label: 'To')),
                      ]),
                      FCheck(form: _f, name: 'skip_auth', title: 'Skip Auth', subtitle: 'Hide login / logout entries'),
                    ]),
            ),
            Wrap(spacing: 8, runSpacing: 8, children: [
              Btn('Apply', icon: FA.filter, onPressed: _apply),
              if (p.hasFilters) Btn('Clear filters', icon: FA.xmark, variant: 'light', onPressed: () => P.go(ctx.path, null, true)),
            ]),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: 14),
          child: Wrap(spacing: 6, runSpacing: 6, children: [
            _pill(FA.circleInfo, '${fmtNum(scope.length)} of ${fmtNum(all.length)} visible logs'),
            _pill(FA.calendar, (q['from'] ?? '').isNotEmpty || (q['to'] ?? '').isNotEmpty ? '${(q['from'] ?? '').isEmpty ? 'start' : q['from']} to ${(q['to'] ?? '').isEmpty ? 'today' : q['to']}' : 'All dates'),
            if ((q['area'] ?? '').isNotEmpty && kAreas.containsKey(q['area'])) _pill(kAreas[q['area']]!.$2, kAreas[q['area']]!.$1),
            if (q['skip_auth'] == '1') _pill(FA.eyeSlash, 'Auth hidden'),
          ]),
        ),
        AppCard(
          title: 'Activity Timeline',
          icon: FA.timeline,
          trailing: Text('${fmtNum(p.total)} record${p.total == 1 ? '' : 's'}', style: T.tiny),
          padding: EdgeInsets.zero,
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (p.items.isEmpty)
              EmptyState(
                icon: p.hasFilters ? FA.magnifyingGlass : FA.inbox,
                title: p.hasFilters ? 'No matching activity found' : 'No activity yet',
                text: 'Try a different date range or reset the filters.',
              )
            else
              Padding(padding: const EdgeInsets.fromLTRB(12, 12, 12, 0), child: Column(children: [for (final a in p.items) _TimelineItem(a, um)])),
            PaginationBar(p),
          ]),
        ),
        _TopPerformers(scope, um),
        const _SectionPerformance(),
        _Operational(scope),
        _ModuleActivity(scope),
        _MostActive(scope, um),
        _QuickInsight(scope),
      ]),
    );
  }

  Widget _pill(IconData icon, String text) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(999), border: Border.all(color: C.border)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 11, color: C.navy700),
          const SizedBox(width: 6),
          Text(text, style: T.s(12, weight: FontWeight.w500, color: C.navy800)),
        ]),
      );
}

class _QuickChip extends StatelessWidget {
  final String text;
  final IconData icon;
  final bool active;
  final VoidCallback onTap;
  const _QuickChip({required this.text, required this.icon, required this.active, required this.onTap});
  @override
  Widget build(BuildContext context) => Material(
        color: active ? C.navy700 : Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: BorderSide(color: active ? C.navy700 : C.border)),
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, size: 11, color: active ? C.gold400 : C.slate600),
              const SizedBox(width: 6),
              Text(text, style: T.s(12.5, weight: FontWeight.w600, color: active ? Colors.white : C.slate600)),
            ]),
          ),
        ),
      );
}

class _TimelineItem extends StatelessWidget {
  final Rec a;
  final Map<int, Rec> um;
  const _TimelineItem(this.a, this.um);

  @override
  Widget build(BuildContext context) {
    final u = um[toInt(a['performed_by'])];
    final area = kAreas[areaOf(a)]!;
    final desc = describe(a);
    final rows = changeRows(a);
    final subject = subjectOf(a);
    final act = str(a['action']);
    final isChange = const ['UPDATED', 'ACTIVATED', 'DEACTIVATED'].contains(act) && a['old_value'] != null;
    final isCreate = act == 'CREATED' && rows.isNotEmpty;
    final n = _map(a['new_value']);
    final tone = RegExp('REJECT|DEACTIVATED|LOCKED|DENIED').hasMatch(act)
        ? 'danger'
        : (RegExp('APPROVED|ACTIVATED|READY').hasMatch(act) || (act == 'ROUND_CLOSED' && n['result'] == 'READY') ? 'success' : '');
    final Color edge = tone == 'danger' ? C.red600 : (tone == 'success' ? C.green600 : C.navy600);
    final role = u != null && u['role_id'] != null ? P.db.find('roles', u['role_id']) : null;
    return FadeSlideIn(
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: C.border)),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(11),
          child: Container(
            decoration: BoxDecoration(border: Border(left: BorderSide(color: edge, width: 3))),
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Wrap(spacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
                Text(titleOf(a), style: T.s(14, weight: FontWeight.w700, color: C.navy800)),
                if (a['entity_id'] != null && a['entity_type'] != 'AUTH') Text('#${a['entity_id']}', style: T.s(12, weight: FontWeight.w600, color: C.muted)),
              ]),
              const SizedBox(height: 6),
              Wrap(spacing: 10, runSpacing: 4, children: [
                _meta(FA.user, '${a['performed_by_name'] ?? 'System'}${a['performed_by_designation'] != null ? ' (${a['performed_by_designation']})' : ''}'),
                if (u != null) _meta(FA.shieldHalved, '${App.panels[u['panel']]}${role != null ? ' · ${role['role_name']}' : ''}'),
                _meta(area.$2, area.$1),
              ]),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(8), border: Border.all(color: const Color(0xFFEEF2F7))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Text('${titleOf(a)} - ${subject != null ? 'Activity details for $subject' : 'Activity Details'}', style: T.s(12, weight: FontWeight.w600, color: C.slate600)),
                  if ((isChange || isCreate) && desc != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(desc, style: T.s(13))),
                  if (isChange || isCreate)
                    Padding(
                      padding: const EdgeInsets.only(top: 6),
                      child: Column(children: [
                        for (final r in rows)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              SizedBox(width: 100, child: Text(r.$1, style: T.s(12, weight: FontWeight.w700, color: C.navy800))),
                              if (isChange) Expanded(child: Text(r.$2, style: T.s(12, color: C.red600).copyWith(decoration: TextDecoration.lineThrough))),
                              if (isChange) const Padding(padding: EdgeInsets.symmetric(horizontal: 4), child: Icon(FA.arrowRightLong, size: 10, color: C.muted)),
                              Expanded(child: Text(r.$3, style: T.s(12, weight: FontWeight.w600, color: C.green600))),
                            ]),
                          ),
                      ]),
                    )
                  else
                    Padding(padding: const EdgeInsets.only(top: 4), child: Text(desc ?? 'No additional details', style: T.s(13))),
                ]),
              ),
              const SizedBox(height: 8),
              Wrap(spacing: 10, runSpacing: 4, children: [
                _meta(FA.calendar, fmtDay(a['performed_at'])),
                _meta(FA.clock, fmtTime(a['performed_at'])),
                Text(str(a['ip_address']), style: T.mono.copyWith(fontSize: 11.5, color: C.muted)),
                Text('Log #${a['log_id']}', style: T.tiny),
              ]),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _meta(IconData icon, String text) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 10, color: C.muted),
        const SizedBox(width: 5),
        Text(text, style: T.s(12, color: C.slate600)),
      ]);
}

/* ======================= side cards ======================= */

Widget _rank(int i, String name, String sub, int pct, Color barColor, List<Widget> minis) => Container(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(color: i == 0 ? C.gold500 : C.navy100, shape: BoxShape.circle),
            alignment: Alignment.center,
            child: Text('${i + 1}', style: T.s(11, weight: FontWeight.w700, color: i == 0 ? Colors.white : C.navy700)),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(name, style: T.s(13.5, weight: FontWeight.w700)),
              Text(sub, style: T.tiny),
            ]),
          ),
          Text('$pct%', style: T.s(14, weight: FontWeight.w800, color: C.navy800)),
        ]),
        const SizedBox(height: 6),
        ProgressBar(pct.toDouble(), color: barColor, height: 6),
        const SizedBox(height: 6),
        Wrap(spacing: 5, runSpacing: 5, children: minis),
      ]),
    );

void _showAll(BuildContext context, String title, String sub, IconData icon, List<Widget> rows) {
  showDialog<void>(
    context: context,
    builder: (c) => Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.all(14),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 36, height: 36, decoration: BoxDecoration(color: C.gold100, borderRadius: BorderRadius.circular(10)), alignment: Alignment.center, child: Icon(icon, size: 15, color: C.gold600)),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: T.s(16, weight: FontWeight.w700, color: C.navy800)), Text(sub, style: T.small)])),
            IconButton(onPressed: () => Navigator.of(c).pop(), icon: const Icon(FA.xmark, size: 15, color: C.slate600)),
          ]),
        ),
        const Divider(height: 1, color: C.border),
        Flexible(child: ListView(shrinkWrap: true, padding: const EdgeInsets.all(14), children: rows)),
      ]),
    ),
  );
}

class _TopPerformers extends StatelessWidget {
  final List<Rec> scope;
  final Map<int, Rec> um;
  const _TopPerformers(this.scope, this.um);

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final map = <int, Map<String, dynamic>>{};
    for (final a in scope) {
      final by = toInt(a['performed_by']);
      if (a['entity_type'] != 'TASK' || by == null) continue;
      final r = map.putIfAbsent(by, () => {'approved': 0, 'rejected': 0, 'last': a['performed_at'], 'logs': 0});
      r['logs'] = (r['logs'] as int) + 1;
      if (str(a['action']).contains('APPROVED')) {
        r['approved'] = (r['approved'] as int) + 1;
      } else {
        r['rejected'] = (r['rejected'] as int) + 1;
      }
      if (str(a['performed_at']).compareTo(str(r['last'])) > 0) r['last'] = a['performed_at'];
    }
    final list = map.entries.where((e) => um[e.key] != null).map((e) {
      final assigned = db.where('verification_task_assignees', {'user_id': e.key});
      final pending = assigned.where((x) {
        final t = db.find('verification_tasks', x['task_id']);
        return t != null && t['task_status'] == 'PENDING' && db.find('verification_rounds', t['round_id'])?['round_status'] == 'OPEN';
      }).length;
      final m = e.value;
      final done = (m['approved'] as int) + (m['rejected'] as int);
      return (um[e.key]!, m, pending, assigned.isEmpty ? 0 : (done * 100 / assigned.length).round());
    }).toList()
      ..sort((a, b) {
        final c = (b.$2['logs'] as int).compareTo(a.$2['logs'] as int);
        return c != 0 ? c : b.$4.compareTo(a.$4);
      });
    Widget row(int i, (Rec, Map<String, dynamic>, int, int) x) {
      final role = db.find('roles', x.$1['role_id']);
      return _rank(i, str(x.$1['name']), '${role?['role_name'] ?? ''} · ${x.$2['logs']} response(s) · Last: ${_short(x.$2['last'])}', x.$4, C.navy700, [
        MiniTag('Approved: ${x.$2['approved']}', icon: FA.check, tone: 'ok'),
        MiniTag('Rejected: ${x.$2['rejected']}', icon: FA.xmark, tone: (x.$2['rejected'] as int) > 0 ? 'bad' : ''),
        MiniTag('Pending: ${x.$3}', icon: FA.hourglassHalf, tone: x.$3 > 0 ? 'warn' : ''),
      ]);
    }

    return AppCard(
      title: 'Top Role User Performers',
      icon: FA.rankingStar,
      trailing: list.length > 3
          ? Btn('View All', icon: FA.list, variant: 'outline', size: 'sm', onPressed: () => _showAll(context, 'Role User Performers', '${list.length} role user(s) · ranked by verification responses in the selected period', FA.rankingStar, [for (var i = 0; i < list.length; i++) row(i, list[i])]))
          : null,
      child: list.isEmpty ? Text('No verification responses in this period.', style: T.small) : Column(children: [for (var i = 0; i < list.length && i < 3; i++) row(i, list[i])]),
    );
  }
}

class _SectionPerformance extends StatelessWidget {
  const _SectionPerformance();

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final t = db.today();
    final roles = db.all('roles')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
    final rows = <Map<String, dynamic>>[];
    for (final r in roles) {
      final tasks = db.where('verification_tasks', {'role_id': r['role_id']}).map((x) => <String, dynamic>{...x, 'round': db.find('verification_rounds', x['round_id']) ?? <String, dynamic>{}}).toList();
      if (tasks.isEmpty) continue;
      final open = tasks.where((x) => x['task_status'] == 'PENDING' && (x['round'] as Rec)['round_status'] == 'OPEN').toList();
      final done = tasks.where((x) => x['task_status'] != 'PENDING').toList();
      final overdue = open.where((x) => str(x['sla_due_date']).compareTo(t) < 0).length;
      final days = done.map((x) {
        final a = parseDate(x['responded_at']);
        final b = parseDate((x['round'] as Rec)['initiated_at']);
        return a != null && b != null ? a.difference(b).inMinutes / 1440 : 0.0;
      }).toList();
      final avg = days.isEmpty ? 0.0 : days.reduce((s, d) => s + d) / days.length;
      final within = done.where((x) => _dateOnly(x['responded_at']).compareTo(str(x['sla_due_date'])) <= 0).length;
      final relevant = done.length + open.length;
      rows.add({
        'r': r, 'total': tasks.length, 'done': done.length, 'open': open.length, 'overdue': overdue, 'avg': avg, 'within': within,
        'approved': done.where((x) => x['task_status'] == 'APPROVED').length, 'rejected': done.where((x) => x['task_status'] == 'REJECTED').length,
        'pct': relevant > 0 ? (done.length * 100 / relevant).round() : 0,
      });
    }
    rows.sort((a, b) {
      final c = (b['pct'] as int).compareTo(a['pct'] as int);
      return c != 0 ? c : (b['done'] as int).compareTo(a['done'] as int);
    });
    Widget row(int i, Map<String, dynamic> x) {
      final r = x['r'] as Rec;
      return _rank(i, str(r['role_name']), '${x['total']} task(s) · SLA ${r['sla_days']} day(s)', x['pct'] as int, (x['overdue'] as int) > 0 ? C.amber600 : C.green600, [
        MiniTag('Responded: ${x['done']}'),
        MiniTag('Pending: ${x['open']}', tone: (x['open'] as int) > 0 ? 'warn' : ''),
        MiniTag('Overdue: ${x['overdue']}', tone: (x['overdue'] as int) > 0 ? 'bad' : ''),
        MiniTag('Approved: ${x['approved']}', tone: 'ok'),
        MiniTag('Rejected: ${x['rejected']}', tone: (x['rejected'] as int) > 0 ? 'bad' : ''),
        MiniTag('Avg response: ${(x['avg'] as double).toStringAsFixed(1)} d'),
        MiniTag('Within SLA: ${x['within']}/${x['done']}'),
      ]);
    }

    return AppCard(
      title: 'Section Verification Performance',
      icon: FA.usersGear,
      trailing: rows.length > 3
          ? Btn('View All', icon: FA.list, variant: 'outline', size: 'sm', onPressed: () => _showAll(context, 'Section Verification Performance', '${rows.length} section(s) · ranked by completion · all verification rounds', FA.usersGear, [for (var i = 0; i < rows.length; i++) row(i, rows[i])]))
          : null,
      child: rows.isEmpty ? Text('No verification tasks yet.', style: T.small) : Column(children: [for (var i = 0; i < rows.length && i < 3; i++) row(i, rows[i])]),
    );
  }
}

class _Operational extends StatelessWidget {
  final List<Rec> scope;
  const _Operational(this.scope);

  @override
  Widget build(BuildContext context) {
    final total = scope.isEmpty ? 1 : scope.length;
    final items = kFocus.map((f) {
      final n = scope.where((a) => areaOf(a) == f.$1).length;
      final pct = (n * 100 / total).round();
      return (f, n, pct, pct >= kExpectedMin);
    }).toList();
    final weak = items.where((x) => !x.$4).toList();
    return AppCard(
      title: 'Operational Analysis',
      icon: FA.chartLine,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Text(weak.isNotEmpty ? '${weak.length} focus area(s) need attention before publishing directions.' : 'All focus areas are active as expected.', style: T.s(13.5, weight: FontWeight.w700)),
        const SizedBox(height: 10),
        for (final x in items)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: x.$4 ? const Color(0xFFF6FBF8) : const Color(0xFFFFFBEB), borderRadius: BorderRadius.circular(10), border: Border.all(color: x.$4 ? const Color(0xFFBBF7D0) : const Color(0xFFFDE68A))),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Icon(kAreas[x.$1.$1]!.$2, size: 12, color: C.navy700),
                const SizedBox(width: 8),
                Expanded(child: Text(x.$1.$2, style: T.s(13.5, weight: FontWeight.w700))),
              ]),
              const SizedBox(height: 6),
              Wrap(spacing: 5, runSpacing: 5, children: [
                MiniTag('Actual: ${x.$3}%', tone: x.$4 ? 'ok' : 'warn'),
                const MiniTag('Expected minimum: $kExpectedMin%'),
                MiniTag('Logs: ${x.$2}'),
              ]),
              const SizedBox(height: 6),
              ProgressBar(x.$3 * 100 / kExpectedMin > 100 ? 100.0 : x.$3 * 100 / kExpectedMin, color: x.$4 ? C.green600 : C.amber600, height: 6),
              const SizedBox(height: 6),
              Text(
                x.$4 ? '${x.$1.$2} are active as expected.' : '${x.$1.$2} are below the expected share in this period.\nSuggested direction: ${x.$1.$3}',
                style: T.s(12.5, color: C.slate600, height: 1.4),
              ),
            ]),
          ),
        Btn('Publish Direction', icon: FA.bullhorn, block: true, onPressed: () {
          final areas = (weak.isNotEmpty ? weak : items).map((x) => x.$1.$2).toList();
          confirmAction(
            confirm: 'Publish directions for ${weak.isNotEmpty ? weak.length : 'all'} focus area(s)? This is recorded in the audit log.',
            okText: 'Yes, publish',
            action: () {
              final db = P.db;
              db.audit('SYSTEM', null, 'DIRECTION_PUBLISHED', null, {'areas': areas});
              db.notify([...db.panelUsers('DATA_ENTRY'), ...db.panelUsers('OIC')],
                  type: 'SYSTEM', tone: 'warning', icon: 'fa-bullhorn', title: 'New direction from Super Admin', message: 'Focus areas: ${areas.join(', ')}.', link: '/notifications');
              P.flash('success', 'Direction published for: ${areas.join(', ')}.');
              P.render();
            },
          );
        }),
      ]),
    );
  }
}

class _ModuleActivity extends StatelessWidget {
  final List<Rec> scope;
  const _ModuleActivity(this.scope);

  @override
  Widget build(BuildContext context) {
    final groups = <String, List<Rec>>{};
    for (final a in scope) {
      groups.putIfAbsent(areaOf(a), () => []).add(a);
    }
    final keys = groups.keys.toList()..sort((a, b) => groups[b]!.length.compareTo(groups[a]!.length));
    final max = groups.values.fold<int>(1, (m, l) => l.length > m ? l.length : m);
    return AppCard(
      title: 'Module Activity',
      icon: FA.chartSimple,
      child: keys.isEmpty
          ? Text('No activity in this period.', style: T.small)
          : Column(children: [
              for (final k in keys)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Row(children: [
                      Icon(kAreas[k]!.$2, size: 12, color: C.navy700),
                      const SizedBox(width: 8),
                      Expanded(child: Text(kAreas[k]!.$1, style: T.s(13))),
                      Text('${groups[k]!.length}', style: T.s(13, weight: FontWeight.w700)),
                    ]),
                    const SizedBox(height: 4),
                    ProgressBar(groups[k]!.length * 100 / max, height: 6),
                    const SizedBox(height: 4),
                    ..._top(groups[k]!).map((e) => Padding(padding: const EdgeInsets.only(left: 4, top: 2), child: Text('• ${e.key} (${e.value})', style: T.s(12, color: C.slate600)))),
                  ]),
                ),
            ]),
    );
  }

  List<MapEntry<String, int>> _top(List<Rec> rows) {
    final m = <String, int>{};
    for (final a in rows) {
      final d = titleOf(a);
      m[d] = (m[d] ?? 0) + 1;
    }
    final l = m.entries.toList()..sort((a, b) => b.value.compareTo(a.value));
    return l.take(3).toList();
  }
}

class _MostActive extends StatelessWidget {
  final List<Rec> scope;
  final Map<int, Rec> um;
  const _MostActive(this.scope, this.um);

  @override
  Widget build(BuildContext context) {
    final m = <int, Map<String, dynamic>>{};
    for (final a in scope) {
      final by = toInt(a['performed_by']);
      if (by == null) continue;
      final r = m.putIfAbsent(by, () => {'n': 0, 'last': a['performed_at']});
      r['n'] = (r['n'] as int) + 1;
      if (str(a['performed_at']).compareTo(str(r['last'])) > 0) r['last'] = a['performed_at'];
    }
    final list = m.keys.toList()..sort((a, b) => (m[b]!['n'] as int).compareTo(m[a]!['n'] as int));
    return AppCard(
      title: 'Most Active Users',
      icon: FA.userClock,
      child: list.isEmpty
          ? Text('No user activity in this period.', style: T.small)
          : Column(children: [
              for (final id in list.take(6))
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(children: [
                    Avatar(str(um[id]?['name'] ?? 'User'), size: 32),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(str(um[id]?['name'] ?? 'User #$id'), style: T.s(13.5, weight: FontWeight.w700)),
                        Text('${um[id]?['designation'] ?? ''} · ${_short(m[id]!['last'])}', style: T.tiny),
                      ]),
                    ),
                    Material(
                      color: C.navy700,
                      borderRadius: BorderRadius.circular(999),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(999),
                        onTap: () => P.go('/admin/audit-logs', {'user': id}, true),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          child: Text('${m[id]!['n']}', style: T.s(12.5, weight: FontWeight.w700, color: Colors.white)),
                        ),
                      ),
                    ),
                  ]),
                ),
            ]),
    );
  }
}

class _QuickInsight extends StatelessWidget {
  final List<Rec> scope;
  const _QuickInsight(this.scope);

  @override
  Widget build(BuildContext context) {
    if (scope.isEmpty) {
      return AppCard(title: 'Quick Insight', icon: FA.lightbulb, child: Text('Select a wider date range to see insights.', style: T.small));
    }
    final hours = <int, int>{};
    final days = <String, int>{};
    for (final a in scope) {
      final s = str(a['performed_at']);
      final h = s.length >= 13 ? (int.tryParse(s.substring(11, 13)) ?? 0) : 0;
      hours[h] = (hours[h] ?? 0) + 1;
      final d = _dateOnly(a['performed_at']);
      days[d] = (days[d] ?? 0) + 1;
    }
    final ph = (hours.entries.toList()..sort((a, b) => b.value.compareTo(a.value))).first;
    final pd = (days.entries.toList()..sort((a, b) => b.value.compareTo(a.value))).first;
    final h12 = '${ph.key % 12 == 0 ? 12 : ph.key % 12} ${ph.key >= 12 ? 'PM' : 'AM'}';
    final appr = scope.where((a) => a['action'] == 'TASK_APPROVED').length;
    final rej = scope.where((a) => a['action'] == 'TASK_REJECTED').length;
    final failed = P.db.count('login_attempts', {'success': 0});
    Widget li(IconData icon, List<InlineSpan> spans) => Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 28, height: 28, decoration: BoxDecoration(color: C.gold100, borderRadius: BorderRadius.circular(8)), alignment: Alignment.center, child: Icon(icon, size: 12, color: C.gold600)),
            const SizedBox(width: 10),
            Expanded(child: Text.rich(TextSpan(style: T.s(13, height: 1.45), children: spans))),
          ]),
        );
    const b = TextStyle(fontWeight: FontWeight.w700);
    return AppCard(
      title: 'Quick Insight',
      icon: FA.lightbulb,
      child: Column(children: [
        li(FA.clock, [const TextSpan(text: 'Peak activity is around '), TextSpan(text: h12, style: b), TextSpan(text: ' with ${ph.value} logged action(s).')]),
        li(FA.calendarCheck, [const TextSpan(text: 'Busiest day: '), TextSpan(text: fmtDay(pd.key), style: b), TextSpan(text: ' (${pd.value} action(s)).')]),
        if (appr + rej > 0)
          li(FA.scaleBalanced, [
            const TextSpan(text: 'Section decisions: '),
            TextSpan(text: '$appr approved', style: b),
            const TextSpan(text: ' / '),
            TextSpan(text: '$rej rejected', style: b),
            TextSpan(text: ' (${(appr * 100 / (appr + rej)).round()}% approval).'),
          ]),
        li(FA.userLock, [const TextSpan(text: 'Failed login attempts recorded: '), TextSpan(text: '$failed', style: b), const TextSpan(text: '.')]),
      ]),
    );
  }
}
