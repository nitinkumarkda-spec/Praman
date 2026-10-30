// PRAMAN — Role User panel (js/pages/role.js): My Tasks + task detail with the decision form
// (Engineering Cell: site measurement + encroachment; Sale Cell: vacancy check; attachments).
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/lookups.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../app/validation.dart';
import '../ui/attachments.dart';
import '../ui/common.dart';
import '../ui/forms.dart';
import '../ui/lists.dart';
import '../ui/timeline.dart';
import 'properties.dart' show typeName;

const String kSiteRole = 'ENGINEERING_CELL';
const String kSaleRole = 'SALE_CELL';
const int kMaxWords = 100;
const Map<String, String> kAllotmentModes = {
  'AUCTION': 'Auction', 'LOTTERY': 'Lottery', 'DIRECT_SALE': 'Direct sale', 'LAND_FOR_LAND': 'Land for land', 'REHABILITATION': 'Rehabilitation', 'OTHER': 'Other',
};
const String kNotVacantMsg = 'A property that is not vacant cannot get “No Objection” — choose “Objection”.';

/// Tasks the user is assigned to (view_status NOT_REQUIRED = pending but round already closed;
/// a task answered by another assignee of the same section is hidden).
List<Rec> tasksFor(int userId) {
  final db = P.db;
  final mine = db.where('verification_task_assignees', {'user_id': userId}).map((a) => toInt(a['task_id'])).toSet();
  return db.filter('verification_tasks', (t) => mine.contains(toInt(t['task_id'])) && (t['task_status'] == 'PENDING' || toInt(t['responded_by']) == userId)).map(enrichTask).toList();
}

Rec enrichTask(Rec t) {
  final db = P.db;
  final vr = db.find('verification_rounds', t['round_id']) ?? <String, dynamic>{};
  final p = db.find('properties', vr['property_id']) ?? <String, dynamic>{};
  final sc = db.find('schemes', p['scheme_id']) ?? <String, dynamic>{};
  final se = db.find('scheme_sectors', p['sector_id']) ?? <String, dynamic>{};
  final r = db.find('roles', t['role_id']) ?? <String, dynamic>{};
  final ru = t['responded_by'] != null ? db.find('users', t['responded_by']) : null;
  final iu = db.find('users', vr['initiated_by']);
  return <String, dynamic>{
    ...t,
    'view_status': t['task_status'] == 'PENDING' && vr['round_status'] != 'OPEN' ? 'NOT_REQUIRED' : t['task_status'],
    'round_no': vr['round_no'], 'round_status': vr['round_status'], 'initiated_at': vr['initiated_at'], 'reinitiate_reason': vr['reinitiate_reason'],
    'property_id': p['property_id'], 'property_code': p['property_code'], 'plot_no': p['plot_no'], 'property_type': p['property_type'],
    'area_value': p['area_value'], 'area_unit': p['area_unit'], 'area_sqm': p['area_sqm'], 'property_remarks': p['remarks'], 'scheme_id': p['scheme_id'],
    'scheme_name': sc['scheme_name'], 'scheme_code': sc['scheme_code'], 'scheme_type': sc['scheme_type'],
    'sector_name': se['sector_name'],
    'role_name': r['role_name'], 'role_code': r['role_code'], 'responded_by_name': ru?['name'],
    'initiated_by_name': iu?['name'] ?? '', 'initiated_by_designation': iu?['designation'] ?? '',
  };
}

bool _pendingOpen(Rec t) => t['view_status'] == 'PENDING';

/* ======================= My Tasks ======================= */

const List<(String, String, IconData)> kTaskTabs = [
  ('', 'All', FA.list),
  ('pending', 'Pending', FA.hourglassHalf),
  ('overdue', 'Overdue', FA.stopwatch),
  ('approved', 'Approved', FA.check),
  ('rejected', 'Rejected', FA.xmark),
  ('closed', 'Not required', FA.minus),
];

class TaskListPage extends StatelessWidget {
  final RouteCtx ctx;
  const TaskListPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final today = P.db.today();
    final cur = ctx.q('status');
    var rows = tasksFor(ctx.uid);
    bool Function(Rec t)? f;
    switch (cur) {
      case 'pending':
        f = _pendingOpen;
        break;
      case 'overdue':
        f = (t) => _pendingOpen(t) && str(t['sla_due_date']).compareTo(today) < 0;
        break;
      case 'approved':
        f = (t) => t['task_status'] == 'APPROVED';
        break;
      case 'rejected':
        f = (t) => t['task_status'] == 'REJECTED';
        break;
      case 'closed':
        f = (t) => t['view_status'] == 'NOT_REQUIRED';
        break;
    }
    if (f != null) rows = rows.where(f).toList();
    if (ctx.q('scheme_id').isNotEmpty) rows = rows.where((t) => str(t['scheme_id']) == ctx.q('scheme_id')).toList();
    final p = paginate<Rec>(rows, ctx,
        sort: 'due',
        filters: const ['status', 'scheme_id'],
        search: (t) => [t['plot_no'], t['property_code'], t['scheme_name'], t['sector_name']],
        sortable: {
          'due': (t) => t['sla_due_date'],
          'sent': (t) => t['initiated_at'],
          'plot': (t) => t['plot_no'],
          'scheme': (t) => t['scheme_name'],
          'status': (t) => t['view_status'],
        });
    return PageBody(children: [
      const PageHeader(icon: FA.clipboardList, title: 'My Tasks', subtitle: 'Properties assigned to you for verification. Open a task to give No Objection or Objection.'),
      SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.only(bottom: 12),
        child: Row(children: [
          for (final t in kTaskTabs)
            Padding(
              padding: const EdgeInsets.only(right: 6),
              child: TabChip(
                text: t.$2,
                icon: t.$3,
                active: cur == t.$1,
                onTap: () => P.go('/role/tasks', {'status': t.$1, 'scheme_id': ctx.q('scheme_id')}, true),
              ),
            ),
        ]),
      ),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search plot no., property code, scheme, sector…',
          filters: [FilterDef('scheme_id', 'Scheme', schemeOptions(), 'All schemes')],
          sorts: const [('due', 'Due (SLA)'), ('sent', 'Received'), ('plot', 'Plot'), ('scheme', 'Scheme / Sector'), ('status', 'Status')],
          keep: {'status': cur},
        ),
        rows: [
          for (final t in p.items) _row(t, today),
        ],
        empty: listEmpty(p.hasFilters, 'No task assigned to you yet'),
        pager: p,
      ),
    ]);
  }

  Widget _row(Rec t, String today) {
    final pending = _pendingOpen(t);
    final late = pending && str(t['sla_due_date']).compareTo(today) < 0;
    return RecordCard(
      tone: t['view_status'] == 'APPROVED' ? 'success' : (t['view_status'] == 'REJECTED' ? 'danger' : null),
      onTap: () => P.go('/role/tasks/${t['task_id']}'),
      title: Text('Plot ${t['plot_no']}', style: T.s(15, weight: FontWeight.w700)),
      subtitle: Text(str(t['property_code']), style: T.mono.copyWith(fontSize: 12)),
      trailing: statusPill(t['view_status']),
      fields: [
        ('Scheme / Sector', cellSub(t['scheme_name'], t['sector_name'])),
        ('Plot type', val(typeName(t['property_type']))),
        ('Area', val('${fmtNum(t['area_sqm'], 2)} sq m')),
        ('Round', val('R${t['round_no']}')),
        ('Received', val(fmtDate(t['initiated_at']))),
        (
          'Due (SLA)',
          Wrap(spacing: 6, runSpacing: 3, crossAxisAlignment: WrapCrossAlignment.center, children: [
            Text(fmtDate(t['sla_due_date']), style: T.s(13.5, color: late ? C.red600 : C.text, weight: late ? FontWeight.w600 : FontWeight.w400)),
            if (late) const Pill('Overdue', tone: Tone.danger, icon: FA.stopwatch, size: 11),
          ]),
        ),
        if (!pending && t['responded_at'] != null) ('Responded', cellSub(t['responded_by_name'], fmtDate(t['responded_at']))),
      ],
      actions: [
        pending
            ? Btn('Verify', icon: FA.clipboardCheck, size: 'sm', onPressed: () => P.go('/role/tasks/${t['task_id']}'))
            : Btn('View', icon: FA.eye, variant: 'light', size: 'sm', onPressed: () => P.go('/role/tasks/${t['task_id']}')),
      ],
    );
  }
}

/// .tab pill
class TabChip extends StatelessWidget {
  final String text;
  final IconData? icon;
  final bool active;
  final VoidCallback onTap;
  final int? count;
  const TabChip({super.key, required this.text, this.icon, required this.active, required this.onTap, this.count});
  @override
  Widget build(BuildContext context) {
    return Material(
      color: active ? C.navy700 : Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: BorderSide(color: active ? C.navy700 : C.border)),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 8),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            if (icon != null) Padding(padding: const EdgeInsets.only(right: 6), child: Icon(icon, size: 12, color: active ? C.gold400 : C.slate600)),
            Text(text, style: T.s(13, weight: FontWeight.w600, color: active ? Colors.white : C.slate600)),
            if (count != null)
              Container(
                margin: const EdgeInsets.only(left: 6),
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                decoration: BoxDecoration(color: active ? const Color(0x33FFFFFF) : C.slate100, borderRadius: BorderRadius.circular(999)),
                child: Text('$count', style: T.s(11, weight: FontWeight.w700, color: active ? Colors.white : C.slate600)),
              ),
          ]),
        ),
      ),
    );
  }
}

/* ======================= Engineering / Sale helpers ======================= */

int wordCount(String s) => s.trim().split(RegExp(r'\s+')).where((w) => w.isNotEmpty).length;

double _inUnit(double v, String from, String to) => from == to ? v : (to == 'SQM' ? v * kSqftToSqm : v / kSqftToSqm);

/// Actual vs recorded area, both expressed in the recorded unit.
Map<String, Object> areaVariance(double value, String unit, double recValue, String recUnit) {
  final actual = (_inUnit(value, unit, recUnit) * 100).round() / 100;
  final diff = ((actual - recValue) * 100).round() / 100;
  return {
    'actual': actual,
    'diff': diff.abs(),
    'unit': recUnit,
    'pct': recValue != 0 ? (diff.abs() / recValue * 10000).round() / 100 : 0,
    'variance': diff.abs() < 0.01 ? 'SAME' : (diff < 0 ? 'LESS' : 'MORE'),
  };
}

Widget varianceView(Map<String, Object?> r, {bool inline = false}) {
  final v = str(r['variance']);
  final Color c = v == 'SAME' ? C.green600 : (v == 'LESS' ? C.amber600 : C.blue600);
  final text = v == 'SAME'
      ? 'Actual area matches the recorded area.'
      : 'Actual area is ${fmtNum(r['diff'], 2)} ${unitShort(r['unit'])} (${fmtNum(r['pct'], 2)}%) ${v == 'LESS' ? 'less' : 'more'} than recorded.';
  final IconData icon = v == 'SAME' ? FA.circleCheck : (v == 'LESS' ? FA.arrowTrendDown : FA.arrowTrendUp);
  return Container(
    margin: EdgeInsets.only(top: inline ? 4 : 0, bottom: inline ? 0 : 14),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(color: c.withAlpha(22), borderRadius: BorderRadius.circular(8), border: Border.all(color: c.withAlpha(70))),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, size: 13, color: c),
      const SizedBox(width: 8),
      Expanded(child: Text(text, style: T.s(13, weight: FontWeight.w600, color: c))),
    ]),
  );
}

/* ======================= Task detail + decision ======================= */

class TaskDetailPage extends StatefulWidget {
  final RouteCtx ctx;
  const TaskDetailPage(this.ctx, {super.key});
  @override
  State<TaskDetailPage> createState() => _TaskDetailPageState();
}

class _TaskDetailPageState extends State<TaskDetailPage> {
  final FormModel _f = FormModel();
  final List<PickedFile> _files = [];
  String _autoText = '';
  bool _inited = false;

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  String _allotmentRemark() {
    final mode = _f.value('allotment_mode');
    final date = _f.value('allotment_date');
    final name = _f.trimmed('allottee_name');
    return 'Not vacant — already allotted${mode.isNotEmpty ? ' by ${(kAllotmentModes[mode] ?? mode).toLowerCase()}' : ''}'
        '${date.isNotEmpty ? ' on ${fmtDate(date)}' : ''}${name.isNotEmpty ? ' to $name' : ''}.';
  }

  void _syncRemark(bool notVacant) {
    final cur = _f.trimmed('remarks');
    if (notVacant && (cur.isEmpty || cur == _autoText)) {
      _autoText = _allotmentRemark();
      _f.set('remarks', _autoText);
    } else if (!notVacant && cur.isNotEmpty && cur == _autoText) {
      _f.set('remarks', '');
      _autoText = '';
    }
  }

  void _onVacant(String v) {
    _f.set('decision', v == 'YES' ? 'APPROVED' : 'REJECTED');
    if (v != 'NO') {
      for (final k in const ['allotment_mode', 'allotment_date', 'allottee_name']) {
        _f.clearError(k);
      }
    }
    _syncRemark(v == 'NO');
  }

  Map<String, Object?>? _siteData(Rec task, int me) {
    final raw = _f.trimmed('site_area_value');
    final unit = _f.value('site_area_unit');
    final ok = RegExp(r'^\d{1,10}(\.\d{1,2})?$').hasMatch(raw) && (double.tryParse(raw) ?? 0) > 0;
    if (!ok) {
      _f.fail('site_area_value', 'Enter the actual measured area — a positive number with at most 2 decimals.');
      return null;
    }
    if (unit != 'SQM' && unit != 'SQFT') {
      _f.fail('site_area_unit', 'Select the unit of the measured area.');
      return null;
    }
    final enc = _f.value('encroachment');
    if (enc != 'YES' && enc != 'NO') {
      _f.fail('encroachment', 'Select Yes or No for encroachment.');
      return null;
    }
    final text = enc == 'YES' ? _f.trimmed('encroachment_details') : '';
    if (enc == 'YES') {
      if (text.isEmpty) {
        _f.fail('encroachment_details', 'Describe the encroachment (required when the answer is "Yes").');
        return null;
      }
      if (wordCount(text) > kMaxWords) {
        _f.fail('encroachment_details', 'Keep the description within $kMaxWords words.');
        return null;
      }
      if (Validator.hasAngle(text)) {
        _f.fail('encroachment_details', 'Description may not contain < or >.');
        return null;
      }
    }
    final p = P.db.find('properties', task['property_id'])!;
    final v = double.parse(raw);
    final r = areaVariance(v, unit, toNum(p['area_value']).toDouble(), str(p['area_unit']));
    return {
      'area_value': v, 'area_unit': unit, 'area_sqm': toSqm(v, unit),
      'recorded_value': p['area_value'], 'recorded_unit': p['area_unit'], 'difference': r['diff'], 'difference_pct': r['pct'], 'variance': r['variance'],
      'encroachment': enc, 'encroachment_details': text.isEmpty ? null : text, 'measured_by': me, 'measured_at': P.db.now(),
    };
  }

  Map<String, Object?>? _saleData(int me) {
    final vacant = _f.value('vacant');
    if (vacant != 'YES' && vacant != 'NO') {
      _f.fail('vacant', 'Select Yes or No for vacancy.');
      return null;
    }
    if (vacant == 'YES') return {'vacant': 'YES', 'checked_by': me, 'checked_at': P.db.now()};
    final mode = _f.value('allotment_mode');
    if (!kAllotmentModes.containsKey(mode)) {
      _f.fail('allotment_mode', 'Select the mode of allotment.');
      return null;
    }
    final date = _f.value('allotment_date');
    if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(date) || parseDate(date) == null) {
      _f.fail('allotment_date', 'Enter a valid date of allotment.');
      return null;
    }
    if (date.compareTo(P.db.today()) > 0) {
      _f.fail('allotment_date', 'Date of allotment cannot be in the future.');
      return null;
    }
    final name = _f.trimmed('allottee_name');
    if (name.length < 3 || name.length > 100 || !Validator.isName(name)) {
      _f.fail('allottee_name', 'Enter the name of the allottee (3–100 letters).');
      return null;
    }
    if (_f.value('decision') != 'REJECTED') {
      _f.fail(null, kNotVacantMsg);
      return null;
    }
    return {'vacant': 'NO', 'allotment_mode': mode, 'allotment_date': date, 'allottee_name': name, 'checked_by': me, 'checked_at': P.db.now()};
  }

  Future<void> _submit(Rec task, bool isSite, bool isSale) async {
    final exclude = <String>{
      if (!isSale || _f.value('vacant') != 'NO') ...['allotment_mode', 'allotment_date', 'allottee_name'],
    };
    await submitForm(
      _f,
      exclude: exclude,
      checks: [
        () {
          if (_f.value('decision') == 'REJECTED' && _f.trimmed('remarks').length < 5) return ('remarks', 'Objection reason is mandatory (at least 5 characters).');
          return null;
        },
        if (isSite)
          () {
            if (_f.value('encroachment') != 'YES') return null;
            final n = wordCount(_f.value('encroachment_details'));
            if (n == 0) return ('encroachment_details', 'Describe the encroachment (required when the answer is "Yes").');
            if (n > kMaxWords) return ('encroachment_details', 'Keep the description within $kMaxWords words (now $n).');
            return null;
          },
        if (isSale)
          () {
            if (_f.value('vacant') != 'NO') return null;
            final d = _f.value('allotment_date');
            if (d.isNotEmpty && d.compareTo(P.db.today()) > 0) return ('allotment_date', 'Date of allotment cannot be in the future.');
            if (_f.value('decision') == 'APPROVED') return ('decision', kNotVacantMsg);
            return null;
          },
      ],
      confirm: 'Submit your decision? It cannot be changed afterwards.',
      okText: 'Yes, submit',
      action: () async {
        final db = P.db;
        final me = P.uid;
        final t = db.find('verification_tasks', task['task_id']);
        if (t == null || !db.exists('verification_task_assignees', {'task_id': t['task_id'], 'user_id': me})) {
          _f.fail(null, 'Task not found or not assigned to you.');
          return;
        }
        if (Validator.hasAngle(_f.value('remarks'))) {
          _f.fail('remarks', 'Remark may be at most 1000 characters and may not contain < or >.');
          return;
        }
        final round = db.find('verification_rounds', t['round_id'])!;
        if (round['round_status'] != 'OPEN' || t['task_status'] != 'PENDING') {
          _f.fail(null, 'This task is no longer pending.');
          return;
        }
        final decision = _f.value('decision');
        if (decision == 'REJECTED' && _f.trimmed('remarks').isEmpty) {
          _f.fail('remarks', 'Objection reason (remark) is mandatory.');
          return;
        }
        Map<String, Object?>? site;
        Map<String, Object?>? sale;
        if (isSite) {
          site = _siteData(task, me);
          if (site == null) return;
        }
        if (isSale) {
          sale = _saleData(me);
          if (sale == null) return;
        }
        List<Rec> saved;
        try {
          saved = await saveAttachments(_files, taskId: toInt(t['task_id'])!, roundId: toInt(t['round_id'])!, propertyId: toInt(round['property_id'])!, by: me);
        } catch (e) {
          _f.fail(null, 'Could not store the attachments: $e');
          return;
        }
        final res = db.verificationRespond(toInt(t['task_id'])!, decision, _f.value('remarks'), me, attachments: saved.map((a) => str(a['file_name'])).toList());
        if (!res.ok) {
          removeAttachments(saved);
          _f.fail(null, res.message);
          return;
        }
        if (site != null) db.update('verification_tasks', t['task_id'], {'site_inspection': site});
        if (sale != null) db.update('verification_tasks', t['task_id'], {'sale_check': sale});
        P.flash('success', '${res.message}${saved.isNotEmpty ? ' ${saved.length} attachment(s) uploaded.' : ''}');
        P.go('/role/tasks', {'status': 'pending'}, true);
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final me = widget.ctx.uid;
    final raw = db.find('verification_tasks', widget.ctx.id);
    if (raw == null || !db.exists('verification_task_assignees', {'task_id': raw['task_id'], 'user_id': me})) {
      return PageBody(children: [AppCard(child: EmptyState(icon: FA.mapLocationDot, title: 'Page not found', text: 'Task not found or not assigned to you.', action: Btn('Back to my tasks', icon: FA.arrowLeft, onPressed: () => P.go('/role/tasks'))))]);
    }
    final task = enrichTask(raw);
    final assignees = db.where('verification_task_assignees', {'task_id': task['task_id']}).map((a) {
      final u = db.find('users', a['user_id']);
      return '${u?['name']} (${u?['designation']})';
    }).toList()
      ..sort();
    final canRespond = task['view_status'] == 'PENDING';
    final late = canRespond && str(task['sla_due_date']).compareTo(db.today()) < 0;
    final isSite = task['role_code'] == kSiteRole;
    final isSale = task['role_code'] == kSaleRole;
    if (!_inited) {
      _inited = true;
      if (isSite) _f.set('site_area_unit', str(task['area_unit']), silent: true);
    }
    final history = db.filter('verification_rounds', (r) => toInt(r['property_id']) == toInt(task['property_id']) && toInt(r['round_id']) != toInt(task['round_id']))
      ..sort((a, b) => (toInt(b['round_no']) ?? 0).compareTo(toInt(a['round_no']) ?? 0));
    final hist = <Rec>[];
    for (final r in history) {
      final t = db.first('verification_tasks', {'round_id': r['round_id'], 'role_id': task['role_id']});
      if (t == null) continue;
      final ru = t['responded_by'] != null ? db.find('users', t['responded_by']) : null;
      hist.add({'task_id': t['task_id'], 'round_no': r['round_no'], 'task_status': t['task_status'], 'remarks': t['remarks'], 'responded_by_name': ru?['name'], 'responded_at': t['responded_at'], 'consolidated_result': r['consolidated_result']});
    }
    final site = task['site_inspection'] is Map ? Map<String, Object?>.from(task['site_inspection'] as Map) : null;
    final sale = task['sale_check'] is Map ? Map<String, Object?>.from(task['sale_check'] as Map) : null;
    final files = attachmentsFor({'task_id': task['task_id']});

    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        PageHeader(
          icon: FA.clipboardCheck,
          title: 'Plot ${task['plot_no']}',
          titleTrailing: statusPill(task['view_status']),
          subtitle: '${task['property_code']} · ${task['scheme_name']} · ${task['sector_name']} · Round R${task['round_no']} · ${task['role_name']}',
          actions: [Btn('Back to my tasks', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('/role/tasks', {'status': 'pending'}, true))],
        ),
        if (task['view_status'] == 'NOT_REQUIRED')
          const AlertBox(type: 'info', text: 'This round was already closed (another section rejected the property), so your response is no longer required. If the OIC re-sends it, you will get a new task.')
        else if (late)
          AlertBox(type: 'warning', icon: FA.stopwatch, text: 'SLA due date ${fmtDate(task['sla_due_date'])} has passed. Please respond as soon as possible.'),
        AppCard(
          title: 'Property details',
          icon: FA.building,
          child: DetailList([
            ('Property code', val(task['property_code'], mono: true)),
            ('Scheme', val('${task['scheme_name']} (${task['scheme_code']})')),
            ('Scheme type', val(kSchemeTypes[task['scheme_type']])),
            ('Sector', val(task['sector_name'])),
            ('Plot no.', val(task['plot_no'], strong: true)),
            ('Plot type', val(typeName(task['property_type']))),
            ('Area', val('${fmtNum(task['area_value'], 2)} ${kAreaUnits[task['area_unit']] ?? ''}${task['area_unit'] == 'SQFT' ? ' (= ${fmtNum(task['area_sqm'], 2)} sq m)' : ''}')),
            if (task['property_remarks'] != null) ('Remarks', val(task['property_remarks'])),
          ]),
        ),
        AppCard(
          title: 'Verification task',
          icon: FA.layerGroup,
          child: DetailList([
            ('Section', val(task['role_name'])),
            ('Round', Wrap(spacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [Text('R${task['round_no']}', style: T.s(13.5)), statusPill(task['round_status'])])),
            ('Sent by', val('${task['initiated_by_name']} (${task['initiated_by_designation']})')),
            ('Received on', val(fmtDateTime(task['initiated_at']))),
            ('Due (SLA)', val(fmtDate(task['sla_due_date']), color: late ? C.red600 : null)),
            ('Assigned to', val('${assignees.isEmpty ? '—' : assignees.join(', ')} (any one may respond)')),
            if (task['reinitiate_reason'] != null) ('Re-verification reason', val(task['reinitiate_reason'])),
            if (!canRespond && task['responded_at'] != null) ...[
              ('Response', Align(alignment: Alignment.centerLeft, child: statusPill(task['task_status']))),
              ('Responded by', val('${task['responded_by_name'] ?? ''} · ${fmtDateTime(task['responded_at'])}')),
              if (site != null) ...[
                ('Actual area', Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  val('${fmtNum(site['area_value'], 2)} ${unitShort(site['area_unit'])}'),
                  varianceView({'variance': site['variance'], 'diff': site['difference'], 'pct': site['difference_pct'], 'unit': site['recorded_unit']}, inline: true),
                ])),
                ('Encroachment', site['encroachment'] == 'YES'
                    ? Column(crossAxisAlignment: CrossAxisAlignment.start, children: [const Pill('Yes', tone: Tone.danger), const SizedBox(height: 3), val(site['encroachment_details'])])
                    : const Align(alignment: Alignment.centerLeft, child: Pill('No', tone: Tone.success))),
              ],
              if (sale != null) ...[
                ('Vacant', Wrap(spacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
                  sale['vacant'] == 'YES' ? const Pill('Yes', tone: Tone.success) : const Pill('No', tone: Tone.danger),
                  Text(sale['vacant'] == 'YES' ? 'Ready for auction' : 'Already allotted', style: T.s(13)),
                ])),
                if (sale['vacant'] == 'NO') ...[
                  ('Mode of allotment', val(kAllotmentModes[sale['allotment_mode']] ?? sale['allotment_mode'])),
                  ('Date of allotment', val(fmtDate(sale['allotment_date']))),
                  ('Allottee', val(sale['allottee_name'])),
                ],
              ],
              ('Remark', val(task['remarks'])),
              ('Attachments', files.isEmpty ? val(null) : AttachmentChips(files)),
            ],
          ]),
        ),
        if (canRespond) _responseCard(task, isSite, isSale),
        AppCard(
          title: '${task['role_name']} — earlier rounds of this property',
          icon: FA.clockRotateLeft,
          padding: EdgeInsets.zero,
          child: hist.isEmpty
              ? const EmptyState(title: 'This is the first round for this property', compact: true)
              : Column(children: [
                  for (final h in hist)
                    RecordCard(
                      title: Text('Round R${h['round_no']}', style: T.s(14, weight: FontWeight.w700)),
                      trailing: statusPill(h['task_status']),
                      fields: [
                        ('Remark', Column(crossAxisAlignment: CrossAxisAlignment.start, children: [val(h['remarks']), AttachmentChips(attachmentsFor({'task_id': h['task_id']}))])),
                        ('By / Date', val('${h['responded_by_name'] ?? '—'} · ${fmtDate(h['responded_at'])}')),
                        ('Round result', h['consolidated_result'] != null ? Align(alignment: Alignment.centerLeft, child: statusPill(h['consolidated_result'])) : val(null)),
                      ],
                    ),
                  const SizedBox(height: 12),
                ]),
        ),
        PropertyTimeline(toInt(task['property_id'])!, heading: 'Property action timeline — every action on Plot ${task['plot_no']}'),
      ]),
    );
  }

  Widget _responseCard(Rec task, bool isSite, bool isSale) {
    final reject = _f.value('decision') == 'REJECTED';
    _f.register('decision', 'No Objection or Objection', 'required', choice: true);
    return AppCard(
      title: isSite ? 'Site inspection & decision' : (isSale ? 'Vacancy check & decision' : 'Your decision'),
      icon: isSite ? FA.rulerCombined : (isSale ? FA.houseCircleCheck : FA.gavel),
      sub: 'Once submitted, the response cannot be changed.',
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (isSite) ..._siteCheck(task),
        if (isSale) ..._saleCheck(),
        FieldLabel('Does the ${task['role_name']} clear this property for auction?', required: true),
        Container(
          key: _f.keyOf('decision'),
          child: Row(children: [
            Expanded(child: _decision('APPROVED', FA.circleCheck, 'No Objection', 'Record is clear — fit for auction', C.green600, C.green100)),
            const SizedBox(width: 10),
            Expanded(child: _decision('REJECTED', FA.circleXmark, 'Objection', 'Issue found — reason required', C.red600, C.red100)),
          ]),
        ),
        FieldError(_f.errors['decision']),
        const SizedBox(height: 14),
        FText(
          form: _f,
          name: 'remarks',
          label: 'Remark',
          rules: 'max:1000|text',
          requiredMark: reject,
          labelExtra: reject ? '(reason for objection)' : null,
          placeholder: reject ? 'Reason for objection (required) — e.g. encroachment, litigation, already allotted…' : 'Optional remark — e.g. Record clear, file no. 123',
          maxLines: 3,
          maxLength: 1000,
        ),
        Uploader(label: 'Supporting documents / photos', files: _files, onChanged: () => setState(() {})),
        Btn('Submit decision', icon: FA.paperPlane, size: 'lg', block: true, loading: _f.submitting, loadingText: 'Submitting…', onPressed: () => _submit(task, isSite, isSale)),
      ]),
    );
  }

  Widget _decision(String value, IconData icon, String title, String sub, Color c, Color bg) {
    final on = _f.value('decision') == value;
    final err = _f.errors['decision'] != null;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      transform: Matrix4.translationValues(0, on ? -2 : 0, 0),
      decoration: BoxDecoration(
        color: on ? bg : Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: on ? c : (err ? C.red600 : C.border), width: 2),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: () => _f.set('decision', value),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 10),
            child: Column(children: [
              AnimatedScale(scale: on ? 1.12 : 1, duration: const Duration(milliseconds: 200), child: Icon(icon, size: 26, color: on ? c : C.borderStrong)),
              const SizedBox(height: 6),
              Text(title, textAlign: TextAlign.center, style: T.s(15, weight: FontWeight.w700)),
              const SizedBox(height: 2),
              Text(sub, textAlign: TextAlign.center, style: T.s(11.5, color: C.muted)),
            ]),
          ),
        ),
      ),
    );
  }

  Widget _step(String n, String text) => Padding(
        padding: const EdgeInsets.only(top: 4, bottom: 10),
        child: Row(children: [StepNo(n), Text(text, style: T.s(14, weight: FontWeight.w700, color: C.navy800))]),
      );

  List<Widget> _siteCheck(Rec task) {
    final recValue = toNum(task['area_value']).toDouble();
    final recUnit = str(task['area_unit']);
    final raw = _f.trimmed('site_area_value');
    final v = double.tryParse(raw);
    final words = wordCount(_f.value('encroachment_details'));
    return [
      _step('1', 'Actual site measurement'),
      Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(8)),
        child: Row(children: [
          const Icon(FA.fileLines, size: 12, color: C.navy700),
          const SizedBox(width: 8),
          Expanded(
            child: Text.rich(TextSpan(children: [
              TextSpan(text: 'Recorded area (property register): ', style: T.s(13)),
              TextSpan(text: '${fmtNum(recValue, 2)} ${unitShort(recUnit)}', style: T.s(13, weight: FontWeight.w700)),
            ])),
          ),
        ]),
      ),
      FText(form: _f, name: 'site_area_value', label: 'Actual measured area', rules: 'required|decimal:2|positive', placeholder: 'e.g. 975.50', keyboard: const TextInputType.numberWithOptions(decimal: true), maxLength: 13),
      FSelect(form: _f, name: 'site_area_unit', label: 'Unit', options: const {'SQM': 'Square Meter (sq m)', 'SQFT': 'Square Feet (sq ft)'}, rules: 'required'),
      if (raw.isEmpty || v == null || v <= 0)
        Container(
          margin: const EdgeInsets.only(bottom: 14),
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: C.slate100, borderRadius: BorderRadius.circular(8)),
          child: Row(children: [
            const Icon(FA.circleInfo, size: 12, color: C.slate600),
            const SizedBox(width: 8),
            Expanded(child: Text('Enter the measured area to compare it with the recorded area.', style: T.s(12.5, color: C.slate600))),
          ]),
        )
      else
        varianceView(areaVariance(v, _f.value('site_area_unit').isEmpty ? recUnit : _f.value('site_area_unit'), recValue, recUnit)),
      _step('2', 'Encroachment'),
      FChoice(form: _f, name: 'encroachment', label: 'Is there any encroachment on the plot?', options: const [('YES', 'Yes'), ('NO', 'No')], rules: 'required', ruleLabel: 'Yes or No for encroachment'),
      if (_f.value('encroachment') == 'YES') ...[
        FText(
          form: _f,
          name: 'encroachment_details',
          label: 'Describe the encroachment',
          requiredMark: true,
          labelExtra: '(max $kMaxWords words)',
          placeholder: 'e.g. Temporary tin shed of about 8 sq m on the front side; boundary wall extended 2 ft into the road',
          maxLines: 3,
          maxLength: 1000,
        ),
        Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: Text('$words / $kMaxWords words', textAlign: TextAlign.right, style: T.s(12, weight: FontWeight.w600, color: words > kMaxWords ? C.red600 : C.muted)),
        ),
      ],
      _step('3', 'Decision'),
    ];
  }

  List<Widget> _saleCheck() {
    final vacant = _f.value('vacant');
    return [
      _step('1', 'Vacancy status'),
      FChoice(
        form: _f,
        name: 'vacant',
        label: 'Is the property vacant?',
        options: const [('YES', 'Yes, vacant'), ('NO', 'No, already allotted')],
        rules: 'required',
        ruleLabel: 'Yes or No for vacancy',
        onChanged: _onVacant,
      ),
      if (vacant == 'YES')
        const AlertBox(type: 'success', text: 'Ready for auction — the property is vacant. “No Objection” is selected below.'),
      if (vacant == 'NO') ...[
        const AlertBox(type: 'error', icon: FA.circleXmark, text: 'Not ready for auction — the property is already allotted. Enter the allotment details; “Objection” is selected below.'),
        FSelect(form: _f, name: 'allotment_mode', label: 'Mode of allotment', options: kAllotmentModes, rules: 'required', placeholder: 'Select mode', onChanged: (_) => _syncRemark(true)),
        FDate(form: _f, name: 'allotment_date', label: 'Date of allotment', rules: 'required', last: DateTime.now(), onChanged: (_) => _syncRemark(true)),
        FText(form: _f, name: 'allottee_name', label: 'Name of allottee', rules: 'required|min:3|max:100|name', placeholder: 'e.g. Ramesh Kumar Sharma', maxLength: 100, onSubmitted: (_) => _syncRemark(true)),
        Align(
          alignment: Alignment.centerRight,
          child: LinkText('Fill remark from allotment details', icon: FA.arrowDown, onTap: () {
            _autoText = '';
            _f.set('remarks', '');
            _syncRemark(true);
          }),
        ),
      ],
      _step('2', 'Decision'),
    ];
  }
}
