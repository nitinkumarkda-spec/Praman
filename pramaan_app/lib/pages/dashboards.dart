// PRAMAN — dashboards of all 4 panels (js/pages/dashboards.js): clickable stat cards + charts.
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/lookups.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../ui/charts.dart';
import '../ui/common.dart';
import 'admin.dart' show isLocked;

/// statusParts(): only non-zero statuses, with labels and chart colours.
(List<String>, List<String>, List<num>, List<Color>) statusParts(Map<String, int> counts) {
  final keys = counts.keys.where((k) => (counts[k] ?? 0) > 0).toList();
  return (keys, keys.map(label).toList(), keys.map((k) => counts[k] ?? 0).toList(), keys.map(C.statusColor).toList());
}

List<Rec> sectionOutcomes() {
  final db = P.db;
  final roles = db.all('roles')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
  final t = db.today();
  return roles.map((r) {
    final tasks = db.where('verification_tasks', {'role_id': r['role_id']});
    final openPending = tasks.where((x) => x['task_status'] == 'PENDING' && db.find('verification_rounds', x['round_id'])?['round_status'] == 'OPEN').toList();
    return <String, dynamic>{
      'name': str(r['role_name']).replaceAll(' Cell', ''),
      'approved': tasks.where((x) => x['task_status'] == 'APPROVED').length,
      'rejected': tasks.where((x) => x['task_status'] == 'REJECTED').length,
      'pending': openPending.where((x) => str(x['sla_due_date']).compareTo(t) >= 0).length,
      'overdue': openPending.where((x) => str(x['sla_due_date']).compareTo(t) < 0).length,
    };
  }).toList();
}

/// Two charts side by side on wide screens, stacked on phones.
class ChartGrid extends StatelessWidget {
  final List<Widget> children;
  final int columns;
  const ChartGrid({super.key, required this.children, this.columns = 2});
  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final cols = c.maxWidth >= 1000 ? columns : (c.maxWidth >= 700 ? math.min(2, columns) : 1);
      if (cols == 1) return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children);
      final w = (c.maxWidth - 16 * (cols - 1)) / cols;
      return Wrap(spacing: 16, children: [for (final ch in children) SizedBox(width: w, child: ch)]);
    });
  }
}

Widget _welcome(RouteCtx ctx, String sub, List<Widget> actions) => PageHeader(title: 'Welcome, ${ctx.user['name']}', subtitle: sub, actions: actions);

/* ======================= SUPER ADMIN ======================= */

class AdminDashboard extends StatelessWidget {
  final RouteCtx ctx;
  const AdminDashboard(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final today = db.today();
    final usersActive = db.count('users', {'is_active': 1});
    final failed24 = db.countWhere('login_attempts', (a) => a['success'] == 0 && str(a['attempted_at']).compareTo(db.now(-1440)) > 0);
    final logsToday = db.countWhere('audit_logs', (a) => str(a['performed_at']).startsWith(today));
    final st = statusParts(propertyStatusCounts());
    final roles = db.all('roles')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
    return PageBody(children: [
      _welcome(ctx, 'System overview — users, roles, schemes, properties, verification and activity.', [
        Btn('Activity & Audit', icon: FA.chartLine, variant: 'light', onPressed: () => P.go('/admin/audit-logs')),
        Btn('Add User', icon: FA.userPlus, onPressed: () => P.go('/admin/users/create')),
      ]),
      TileGrid(children: [
        StatCard(color: 'navy', icon: FA.users, value: fmtNum(db.count('users')), label: 'Users', sub: '${fmtNum(usersActive)} active', onTap: () => P.go('/admin/users')),
        StatCard(color: 'gold', icon: FA.sitemap, value: fmtNum(db.count('roles')), label: 'Roles / Sections', sub: '${fmtNum(db.count('roles', {'is_active': 1}))} active', onTap: () => P.go('/admin/roles')),
        StatCard(color: 'blue', icon: FA.map, value: fmtNum(db.count('schemes')), label: 'Schemes', sub: '${fmtNum(db.count('schemes', {'is_active': 1}))} active', onTap: () => P.go('/admin/audit-logs', {'area': 'SCHEME', 'quick': 'area:SCHEME'})),
        StatCard(color: 'green', icon: FA.building, value: fmtNum(db.count('properties', {'is_active': 1})), label: 'Properties', sub: 'registered', onTap: () => P.go('/admin/audit-logs', {'area': 'PROPERTY', 'quick': 'area:PROPERTY'})),
        StatCard(color: 'red', icon: FA.userLock, value: fmtNum(db.countWhere('users', isLocked)), label: 'Locked accounts', sub: '${fmtNum(failed24)} failed logins (24h)', onTap: () => P.go('/admin/users', {'status': 'locked'})),
        StatCard(color: 'navy', icon: FA.fingerprint, value: fmtNum(logsToday), label: 'Actions today', sub: 'audit entries', onTap: () => P.go('/admin/audit-logs', {'quick': 'today', 'from': today, 'to': today})),
      ]),
      ChartGrid(children: [
        ChartCard(
          title: 'Property status',
          icon: FA.chartPie,
          sub: 'tap a slice',
          empty: DonutChart.isEmpty(st.$3),
          child: DonutChart(labels: st.$2, values: st.$3, colors: st.$4, onTap: (i) => P.go('/admin/audit-logs', {'area': 'PROPERTY', 'quick': 'area:PROPERTY', 'q': st.$2[i]})),
        ),
        SectionOutcomeCard(rows: sectionOutcomes(), title: 'Section verification outcomes', sub: 'tasks per section, all rounds'),
      ]),
      ChartGrid(children: [
        AppCard(
          title: 'Users per role',
          icon: FA.sitemap,
          trailing: LinkText('Role Master', icon: FA.arrowRight, onTap: () => P.go('/admin/roles')),
          padding: EdgeInsets.zero,
          child: Column(children: [
            Container(
              color: C.navy50,
              padding: const EdgeInsets.fromLTRB(14, 9, 14, 9),
              child: Row(children: [
                Expanded(child: Text('ROLE / SECTION', style: _th)),
                SizedBox(width: 52, child: Text('USERS', textAlign: TextAlign.right, style: _th)),
                SizedBox(width: 58, child: Text('ACTIVE', textAlign: TextAlign.right, style: _th)),
              ]),
            ),
            for (final r in roles)
              InkWell(
                onTap: () => P.go('/admin/users', {'role_id': r['role_id']}),
                child: Container(
                  padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                  decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFEEF2F7)))),
                  child: Row(children: [
                    Expanded(
                      child: Row(children: [
                        Flexible(child: Text(str(r['role_name']), style: T.s(13.5, weight: FontWeight.w600, color: C.blue600))),
                        const SizedBox(width: 6),
                        if (!truthy(r['is_active'])) const Pill('Inactive', size: 10.5),
                      ]),
                    ),
                    SizedBox(width: 52, child: Text('${db.count('users', {'role_id': r['role_id']})}', textAlign: TextAlign.right, style: T.s(13.5))),
                    SizedBox(width: 58, child: Text('${db.count('users', {'role_id': r['role_id'], 'is_active': 1})}', textAlign: TextAlign.right, style: T.s(13.5, weight: FontWeight.w600))),
                  ]),
                ),
              ),
          ]),
        ),
        ActivityCard(rows: recentActivity(8), title: 'Recent system activity'),
      ]),
    ]);
  }
}

final TextStyle _th = T.s(11, weight: FontWeight.w700, color: C.navy800, spacing: .4);

/* ======================= DATA ENTRY ======================= */

class DataEntryDashboard extends StatelessWidget {
  final RouteCtx ctx;
  const DataEntryDashboard(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final me = ctx.uid;
    final activeSectors = db.countWhere('scheme_sectors', (s) {
      final sc = db.find('schemes', s['scheme_id']);
      return truthy(s['is_active']) && sc != null && truthy(sc['is_active']);
    });
    final mine = propertyStatusCounts(me);
    final st = statusParts(mine);
    final types = propertyTypes();
    final typeKeys = types.keys.toList();
    final typeCounts = typeKeys.map((t) => db.count('properties', {'property_type': t, 'is_active': 1})).toList();
    final sch = db.all('schemes');
    final days = lastDays(14);
    final props = db.where('properties', {'is_active': 1});
    final entries = [
      ChartSeries('All', countByDay(props, 'created_at', days.$1), C.chNavy),
      ChartSeries('By me', countByDay(props, 'created_at', days.$1, (p) => toInt(p['created_by']) == me), C.chGold),
    ];
    final perScheme = [
      ChartSeries('Properties', sch.map((s) => db.count('properties', {'scheme_id': s['scheme_id'], 'is_active': 1})).toList(), C.chNavy),
      ChartSeries('Active sectors', sch.map((s) => db.schemeSectors(s['scheme_id'], activeOnly: true).length).toList(), C.chGold),
    ];
    final area = [
      ChartSeries('Area (sq m)', sch.map((s) => db.where('properties', {'scheme_id': s['scheme_id'], 'is_active': 1}).fold<num>(0, (t, p) => t + toNum(p['area_sqm'])).round()).toList(), C.chTeal),
    ];
    return PageBody(children: [
      _welcome(ctx, 'Scheme, sector and property register.', [
        Btn('Create Scheme', icon: FA.map, variant: 'light', onPressed: () => P.go('/data-entry/schemes/create')),
        Btn('Add Property', icon: FA.circlePlus, onPressed: () => P.go('/data-entry/properties/create')),
      ]),
      TileGrid(children: [
        StatCard(color: 'navy', icon: FA.map, value: fmtNum(db.count('schemes', {'is_active': 1})), label: 'Active schemes', onTap: () => P.go('/data-entry/schemes', {'status': 'active'})),
        StatCard(color: 'gold', icon: FA.tableCells, value: fmtNum(activeSectors), label: 'Active sectors', onTap: () => P.go('/data-entry/schemes')),
        StatCard(color: 'blue', icon: FA.building, value: fmtNum(db.count('properties', {'is_active': 1})), label: 'All properties', onTap: () => P.go('/data-entry/properties')),
        StatCard(color: 'green', icon: FA.list, value: fmtNum(db.count('properties', {'is_active': 1, 'current_status': 'LISTED'})), label: 'Listed (editable)', onTap: () => P.go('/data-entry/properties', {'status': 'LISTED'})),
        StatCard(color: 'red', icon: FA.userPen, value: fmtNum(db.count('properties', {'is_active': 1, 'created_by': me})), label: 'Entered by me', onTap: () => P.go('/data-entry/properties')),
      ]),
      ChartGrid(columns: 3, children: [
        ChartCard(
          title: 'My properties by status',
          icon: FA.chartPie,
          sub: 'tap a slice',
          empty: DonutChart.isEmpty(st.$3),
          child: DonutChart(labels: st.$2, values: st.$3, colors: st.$4, onTap: (i) => P.go('/data-entry/properties', {'status': st.$1[i]})),
        ),
        ChartCard(
          title: 'Properties by type',
          icon: FA.shapes,
          empty: DonutChart.isEmpty(typeCounts),
          child: DonutChart(labels: typeKeys.map((t) => types[t]!).toList(), values: typeCounts, colors: C.series, doughnut: false, onTap: (i) => P.go('/data-entry/properties', {'type': typeKeys[i]})),
        ),
        ChartCard(
          title: 'Area by scheme (sq m)',
          icon: FA.rulerCombined,
          empty: BarChart.isEmpty(area),
          child: BarChart(labels: sch.map((s) => str(s['scheme_code'])).toList(), series: area, horizontal: true),
        ),
      ]),
      ChartGrid(children: [
        ChartCard(title: 'Properties entered — last 14 days', icon: FA.chartLine, empty: LineChart.isEmpty(entries), child: LineChart(labels: days.$2, series: entries)),
        ChartCard(
          title: 'Properties per scheme',
          icon: FA.map,
          onView: () => P.go('/data-entry/schemes'),
          empty: BarChart.isEmpty(perScheme),
          child: BarChart(labels: sch.map((s) => str(s['scheme_code'])).toList(), series: perScheme, onTap: (i) => P.go('/data-entry/properties', {'scheme_id': sch[i]['scheme_id']})),
        ),
      ]),
      ChartGrid(children: [
        StatusOverviewCard(counts: mine, title: 'My properties by status'),
        ActivityCard(rows: recentActivity(8, me), title: 'My recent activity'),
      ]),
    ]);
  }
}

/* ======================= OIC ======================= */

class OicDashboard extends StatelessWidget {
  final RouteCtx ctx;
  const OicDashboard(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final sc = propertyStatusCounts();
    final open = db.count('verification_rounds', {'round_status': 'OPEN'});
    final ready = db.count('verification_rounds', {'round_status': 'CLOSED', 'consolidated_result': 'READY'});
    final notReady = db.count('verification_rounds', {'round_status': 'CLOSED', 'consolidated_result': 'NOT_READY'});
    final openIds = db.where('verification_rounds', {'round_status': 'OPEN'}).map((r) => toInt(r['round_id'])).toSet();
    final pending = db.countWhere('verification_tasks', (t) => t['task_status'] == 'PENDING' && openIds.contains(toInt(t['round_id'])));
    final st = statusParts(sc);
    final sch = db.all('schemes');
    final readiness = [
      for (final k in const ['AUCTION_READY', 'UNDER_VERIFICATION', 'NOT_READY', 'LISTED'])
        ChartSeries(label(k), sch.map((s) => db.count('properties', {'scheme_id': s['scheme_id'], 'current_status': k, 'is_active': 1})).toList(), C.statusColor(k)),
    ];
    final so = sectionOutcomes();
    final sections = [
      ChartSeries('Approved', so.map((x) => toNum(x['approved'])).toList(), C.chGreen),
      ChartSeries('Rejected', so.map((x) => toNum(x['rejected'])).toList(), C.chRed),
      ChartSeries('Pending', so.map((x) => toNum(x['pending'])).toList(), C.chAmber),
      ChartSeries('Overdue', so.map((x) => toNum(x['overdue'])).toList(), C.chDark),
    ];
    final days = lastDays(14);
    final logs = db.all('audit_logs');
    final trend = [
      ChartSeries('Sent / re-sent', countByDay(logs, 'performed_at', days.$1, (a) => a['action'] == 'ROUND_INITIATED' || a['action'] == 'REINITIATED'), C.chNavy),
      ChartSeries('Section decisions', countByDay(logs, 'performed_at', days.$1, (a) => a['entity_type'] == 'TASK'), C.chGold),
      ChartSeries('Rounds closed', countByDay(logs, 'performed_at', days.$1, (a) => a['action'] == 'ROUND_CLOSED'), C.chGreen),
    ];
    final rounds = <num>[open, ready, notReady];
    return PageBody(children: [
      _welcome(ctx, 'Verification rounds and auction readiness.', [
        Btn('Send for verification', icon: FA.paperPlane, onPressed: () => P.go('/oic/properties', {'status': 'LISTED'})),
        Btn('Not ready properties', icon: FA.rotate, variant: 'light', onPressed: () => P.go('/oic/properties', {'status': 'NOT_READY'})),
      ]),
      TileGrid(children: [
        StatCard(color: 'blue', icon: FA.list, value: fmtNum(sc['LISTED'] ?? 0), label: 'Listed (to send)', onTap: () => P.go('/oic/properties', {'status': 'LISTED'})),
        StatCard(color: 'gold', icon: FA.hourglassHalf, value: fmtNum(sc['UNDER_VERIFICATION'] ?? 0), label: 'Under verification', sub: '${fmtNum(pending)} pending role tasks', onTap: () => P.go('/oic/properties', {'status': 'UNDER_VERIFICATION'})),
        StatCard(color: 'green', icon: FA.circleCheck, value: fmtNum(sc['AUCTION_READY'] ?? 0), label: 'Auction ready', onTap: () => P.go('/oic/properties', {'status': 'AUCTION_READY'})),
        StatCard(color: 'red', icon: FA.circleXmark, value: fmtNum(sc['NOT_READY'] ?? 0), label: 'Not ready', onTap: () => P.go('/oic/properties', {'status': 'NOT_READY'})),
        StatCard(color: 'navy', icon: FA.folderOpen, value: fmtNum(open), label: 'Open rounds', sub: '${fmtNum(ready)} ready / ${fmtNum(notReady)} not ready closed', onTap: () => P.go('/oic/properties', {'status': 'UNDER_VERIFICATION'})),
      ]),
      ChartGrid(columns: 3, children: [
        ChartCard(
          title: 'Property status',
          icon: FA.chartPie,
          sub: 'tap a slice',
          empty: DonutChart.isEmpty(st.$3),
          child: DonutChart(labels: st.$2, values: st.$3, colors: st.$4, onTap: (i) => P.go('/oic/properties', {'status': st.$1[i]})),
        ),
        ChartCard(
          title: 'Verification rounds',
          icon: FA.layerGroup,
          empty: DonutChart.isEmpty(rounds),
          child: DonutChart(labels: const ['Open', 'Closed — Ready', 'Closed — Not ready'], values: rounds, colors: const [C.chAmber, C.chGreen, C.chRed]),
        ),
        ChartCard(
          title: 'Readiness by scheme',
          icon: FA.gavel,
          empty: BarChart.isEmpty(readiness),
          child: BarChart(labels: sch.map((s) => str(s['scheme_code'])).toList(), series: readiness, stacked: true, onTap: (i) => P.go('/oic/properties', {'scheme_id': sch[i]['scheme_id']})),
        ),
      ]),
      ChartGrid(children: [
        ChartCard(
          title: 'Section responses (all rounds)',
          icon: FA.listCheck,
          empty: BarChart.isEmpty(sections),
          child: BarChart(labels: so.map((x) => str(x['name'])).toList(), series: sections, stacked: true, horizontal: true),
        ),
        ChartCard(title: 'Sent vs decided — last 14 days', icon: FA.chartLine, empty: LineChart.isEmpty(trend), child: LineChart(labels: days.$2, series: trend)),
      ]),
      ChartGrid(children: [
        StatusOverviewCard(counts: sc),
        ActivityCard(rows: recentActivity(8, ctx.uid), title: 'My recent activity'),
      ]),
    ]);
  }
}

/* ======================= ROLE USER ======================= */

class RoleDashboard extends StatelessWidget {
  final RouteCtx ctx;
  const RoleDashboard(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final me = ctx.uid;
    final t = db.today();
    final mineIds = db.where('verification_task_assignees', {'user_id': me}).map((a) => toInt(a['task_id'])).toSet();
    final all = db.filter('verification_tasks', (x) => mineIds.contains(toInt(x['task_id'])) && (x['task_status'] == 'PENDING' || toInt(x['responded_by']) == me)).map((x) {
      final vr = db.find('verification_rounds', x['round_id']) ?? <String, dynamic>{};
      final p = db.find('properties', vr['property_id']) ?? <String, dynamic>{};
      return <String, dynamic>{
        ...x,
        'view': x['task_status'] == 'PENDING' && vr['round_status'] != 'OPEN' ? 'NOT_REQUIRED' : x['task_status'],
        'round_no': vr['round_no'], 'initiated_at': vr['initiated_at'], 'property': p,
        'scheme': db.find('schemes', p['scheme_id']) ?? <String, dynamic>{}, 'sector': db.find('scheme_sectors', p['sector_id']) ?? <String, dynamic>{},
      };
    }).toList();
    final pending = all.where((x) => x['view'] == 'PENDING').toList();
    final overdue = pending.where((x) => str(x['sla_due_date']).compareTo(t) < 0).length;
    final approved = all.where((x) => x['task_status'] == 'APPROVED' && toInt(x['responded_by']) == me).length;
    final rejected = all.where((x) => x['task_status'] == 'REJECTED' && toInt(x['responded_by']) == me).length;
    final closed = all.where((x) => x['view'] == 'NOT_REQUIRED').length;
    final urgent = List<Rec>.of(pending)..sort((a, b) => str(a['sla_due_date']).compareTo(str(b['sla_due_date'])));
    const keys = ['PENDING', 'APPROVED', 'REJECTED', 'NOT_REQUIRED'];
    const tab = {'PENDING': 'pending', 'APPROVED': 'approved', 'REJECTED': 'rejected', 'NOT_REQUIRED': 'closed'};
    final mineCounts = keys.map((k) => all.where((x) => x['view'] == k).length).toList();
    final sch = db.all('schemes');
    final byScheme = [
      ChartSeries('Pending', sch.map((s) => pending.where((x) => toInt((x['scheme'] as Rec)['scheme_id']) == toInt(s['scheme_id'])).length).toList(), C.chAmber),
      ChartSeries('Responded', sch.map((s) => all.where((x) => toInt((x['scheme'] as Rec)['scheme_id']) == toInt(s['scheme_id']) && x['task_status'] != 'PENDING').length).toList(), C.chGreen),
    ];
    final buckets = <String, int>{'Overdue': 0, 'Due today': 0, '1–3 days': 0, '4+ days': 0};
    final today0 = parseDate(t)!;
    for (final x in pending) {
      final due = parseDate(x['sla_due_date']) ?? today0;
      final d = due.difference(today0).inHours / 24;
      final k = d < 0 ? 'Overdue' : (d.round() == 0 ? 'Due today' : (d <= 3 ? '1–3 days' : '4+ days'));
      buckets[k] = (buckets[k] ?? 0) + 1;
    }
    final sla = [ChartSeries('Tasks', buckets.values.toList(), C.chAmber, colors: const [C.chRed, C.chAmber, C.chGold, C.chGreen])];
    final days = lastDays(14);
    final trend = [
      ChartSeries('Received', countByDay(all, 'initiated_at', days.$1), C.chNavy),
      ChartSeries('Responded by me', countByDay(all, 'responded_at', days.$1, (x) => toInt(x['responded_by']) == me), C.chGreen),
    ];
    return PageBody(children: [
      _welcome(ctx, '${ctx.user['role_name'] ?? ''} · ${ctx.user['designation']} — properties sent to you for verification.', [
        Btn('Pending tasks', icon: FA.clipboardList, onPressed: () => P.go('/role/tasks', {'status': 'pending'})),
      ]),
      TileGrid(children: [
        StatCard(color: 'gold', icon: FA.hourglassHalf, value: fmtNum(pending.length), label: 'Pending', onTap: () => P.go('/role/tasks', {'status': 'pending'})),
        StatCard(color: 'red', icon: FA.stopwatch, shake: overdue > 0, value: fmtNum(overdue), label: 'Overdue (SLA)', onTap: () => P.go('/role/tasks', {'status': 'overdue'})),
        StatCard(color: 'green', icon: FA.circleCheck, value: fmtNum(approved), label: 'Approved by me', onTap: () => P.go('/role/tasks', {'status': 'approved'})),
        StatCard(color: 'navy', icon: FA.circleXmark, value: fmtNum(rejected), label: 'Rejected by me', onTap: () => P.go('/role/tasks', {'status': 'rejected'})),
        StatCard(color: 'blue', icon: FA.minus, value: fmtNum(closed), label: 'Not required', sub: 'round closed by another section', onTap: () => P.go('/role/tasks', {'status': 'closed'})),
      ]),
      ChartGrid(columns: 3, children: [
        ChartCard(
          title: 'My tasks by status',
          icon: FA.chartPie,
          sub: 'tap a slice',
          empty: DonutChart.isEmpty(mineCounts),
          child: DonutChart(
            labels: keys.map(label).toList(),
            values: mineCounts,
            colors: const [C.chAmber, C.chGreen, C.chRed, C.chSlate],
            onTap: (i) => P.go('/role/tasks', {'status': tab[keys[i]]}),
          ),
        ),
        ChartCard(
          title: 'My tasks by scheme',
          icon: FA.map,
          empty: BarChart.isEmpty(byScheme),
          child: BarChart(labels: sch.map((s) => str(s['scheme_code'])).toList(), series: byScheme, stacked: true, onTap: (i) => P.go('/role/tasks', {'scheme_id': sch[i]['scheme_id']})),
        ),
        ChartCard(title: 'Pending — days to SLA', icon: FA.stopwatch, empty: BarChart.isEmpty(sla), child: BarChart(labels: buckets.keys.toList(), series: sla)),
      ]),
      ChartCard(title: 'Received vs responded — last 14 days', icon: FA.chartLine, empty: LineChart.isEmpty(trend), child: LineChart(labels: days.$2, series: trend)),
      ChartGrid(children: [
        AppCard(
          title: 'Most urgent',
          icon: FA.fire,
          trailing: LinkText('View all', icon: FA.arrowRight, onTap: () => P.go('/role/tasks', {'status': 'pending'})),
          padding: EdgeInsets.zero,
          child: urgent.isEmpty
              ? const EmptyState(icon: FA.mugHot, title: 'No pending task', text: 'New properties sent by the OIC will appear here.', compact: true)
              : Column(children: [
                  for (final x in urgent.take(6)) _urgentRow(x, t),
                ]),
        ),
        ActivityCard(rows: recentActivity(8, me), title: 'My recent activity'),
      ]),
    ]);
  }

  Widget _urgentRow(Rec x, String today) {
    final p = x['property'] as Rec;
    final late = str(x['sla_due_date']).compareTo(today) < 0;
    return InkWell(
      onTap: () => P.go('/role/tasks/${x['task_id']}'),
      child: Container(
        padding: const EdgeInsets.fromLTRB(14, 11, 10, 11),
        decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFEEF2F7)))),
        child: Row(children: [
          Container(
            width: 32,
            height: 32,
            decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(9)),
            alignment: Alignment.center,
            child: const Icon(FA.building, size: 13, color: C.navy700),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Plot ${p['plot_no']} · ${propertyTypes()[str(p['property_type'])] ?? ''}', style: T.s(13.5, weight: FontWeight.w700, color: C.blue600)),
              Text('${(x['scheme'] as Rec)['scheme_name']} / ${(x['sector'] as Rec)['sector_name']} · R${x['round_no']}', style: T.s(12.5, color: C.muted)),
              Text('Due ${fmtDate(x['sla_due_date'])}${late ? ' — overdue' : ''}', style: T.s(11.5, color: late ? C.red600 : C.muted, weight: late ? FontWeight.w600 : FontWeight.w400)),
            ]),
          ),
          Btn('Verify', icon: FA.clipboardCheck, size: 'sm', onPressed: () => P.go('/role/tasks/${x['task_id']}')),
        ]),
      ),
    );
  }
}
