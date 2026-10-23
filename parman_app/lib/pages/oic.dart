// PRAMAN — OIC panel (js/pages/oic.js): Properties – Verification (bulk / single send, re-send with reason,
// per-section verification bar) and the property status page with every round.
import 'package:flutter/material.dart';

import '../app/db.dart';
import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../app/validation.dart';
import '../ui/attachments.dart';
import '../ui/common.dart';
import '../ui/forms.dart';
import '../ui/lists.dart';
import '../ui/timeline.dart';
import 'properties.dart';

const int kMaxBulk = 100;

/// status key of a task inside the verification cell
const Map<String, (String, IconData, String, Color)> _vf = {
  'APPROVED': ('ok', FA.circleCheck, 'Approved', C.green600),
  'REJECTED': ('bad', FA.circleXmark, 'Rejected', C.red600),
  'NO_RESPONSE': ('late', FA.triangleExclamation, 'No response', C.amber600),
  'PENDING': ('wait', FA.hourglassHalf, 'Pending', Color(0xFFF0A85C)),
  'SKIPPED': ('skip', FA.minus, 'Not required (round closed)', C.slate400),
};

/// Send properties for verification (P.actions['oic-send']). Returns true when anything was sent.
void sendForVerification(List<int> ids, String? reason, {String? goTo}) {
  final ok = <String>[];
  final failed = <String>[];
  for (final id in ids) {
    final res = P.db.verificationSend(id, P.uid, (reason == null || reason.trim().isEmpty) ? null : reason.trim());
    (res.ok ? ok : failed).add(res.message);
  }
  if (ok.isNotEmpty) P.flash('success', ok.length == 1 ? ok.first : '${ok.length} properties sent for verification.');
  if (failed.isNotEmpty) P.flash('error', '${failed.length} not sent: ${failed.take(10).join(' | ')}');
  if (goTo != null) {
    P.go(goTo, null, true);
  } else {
    P.render();
  }
}

/* ======================= list ======================= */

class OicListPage extends StatefulWidget {
  final RouteCtx ctx;
  const OicListPage(this.ctx, {super.key});
  @override
  State<OicListPage> createState() => _OicListPageState();
}

class _OicListPageState extends State<OicListPage> {
  final Set<int> _sel = {};
  final FormModel _f = FormModel();

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _bulk(List<Rec> items) async {
    await submitForm(
      _f,
      checks: [
        () {
          final needs = items.any((r) => _sel.contains(toInt(r['property_id'])) && r['current_status'] == 'NOT_READY');
          if (needs && _f.trimmed('reason').length < 5) return ('reason', 'Enter a re-verification reason (min 5 characters) — Not Ready properties are selected.');
          if (_sel.isEmpty) return (null, 'Select at least one property.');
          if (Validator.hasAngle(_f.value('reason'))) return ('reason', 'Reason may be at most 255 characters and may not contain < or >.');
          return null;
        },
      ],
      confirm: 'Send the selected properties for verification? A new round will be created for each and all mapped role users will get a task.',
      okText: 'Yes, send',
      action: () {
        if (_sel.length > kMaxBulk) {
          _f.fail(null, 'You can send at most $kMaxBulk properties at once.');
          return;
        }
        sendForVerification(_sel.toList(), _f.trimmed('reason'));
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final ctx = widget.ctx;
    final q = ctx.query;
    final p = paginate<Rec>(filterProperties(propertyRows(), q), ctx, sort: 'created', dir: 'desc', filters: kPropertyFilterKeys, search: propertySearch, sortable: kPropertySortable);
    final sendable = p.items.where((r) => Db.sendable.contains(r['current_status'])).map((r) => toInt(r['property_id'])!).toList();
    final allOn = sendable.isNotEmpty && sendable.every(_sel.contains);
    final someOn = sendable.any(_sel.contains);
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        PageHeader(
          icon: FA.listCheck,
          title: 'Properties – Verification',
          subtitleWidget: Text.rich(TextSpan(style: T.s(13.5, color: C.muted, height: 1.45), children: const [
            TextSpan(text: 'Send '),
            TextSpan(text: 'Listed', style: TextStyle(fontWeight: FontWeight.w700)),
            TextSpan(text: ' properties for verification to all mapped role users. '),
            TextSpan(text: 'Not Ready', style: TextStyle(fontWeight: FontWeight.w700)),
            TextSpan(text: ' ones can be re-sent (new round) with a reason.'),
          ])),
        ),
        ListCard(
          toolbar: ListToolbar(p: p, placeholder: 'Search plot no., code, scheme, sector…', filters: propertyFilters(q), sorts: kPropertySorts),
          header: Container(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            decoration: const BoxDecoration(color: C.navy50, border: Border(bottom: BorderSide(color: C.border))),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                SizedBox(
                  width: 150,
                  child: CheckRow(
                    dense: true,
                    value: allOn,
                    tristate: someOn && !allOn,
                    enabled: sendable.isNotEmpty,
                    title: 'Select all',
                    onChanged: (v) => setState(() {
                      if (v) {
                        _sel.addAll(sendable);
                      } else {
                        _sel.removeAll(sendable);
                      }
                    }),
                  ),
                ),
                const Spacer(),
                const Icon(FA.squareCheck, size: 13, color: C.navy700),
                const SizedBox(width: 6),
                Text('${_sel.length} selected', style: T.s(13, weight: FontWeight.w700, color: C.navy800)),
              ]),
              const SizedBox(height: 6),
              FText(form: _f, name: 'reason', label: 'Re-verification reason', showLabel: false, placeholder: 'Re-verification reason (required for Not Ready properties)', maxLength: 255),
              Btn('Send selected for verification', icon: FA.paperPlane, block: true, loading: _f.submitting, loadingText: 'Sending…', onPressed: _sel.isEmpty ? null : () => _bulk(p.items)),
            ]),
          ),
          rows: [
            for (var i = 0; i < p.items.length; i++) _row(p.items[i], p.from + i),
          ],
          empty: listEmpty(p.hasFilters, 'No properties yet — Data Entry adds them'),
          pager: p,
        ),
      ]),
    );
  }

  Widget _row(Rec r, int sno) {
    final id = toInt(r['property_id'])!;
    final st = str(r['current_status']);
    final can = Db.sendable.contains(st);
    return RecordCard(
      tone: st == 'AUCTION_READY' ? 'success' : (st == 'NOT_READY' ? 'danger' : null),
      leading: Padding(
        padding: const EdgeInsets.only(top: 2),
        child: can
            ? GestureDetector(
                onTap: () => setState(() => _sel.contains(id) ? _sel.remove(id) : _sel.add(id)),
                child: _box(_sel.contains(id)),
              )
            : Opacity(opacity: .35, child: _box(false)),
      ),
      title: Text('Plot ${r['plot_no']}', style: T.s(15, weight: FontWeight.w700)),
      subtitle: Text('#$sno · ${r['scheme_name']} / ${r['sector_name']}', style: T.small),
      trailing: Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
        statusPill(st),
        if (r['round_no'] != null)
          Padding(
            padding: const EdgeInsets.only(top: 3),
            child: Text('R${r['round_no']}${st == 'UNDER_VERIFICATION' ? ' · ${r['tasks_approved']}/${r['tasks_total']} approved' : ''}', style: T.tiny),
          ),
      ]),
      fields: [
        ('Type', val(typeName(r['property_type']))),
        ('Area', areaCell(r)),
        (
          'Role users',
          Align(
            alignment: Alignment.centerLeft,
            child: Pill('${r['role_count']} roles', icon: FA.userTie, onTap: () => showRoleUsers(context, id)),
          ),
        ),
      ],
      actions: [
        _VerificationCell(r),
        const SizedBox(width: double.infinity, height: 2),
        IconBtn(FA.eye, tooltip: 'View status', onPressed: () => P.go('/oic/properties/$id')),
        IconBtn(FA.timeline, tooltip: 'Action timeline', onPressed: () => P.go('/oic/properties/$id', {'focus': 'timeline'})),
        if (st == 'LISTED')
          Btn('Send', icon: FA.paperPlane, size: 'sm', onPressed: () => confirmAction(
                confirm: 'Send plot ${r['plot_no']} (${r['scheme_name']} / ${r['sector_name']}) for verification to ${r['role_count']} role(s)?',
                okText: 'Yes, send',
                action: () => sendForVerification([id], null),
              )),
        if (st == 'NOT_READY') Btn('Re-send', icon: FA.rotate, variant: 'warning', size: 'sm', onPressed: () => P.go('/oic/properties/$id', {'focus': 'resend'})),
      ],
    );
  }

  Widget _box(bool on) => AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 22,
        height: 22,
        decoration: BoxDecoration(
          color: on ? C.navy700 : Colors.white,
          borderRadius: BorderRadius.circular(6),
          border: Border.all(color: on ? C.navy700 : C.borderStrong, width: 1.5),
        ),
        child: on ? const Icon(FA.check, size: 12, color: Colors.white) : null,
      );
}

/// Verification column: latest round — how many sections responded and what each one said.
class _VerificationCell extends StatelessWidget {
  final Rec r;
  const _VerificationCell(this.r);

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final rounds = db.where('verification_rounds', {'property_id': r['property_id']})..sort((a, b) => (toInt(b['round_no']) ?? 0).compareTo(toInt(a['round_no']) ?? 0));
    if (rounds.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: C.slate100, borderRadius: BorderRadius.circular(10)),
        child: Row(children: [
          const Icon(FA.hourglassStart, size: 12, color: C.slate600),
          const SizedBox(width: 8),
          Text('Not sent yet', style: T.s(12.5, weight: FontWeight.w600, color: C.slate600)),
          const Spacer(),
          Text('${r['role_count']} sections mapped', style: T.tiny),
        ]),
      );
    }
    final round = rounds.first;
    final open = round['round_status'] == 'OPEN';
    final tasks = db.where('verification_tasks', {'round_id': round['round_id']}).map((t) {
      final role = db.find('roles', t['role_id']) ?? <String, dynamic>{};
      final user = t['responded_by'] != null ? db.find('users', t['responded_by']) : null;
      final st = str(t['task_status']);
      final key = st == 'PENDING' && !open ? 'SKIPPED' : (_vf.containsKey(st) ? st : 'PENDING');
      return (t, role, user, key);
    }).toList()
      ..sort((a, b) => toNum(a.$2['sort_order']).compareTo(toNum(b.$2['sort_order'])));
    final total = tasks.length;
    final responded = tasks.where((x) => x.$4 == 'APPROVED' || x.$4 == 'REJECTED').length;
    final groups = <String, List<String>>{};
    for (final x in tasks) {
      groups.putIfAbsent(x.$4, () => []).add(str(x.$2['role_name']).replaceAll(RegExp(r'\s+Cell$', caseSensitive: false), ''));
    }
    return InkWell(
      onTap: () => _details(context, round, tasks),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: C.border)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(children: [
            Text.rich(TextSpan(children: [
              TextSpan(text: '$responded', style: T.s(15, weight: FontWeight.w800, color: C.navy800)),
              TextSpan(text: '/$total sections verified', style: T.s(12, color: C.muted)),
            ])),
            const Spacer(),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 1),
              decoration: BoxDecoration(color: C.navy100, borderRadius: BorderRadius.circular(6)),
              child: Text('R${round['round_no']}', style: T.s(11.5, weight: FontWeight.w700, color: C.navy700)),
            ),
            const SizedBox(width: 6),
            const Icon(FA.chevronRight, size: 10, color: C.muted),
          ]),
          const SizedBox(height: 8),
          Row(children: [
            for (var i = 0; i < tasks.length; i++) ...[
              if (i > 0) const SizedBox(width: 3),
              Expanded(child: Container(height: 7, decoration: BoxDecoration(color: _vf[tasks[i].$4]!.$4, borderRadius: BorderRadius.circular(3)))),
            ],
          ]),
          const SizedBox(height: 8),
          Wrap(spacing: 6, runSpacing: 4, children: [
            for (final k in _vf.keys)
              if ((groups[k] ?? const []).isNotEmpty)
                MiniTag(
                  groups[k]!.length == total ? 'All ${_vf[k]!.$3.toLowerCase()}' : (k == 'APPROVED' ? '${groups[k]!.length} approved' : groups[k]!.join(', ')),
                  icon: _vf[k]!.$2,
                  tone: k == 'APPROVED' ? 'ok' : (k == 'REJECTED' ? 'bad' : (k == 'SKIPPED' ? '' : 'warn')),
                ),
          ]),
        ]),
      ),
    );
  }

  void _details(BuildContext context, Rec round, List<(Rec, Rec, Rec?, String)> tasks) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
      builder: (c) => SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: MediaQuery.of(c).size.height * .8),
          child: ListView(shrinkWrap: true, padding: const EdgeInsets.fromLTRB(18, 16, 18, 18), children: [
            Text('Plot ${r['plot_no']} · Round R${round['round_no']}', style: T.s(17, weight: FontWeight.w700, color: C.navy800)),
            Text('${r['scheme_name']} / ${r['sector_name']}', style: T.small),
            const SizedBox(height: 12),
            for (final x in tasks)
              Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), border: Border.all(color: C.border)),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Icon(_vf[x.$4]!.$2, size: 14, color: _vf[x.$4]!.$4),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('${x.$2['role_name']} — ${_vf[x.$4]!.$3}', style: T.s(13.5, weight: FontWeight.w600)),
                      if (x.$3 != null) Text('by ${x.$3!['name']} (${x.$3!['designation']})', style: T.small),
                      if (x.$1['responded_at'] != null)
                        Text('on ${fmtDateTime(x.$1['responded_at'])}', style: T.tiny)
                      else if (x.$4 == 'PENDING')
                        Text('due ${fmtDate(x.$1['sla_due_date'])}', style: T.tiny),
                      if (x.$1['remarks'] != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text('“${x.$1['remarks']}”', style: T.s(12.5, color: C.slate600))),
                    ]),
                  ),
                ]),
              ),
          ]),
        ),
      ),
    );
  }
}

/// "N roles" → every mapped section (role) and its user(s) for that property.
void showRoleUsers(BuildContext context, int propertyId) {
  final db = P.db;
  final prop = db.find('properties', propertyId);
  if (prop == null) return;
  final sc = db.find('schemes', prop['scheme_id']) ?? <String, dynamic>{};
  final se = db.find('scheme_sectors', prop['sector_id']) ?? <String, dynamic>{};
  final groups = <(Rec, List<Rec>)>[];
  for (final x in db.propertyRoleUsers(propertyId)) {
    if (groups.isEmpty || groups.last.$1['role_id'] != x['role_id']) groups.add((x, <Rec>[]));
    groups.last.$2.add(x);
  }
  final userCount = groups.fold<int>(0, (n, g) => n + g.$2.length);
  showDialog<void>(
    context: context,
    builder: (c) => Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.all(16),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 8, 10),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(
              width: 38,
              height: 38,
              decoration: BoxDecoration(color: C.gold100, borderRadius: BorderRadius.circular(10)),
              alignment: Alignment.center,
              child: const Icon(FA.userTie, size: 15, color: C.gold600),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Assigned role users', style: T.s(16, weight: FontWeight.w700, color: C.navy800)),
                Text('Plot ${prop['plot_no']} · ${sc['scheme_name']} / ${se['sector_name']} · ${groups.length} roles · $userCount users', style: T.small),
              ]),
            ),
            IconButton(onPressed: () => Navigator.of(c).pop(), icon: const Icon(FA.xmark, size: 15, color: C.slate600)),
          ]),
        ),
        const Divider(height: 1, color: C.border),
        Flexible(
          child: groups.isEmpty
              ? Padding(padding: const EdgeInsets.all(24), child: Text('No role users assigned.', textAlign: TextAlign.center, style: T.small))
              : ListView(shrinkWrap: true, padding: const EdgeInsets.all(14), children: [
                  for (var i = 0; i < groups.length; i++)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Container(
                          width: 24,
                          height: 24,
                          decoration: const BoxDecoration(color: C.navy700, shape: BoxShape.circle),
                          alignment: Alignment.center,
                          child: Text('${i + 1}', style: T.s(11, weight: FontWeight.w700, color: Colors.white)),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Wrap(spacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
                              Text(str(groups[i].$1['role_name']), style: T.s(14, weight: FontWeight.w700)),
                              if (!truthy(groups[i].$1['role_active'])) const Pill('role inactive'),
                            ]),
                            const SizedBox(height: 6),
                            for (final u in groups[i].$2)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 6),
                                child: Row(children: [
                                  Avatar(str(u['name']), size: 30),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                      Text(str(u['name']), style: T.s(13.5, weight: FontWeight.w600)),
                                      Text(str(u['designation']), style: T.tiny),
                                    ]),
                                  ),
                                  if (!truthy(u['user_active'])) const Pill('inactive', tone: Tone.danger),
                                ]),
                              ),
                          ]),
                        ),
                      ]),
                    ),
                ]),
        ),
      ]),
    ),
  );
}

/* ======================= detail ======================= */

class OicDetailPage extends StatefulWidget {
  final RouteCtx ctx;
  const OicDetailPage(this.ctx, {super.key});
  @override
  State<OicDetailPage> createState() => _OicDetailPageState();
}

class _OicDetailPageState extends State<OicDetailPage> {
  final FormModel _f = FormModel();
  final GlobalKey _resendKey = GlobalKey();
  final GlobalKey _timelineKey = GlobalKey();
  final Set<int> _open = {};
  bool _first = true;

  @override
  void initState() {
    super.initState();
    final focus = widget.ctx.q('focus');
    if (focus.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final k = focus == 'resend' ? _resendKey : _timelineKey;
        final c = k.currentContext;
        if (c != null) Scrollable.ensureVisible(c, duration: const Duration(milliseconds: 400), alignment: .05);
      });
    }
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _resend(int id) async {
    await submitForm(_f,
        confirm: 'Start a new verification round? The old round stays in history.',
        okText: 'Yes, re-send',
        variant: 'warning',
        action: () => sendForVerification([id], _f.trimmed('reason'), goTo: '/oic/properties/$id'));
  }

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final rows = propertyRows().where((r) => toInt(r['property_id']) == widget.ctx.id).toList();
    if (rows.isEmpty) {
      return PageBody(children: [AppCard(child: EmptyState(icon: FA.mapLocationDot, title: 'Page not found', text: 'Property not found.'))]);
    }
    final prop = rows.first;
    final id = toInt(prop['property_id'])!;
    final roleUsers = db.propertyRoleUsers(id);
    final rounds = db.where('verification_rounds', {'property_id': id})..sort((a, b) => (toInt(b['round_no']) ?? 0).compareTo(toInt(a['round_no']) ?? 0));
    if (_first && rounds.isNotEmpty) {
      _open.add(toInt(rounds.first['round_id'])!);
      _first = false;
    }
    List<Rec> tasksOf(Rec r) {
      final l = db.where('verification_tasks', {'round_id': r['round_id']}).map((t) {
        final role = db.find('roles', t['role_id']) ?? <String, dynamic>{};
        final ru = t['responded_by'] != null ? db.find('users', t['responded_by']) : null;
        final assignees = db.where('verification_task_assignees', {'task_id': t['task_id']}).map((a) {
          final u = db.find('users', a['user_id']);
          return '${u?['name']} (${u?['designation']})';
        }).toList()
          ..sort();
        return <String, dynamic>{...t, 'role_name': role['role_name'], 'sort_order': role['sort_order'], 'is_mandatory': role['is_mandatory'], 'responded_by_name': ru?['name'], 'assignees': assignees.join(', ')};
      }).toList();
      l.sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
      return l;
    }

    final current = rounds.isEmpty ? null : rounds.first;
    final st = str(prop['current_status']);
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        PageHeader(
          icon: FA.clipboardCheck,
          title: 'Plot ${prop['plot_no']}',
          titleTrailing: statusPill(st),
          subtitle: '${prop['property_code']} · ${prop['scheme_name']} · ${prop['sector_name']}',
          actions: [
            Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () {
              if (!P.back()) P.go('/oic/properties');
            }),
            if (st == 'LISTED')
              Btn('Send for verification', icon: FA.paperPlane, onPressed: () => confirmAction(
                    confirm: 'Send this property for verification to all mapped role users?',
                    okText: 'Yes, send',
                    action: () => sendForVerification([id], null),
                  )),
          ],
        ),
        if (st == 'AUCTION_READY' && current != null)
          AlertBox(type: 'success', child: Text.rich(TextSpan(style: T.s(13.5, color: const Color(0xFF14532D)), children: [
            const TextSpan(text: 'Ready for Auction. ', style: TextStyle(fontWeight: FontWeight.w700)),
            TextSpan(text: 'All roles approved in round R${current['round_no']}. Valid till ${fmtDate(prop['verification_valid_till'])}.'),
          ]))),
        if (st == 'NOT_READY' && current != null)
          AlertBox(
            type: 'error',
            icon: FA.circleXmark,
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Not Ready — round R${current['round_no']} rejected. Reasons:', style: T.s(13.5, weight: FontWeight.w700, color: const Color(0xFF7F1D1D))),
              const SizedBox(height: 4),
              for (final t in tasksOf(current).where((t) => t['task_status'] == 'REJECTED' || t['task_status'] == 'NO_RESPONSE'))
                Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text('• ${t['role_name']}: ${t['remarks'] ?? 'No response'} (${t['responded_by_name'] ?? ''}, ${fmtDateTime(t['responded_at'])})', style: T.s(13, color: const Color(0xFF7F1D1D))),
                ),
            ]),
          ),
        propertyDetails(prop),
        AppCard(
          title: 'Assigned role users',
          icon: FA.userTie,
          sub: 'Snapshot taken when the property was created',
          padding: EdgeInsets.zero,
          child: roleUsers.isEmpty
              ? const EmptyState(title: 'No role users assigned', compact: true)
              : Column(children: [
                  for (final ru in roleUsers)
                    Container(
                      padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
                      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFEEF2F7)))),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(str(ru['role_name']), style: T.s(13.5, weight: FontWeight.w600)),
                            Text('${ru['name']} · ${ru['designation']}', style: T.small),
                          ]),
                        ),
                        if (!truthy(ru['role_active'])) const Pill('role inactive'),
                        if (!truthy(ru['user_active'])) const Pill('inactive', tone: Tone.danger),
                      ]),
                    ),
                ]),
        ),
        if (st == 'NOT_READY' && current != null)
          Container(
            key: _resendKey,
            child: AppCard(
              title: 'Send again for verification (new round R${(toInt(current['round_no']) ?? 0) + 1})',
              icon: FA.rotate,
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                FText(form: _f, name: 'reason', label: 'Reason for re-verification', rules: 'required|min:5|max:255|text', placeholder: 'e.g. Encroachment removed, record corrected', maxLength: 255),
                Align(alignment: Alignment.centerLeft, child: Btn('Re-send for verification', icon: FA.paperPlane, variant: 'warning', loading: _f.submitting, loadingText: 'Sending…', onPressed: () => _resend(id))),
              ]),
            ),
          ),
        if (rounds.isEmpty)
          const AppCard(child: EmptyState(icon: FA.hourglassStart, title: 'Not sent for verification yet', text: 'Use “Send for verification” to create round R1.')),
        for (final round in rounds) _roundCard(round, tasksOf(round)),
        PropertyTimeline(id, anchor: _timelineKey),
      ]),
    );
  }

  Widget _roundCard(Rec round, List<Rec> tasks) {
    final rid = toInt(round['round_id'])!;
    final isOpen = round['round_status'] == 'OPEN';
    final expanded = _open.contains(rid);
    final approved = tasks.where((t) => t['task_status'] == 'APPROVED').length;
    final initiator = P.db.find('users', round['initiated_by']);
    return AppCard(
      padding: EdgeInsets.zero,
      titleWidget: InkWell(
        onTap: () => setState(() => expanded ? _open.remove(rid) : _open.add(rid)),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Wrap(spacing: 6, runSpacing: 4, crossAxisAlignment: WrapCrossAlignment.center, children: [
            const Icon(FA.layerGroup, size: 14, color: C.gold500),
            Text('Round R${round['round_no']}', style: T.cardTitle),
            statusPill(round['round_status']),
            if (round['consolidated_result'] != null) statusPill(round['consolidated_result']),
          ]),
          const SizedBox(height: 4),
          Text(
            '$approved/${tasks.length} approved · Sent ${fmtDateTime(round['initiated_at'])} by ${initiator?['name'] ?? ''}'
            '${round['closed_at'] != null ? ' · Closed ${fmtDateTime(round['closed_at'])}' : ' · Due ${fmtDate(round['due_date'])}'}',
            style: T.tiny,
          ),
        ]),
      ),
      trailing: IconButton(
        onPressed: () => setState(() => expanded ? _open.remove(rid) : _open.add(rid)),
        icon: AnimatedRotation(turns: expanded ? .5 : 0, duration: const Duration(milliseconds: 200), child: const Icon(FA.chevronDown, size: 13, color: C.slate600)),
      ),
      footer: !expanded
          ? null
          : round['consolidated_result'] == 'NOT_READY' && round['consolidation_remarks'] != null
              ? Text('Result: ${round['consolidation_remarks']}', style: T.s(13))
              : round['consolidated_result'] == 'READY'
                  ? Text('Result: All roles approved · valid ${fmtDate(round['valid_from'])} – ${fmtDate(round['valid_till'])}', style: T.s(13))
                  : null,
      child: AnimatedSize(
        duration: const Duration(milliseconds: 220),
        alignment: Alignment.topCenter,
        child: !expanded
            ? const SizedBox(width: double.infinity)
            : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                if (round['reinitiate_reason'] != null)
                  Container(
                    margin: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(color: C.amber100, borderRadius: BorderRadius.circular(8)),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      const Icon(FA.quoteLeft, size: 10, color: C.amber600),
                      const SizedBox(width: 8),
                      Expanded(child: Text('Re-verification reason: ${round['reinitiate_reason']}', style: T.s(13, color: const Color(0xFF78350F)))),
                    ]),
                  ),
                for (final t in tasks)
                  RecordCard(
                    tone: t['task_status'] == 'APPROVED' ? 'success' : (t['task_status'] == 'REJECTED' ? 'danger' : null),
                    title: Wrap(spacing: 6, crossAxisAlignment: WrapCrossAlignment.center, children: [
                      Text(str(t['role_name']), style: T.s(14, weight: FontWeight.w700)),
                      if (!truthy(t['is_mandatory'])) const Pill('optional'),
                    ]),
                    trailing: t['task_status'] == 'PENDING' && !isOpen ? const Pill('Not required (round closed)', icon: FA.minus) : statusPill(t['task_status']),
                    fields: [
                      ('Assigned user(s)', val(t['assignees'])),
                      ('Remark', val(t['remarks'])),
                      (
                        'Date',
                        t['responded_at'] != null ? cellSub(fmtDateTime(t['responded_at']), t['responded_by_name']) : Text('SLA ${fmtDate(t['sla_due_date'])}', style: T.small),
                      ),
                      ('Attachments', attachmentsFor({'task_id': t['task_id']}).isEmpty ? val(null) : AttachmentChips(attachmentsFor({'task_id': t['task_id']}))),
                    ],
                  ),
                const SizedBox(height: 12),
              ]),
      ),
    );
  }
}
