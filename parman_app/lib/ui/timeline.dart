// PRAMAN — Property Action Timeline (port of js/pages/timeline.js): every action on a property —
// registered, edited, sent, each section's approve / reject with remark + attachments, round closed …
import 'dart:convert';

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'attachments.dart';
import 'common.dart';

class _Kind {
  final String area; // data | verification | decision
  final IconData icon;
  final String tone; // info | navy | warning | success | danger
  const _Kind(this.area, this.icon, this.tone);
}

const Map<String, _Kind> _kinds = {
  'PROPERTY_CREATED': _Kind('data', FA.buildingCircleCheck, 'info'),
  'PROPERTY_UPDATED': _Kind('data', FA.penToSquare, 'info'),
  'ROUND_INITIATED': _Kind('verification', FA.paperPlane, 'navy'),
  'REINITIATED': _Kind('verification', FA.rotate, 'warning'),
  'TASK_APPROVED': _Kind('decision', FA.circleCheck, 'success'),
  'TASK_REJECTED': _Kind('decision', FA.circleXmark, 'danger'),
  'TASK_SIMULATED_APPROVED': _Kind('decision', FA.circleCheck, 'success'),
  'TASK_SIMULATED_REJECTED': _Kind('decision', FA.circleXmark, 'danger'),
  'ROUND_CLOSED': _Kind('verification', FA.flagCheckered, 'navy'),
};

String _kindKey(Rec a) => a['entity_type'] == 'PROPERTY' ? 'PROPERTY_${a['action']}' : str(a['action']);
_Kind _kindOf(Rec a) => _kinds[_kindKey(a)] ?? const _Kind('data', FA.circleDot, 'info');

Map<String, dynamic> _map(Object? v) => v is Map ? Map<String, dynamic>.from(v) : <String, dynamic>{};

String _val(String k, Object? v) {
  if (v == null || str(v).isEmpty) return 'Not set';
  final db = P.db;
  switch (k) {
    case 'sector_id':
      return str(db.find('scheme_sectors', v)?['sector_name'] ?? v);
    case 'ward_id':
      return db.wardName(v);
    case 'assembly_id':
      return db.assemblyName(v);
    case 'property_type':
      return str(db.first('master_plot_types', {'code': v})?['name'] ?? v);
    case 'status':
      return str(db.first('master_statuses', {'code': v})?['name'] ?? v);
    default:
      return str(v);
  }
}

class PropertyTimeline extends StatefulWidget {
  final int propertyId;
  final String? heading;
  final GlobalKey? anchor;
  const PropertyTimeline(this.propertyId, {super.key, this.heading, this.anchor});
  @override
  State<PropertyTimeline> createState() => _PropertyTimelineState();
}

class _PropertyTimelineState extends State<PropertyTimeline> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final rows = P.db.where('audit_logs', {'property_id': widget.propertyId});
    rows.sort((a, b) => (toInt(b['log_id']) ?? 0).compareTo(toInt(a['log_id']) ?? 0));
    final counts = <String, int>{'decision': 0, 'verification': 0, 'data': 0};
    for (final r in rows) {
      final k = _kindOf(r).area;
      counts[k] = (counts[k] ?? 0) + 1;
    }
    final shown = rows.where((r) => _filter == 'all' || _kindOf(r).area == _filter).toList();
    return Container(
      key: widget.anchor,
      child: AppCard(
        title: widget.heading ?? 'Property action timeline',
        icon: FA.timeline,
        trailing: Pill('${rows.length} events'),
        child: rows.isEmpty
            ? Padding(padding: const EdgeInsets.symmetric(vertical: 16), child: Text('No actions recorded on this property yet.', textAlign: TextAlign.center, style: T.small))
            : Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Wrap(spacing: 6, runSpacing: 6, children: [
                  _chip('all', 'All', null, rows.length),
                  if ((counts['decision'] ?? 0) > 0) _chip('decision', 'Decisions', FA.circleCheck, counts['decision']!),
                  if ((counts['verification'] ?? 0) > 0) _chip('verification', 'Rounds', FA.paperPlane, counts['verification']!),
                  if ((counts['data'] ?? 0) > 0) _chip('data', 'Edits', FA.penToSquare, counts['data']!),
                ]),
                const SizedBox(height: 16),
                Stack(children: [
                  Positioned(left: 17, top: 8, bottom: 8, child: Container(width: 2, color: C.border)),
                  Column(children: [for (final a in shown) _Entry(a)]),
                ]),
              ]),
      ),
    );
  }

  Widget _chip(String key, String text, IconData? icon, int n) {
    final on = _filter == key;
    return Material(
      color: on ? C.navy700 : Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: BorderSide(color: on ? C.navy700 : C.border)),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: () => setState(() => _filter = key),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            if (icon != null) Padding(padding: const EdgeInsets.only(right: 6), child: Icon(icon, size: 11, color: on ? Colors.white : C.slate600)),
            Text(text, style: T.s(12.5, weight: FontWeight.w600, color: on ? Colors.white : C.slate600)),
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
              decoration: BoxDecoration(color: on ? const Color(0x33FFFFFF) : C.slate100, borderRadius: BorderRadius.circular(999)),
              child: Text('$n', style: T.s(11, weight: FontWeight.w700, color: on ? Colors.white : C.slate600)),
            ),
          ]),
        ),
      ),
    );
  }
}

class _Entry extends StatelessWidget {
  final Rec a;
  const _Entry(this.a);

  Widget _statusChange(String? from, String to) => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 6, runSpacing: 4, children: [
          if (from != null) statusPill(from),
          if (from != null) const Icon(FA.arrowRightLong, size: 12, color: C.muted),
          statusPill(to),
        ]),
      );

  Widget _quote(String text, {String tone = ''}) {
    final Color c = tone == 'ok' ? C.green600 : (tone == 'bad' ? C.red600 : C.navy600);
    return Container(
      margin: const EdgeInsets.only(top: 6),
      padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
      decoration: BoxDecoration(color: c.withAlpha(16), border: Border(left: BorderSide(color: c, width: 3)), borderRadius: BorderRadius.circular(6)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(FA.quoteLeft, size: 10, color: c),
        const SizedBox(width: 8),
        Expanded(child: Text(text, style: T.s(13, color: C.text, height: 1.4))),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final n = _map(a['new_value']);
    final o = _map(a['old_value']);
    final k = _kindOf(a);
    final actor = P.db.find('users', a['performed_by']);
    final role = actor != null && actor['role_id'] != null ? P.db.find('roles', actor['role_id']) : null;
    var title = '';
    final body = <Widget>[];
    switch (_kindKey(a)) {
      case 'PROPERTY_CREATED':
        title = 'Property registered';
        body.add(Text(
          'Plot ${n['plot_no'] ?? ''}${n['property_type'] != null ? ' · ${_val('property_type', n['property_type'])}' : ''}'
          '${n['area_value'] != null ? ' · ${fmtNum(n['area_value'], 2)} ${n['area_unit'] == 'SQFT' ? 'sq ft' : 'sq m'}' : ''}',
          style: T.s(13),
        ));
        body.add(_statusChange(null, 'LISTED'));
        break;
      case 'PROPERTY_UPDATED':
        title = 'Property details updated';
        final keys = n.keys.where((x) => !x.startsWith('_') && jsonEncode(o[x]) != jsonEncode(n[x]) && x != 'scheme_id' && x != 'area_sqm').toList();
        if (keys.isEmpty) {
          body.add(Text('Details saved (no field changed).', style: T.s(13)));
        } else {
          for (final x in keys) {
            body.add(Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 6, runSpacing: 2, children: [
                Text(label(x), style: T.s(12.5, weight: FontWeight.w600, color: C.navy800)),
                Text(_val(x, o[x]), style: T.s(12.5, color: C.red600).copyWith(decoration: TextDecoration.lineThrough)),
                const Icon(FA.arrowRightLong, size: 11, color: C.muted),
                Text(_val(x, n[x]), style: T.s(12.5, weight: FontWeight.w600, color: C.green600)),
              ]),
            ));
          }
        }
        break;
      case 'ROUND_INITIATED':
      case 'REINITIATED':
        final roles = (n['roles'] as List? ?? const []).map((e) => e.toString()).toList();
        title = '${a['action'] == 'REINITIATED' ? 'Re-sent for verification' : 'Sent for verification'} — Round R${n['round_no']}';
        body.add(Text.rich(TextSpan(children: [
          TextSpan(text: 'Sent to ', style: T.s(13)),
          TextSpan(text: '${roles.length}', style: T.s(13, weight: FontWeight.w700)),
          TextSpan(text: ' section(s): ${roles.join(', ')}', style: T.s(13)),
        ])));
        if (n['reason'] != null) body.add(_quote(str(n['reason'])));
        body.add(_statusChange(str(o['status'] ?? 'LISTED'), 'UNDER_VERIFICATION'));
        break;
      case 'TASK_APPROVED':
      case 'TASK_REJECTED':
      case 'TASK_SIMULATED_APPROVED':
      case 'TASK_SIMULATED_REJECTED':
        final ok = str(a['action']).contains('APPROVED');
        title = '${n['role']} ${ok ? 'approved' : 'rejected'}${str(a['action']).contains('SIMULATED') ? ' (simulated)' : ''}${n['round_no'] != null ? ' · R${n['round_no']}' : ''}';
        if (n['remarks'] != null && str(n['remarks']).isNotEmpty) {
          body.add(_quote(str(n['remarks']), tone: ok ? 'ok' : 'bad'));
        } else {
          body.add(Text('No remarks provided.', style: T.small));
        }
        final names = (n['attachments'] as List? ?? const []).map((e) => e.toString()).toList();
        if (names.isNotEmpty) {
          final files = attachmentsFor({'task_id': a['entity_id']}).where((x) => names.contains(x['file_name'])).toList();
          body.add(Padding(
            padding: const EdgeInsets.only(top: 8, bottom: 4),
            child: Row(children: [const Icon(FA.paperclip, size: 11, color: C.muted), const SizedBox(width: 6), Text('Attached documents', style: T.s(12, weight: FontWeight.w600, color: C.muted))]),
          ));
          body.add(files.isNotEmpty ? AttachmentChips(files) : Text(names.join(', '), style: T.small));
        }
        break;
      case 'ROUND_CLOSED':
        title = 'Round closed — ${n['result'] == 'READY' ? 'Ready for Auction' : 'Not Ready'}';
        body.add(_statusChange('UNDER_VERIFICATION', n['result'] == 'READY' ? 'AUCTION_READY' : 'NOT_READY'));
        break;
      default:
        title = label(a['action']);
    }
    final tone = Tone.of(k.tone == 'navy' ? 'primary' : k.tone);
    final who = actor != null
        ? '${actor['name']}${actor['designation'] != null ? ' (${actor['designation']})' : (role != null ? ' (${role['role_name']})' : '')}'
        : 'System';
    return FadeSlideIn(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(color: tone.bg, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 3)),
            alignment: Alignment.center,
            child: Icon(k.icon, size: 13, color: tone.fg),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12), border: Border.all(color: C.border), boxShadow: kShadowSm),
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Text(title, style: T.s(13.5, weight: FontWeight.w700, color: C.navy800)),
                const SizedBox(height: 2),
                Text(fmtDateTime(a['performed_at']), style: T.tiny),
                const SizedBox(height: 6),
                Row(children: [
                  const Icon(FA.user, size: 10, color: C.muted),
                  const SizedBox(width: 6),
                  Expanded(child: Text(who, style: T.s(12.5, color: C.slate600))),
                ]),
                const SizedBox(height: 4),
                ...body,
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}
