// PRAMAN — demo database (port of js/db.js of html/index.html).
// Same tables, same seed data (so ids match the web demo), same verification engine.
// Stored as one JSON document in SharedPreferences (the web app uses localStorage).
import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

import 'package:shared_preferences/shared_preferences.dart';

import 'format.dart';

/// Table registry: key => primary key (config/tables.php in the PHP app).
const Map<String, String> kTables = {
  'roles': 'role_id',
  'users': 'user_id',
  'schemes': 'scheme_id',
  'scheme_sectors': 'sector_id',
  'scheme_role_users': 'map_id',
  'properties': 'property_id',
  'property_role_users': 'pru_id',
  'verification_rounds': 'round_id',
  'verification_tasks': 'task_id',
  'verification_task_assignees': 'assignee_id',
  'audit_logs': 'log_id',
  'login_attempts': 'attempt_id',
  'notifications': 'notification_id',
  'master_plot_types': 'plot_type_id',
  'master_statuses': 'status_id',
  'attachments': 'attachment_id',
};

const int kDay = 1440;

class VerifyResult {
  final bool ok;
  final String message;
  final int? roundNo;
  final String? outcome;
  const VerifyResult(this.ok, this.message, {this.roundNo, this.outcome});
}

class _RoleUsers {
  final Rec role;
  final List<int> users = [];
  _RoleUsers(this.role);
}

class Db {
  static const String storeKey = 'praman_demo_db_v5';
  static const int validityDays = 180;
  static const List<String> sendable = ['LISTED', 'NOT_READY'];

  final Map<String, List<Rec>> _data = {};
  final Map<String, int> _seq = {};
  int _shift = 0; // minutes; moves the clock back while seeding
  Rec? actorOverride; // DB._actor
  Rec? Function()? currentUser;
  Rec? Function()? currentImpersonator;
  SharedPreferences? _prefs;
  bool _batch = false;
  Timer? _saveTimer;

  /// Bumps on every change — handy for widgets that cache derived data.
  int version = 0;

  void attach(SharedPreferences prefs, {Rec? Function()? user, Rec? Function()? impersonator}) {
    _prefs = prefs;
    currentUser = user;
    currentImpersonator = impersonator;
  }

  /* ---------- date helpers (local time, MySQL-like strings) ---------- */
  DateTime _base() => DateTime.now().add(Duration(minutes: _shift));
  String now([int offsetMinutes = 0]) => sqlDateTime(_base().add(Duration(minutes: offsetMinutes)));
  String today([int offsetDays = 0]) {
    final d = _base();
    return sqlDate(DateTime(d.year, d.month, d.day + offsetDays));
  }

  void _at(num minutesAgo) {
    _shift = -minutesAgo.round();
  }

  /* ---------- persistence ---------- */
  void _empty() {
    _data.clear();
    _seq.clear();
    for (final t in kTables.keys) {
      _data[t] = <Rec>[];
      _seq[t] = 0;
    }
  }

  void save() {
    version++;
    if (_batch) return;
    _saveTimer?.cancel();
    _saveTimer = Timer(const Duration(milliseconds: 150), flush);
  }

  /// Write to storage now (also called when the app goes to the background).
  void flush() {
    _saveTimer?.cancel();
    _saveTimer = null;
    final p = _prefs;
    if (p == null) return;
    final m = <String, dynamic>{'seq': _seq};
    _data.forEach((k, v) => m[k] = v);
    p.setString(storeKey, jsonEncode(m));
  }

  void load() {
    try {
      final raw = _prefs?.getString(storeKey);
      if (raw != null) {
        final parsed = jsonDecode(raw);
        if (parsed is Map && parsed['seq'] is Map && parsed['users'] is List) {
          _empty();
          (parsed['seq'] as Map).forEach((k, v) => _seq[k.toString()] = toInt(v) ?? 0);
          for (final t in kTables.keys) {
            final list = parsed[t];
            if (list is List) {
              _data[t] = list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
            }
          }
          version++;
          return;
        }
      }
    } catch (_) {
      // ignore and reseed
    }
    seed();
  }

  void reset() => seed();

  List<Rec> _t(String t) {
    final l = _data[t];
    if (l == null) throw ArgumentError('Unknown table key: $t');
    return l;
  }

  /// The live list of a table (for removing rows, like DB.raw()[table]).
  List<Rec> raw(String t) => _t(t);

  /* ---------- generic table helpers ---------- */
  static bool _eq(Rec r, Map<String, Object?> eq) {
    for (final e in eq.entries) {
      final v = e.value;
      final rv = r[e.key];
      if (v is List) {
        if (!v.contains(rv)) return false;
      } else if (rv != v) {
        return false;
      }
    }
    return true;
  }

  List<Rec> all(String t) => List<Rec>.of(_t(t));

  Rec? find(String t, Object? id) {
    final pk = kTables[t]!;
    final n = toInt(id);
    if (n == null) return null;
    for (final r in _t(t)) {
      if (toInt(r[pk]) == n) return r;
    }
    return null;
  }

  List<Rec> where(String t, [Map<String, Object?>? eq]) => eq == null ? all(t) : _t(t).where((r) => _eq(r, eq)).toList();
  List<Rec> filter(String t, bool Function(Rec r) test) => _t(t).where(test).toList();

  Rec? first(String t, Map<String, Object?> eq) {
    for (final r in _t(t)) {
      if (_eq(r, eq)) return r;
    }
    return null;
  }

  Rec? firstWhere(String t, bool Function(Rec r) test) {
    for (final r in _t(t)) {
      if (test(r)) return r;
    }
    return null;
  }

  int count(String t, [Map<String, Object?>? eq]) => eq == null ? _t(t).length : _t(t).where((r) => _eq(r, eq)).length;
  int countWhere(String t, bool Function(Rec r) test) => _t(t).where(test).length;
  bool exists(String t, Map<String, Object?> eq) => _t(t).any((r) => _eq(r, eq));
  bool any(String t, bool Function(Rec r) test) => _t(t).any(test);

  Rec insert(String t, Map<String, Object?> row) {
    final pk = kTables[t]!;
    final next = (_seq[t] ?? 0) + 1;
    _seq[t] = next;
    final rec = Map<String, dynamic>.of(row);
    rec[pk] = next;
    if (!rec.containsKey('created_at')) rec['created_at'] = now();
    _t(t).add(rec);
    save();
    return rec;
  }

  Rec? update(String t, Object? id, Map<String, Object?> patch) {
    final row = find(t, id);
    if (row == null) return null;
    row.addAll(patch);
    row['updated_at'] = now();
    save();
    return row;
  }

  void removeWhere(String t, bool Function(Rec r) test) {
    _t(t).removeWhere(test);
    save();
  }

  /// Case-insensitive uniqueness check (like Model::valueExists).
  bool valueExists(String t, String col, Object? value, [Object? exceptId]) {
    final pk = kTables[t]!;
    final v = str(value).trim().toLowerCase();
    final ex = toInt(exceptId);
    return _t(t).any((r) => str(r[col]).toLowerCase() == v && (ex == null || toInt(r[pk]) != ex));
  }

  static void sortByOrder(List<Rec> list) {
    list.sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
  }

  /* ---------- audit (insert-only) ---------- */
  void audit(String entityType, Object? entityId, String action, Map<String, Object?>? oldV, Map<String, Object?>? newV,
      [Object? propertyId, Rec? actor]) {
    final u = actor ?? actorOverride ?? currentUser?.call();
    final imp = currentImpersonator?.call();
    var name = u != null ? str(u['name']) : 'System';
    Map<String, Object?>? nv = newV;
    if (imp != null && u != null) {
      name = '${u['name']} [by Super Admin: ${imp['name']}]';
      if (name.length > 120) name = name.substring(0, 120);
      nv = <String, Object?>{...?newV, '_impersonated_by_admin_id': imp['user_id']};
    }
    Map<String, Object?>? scrub(Map<String, Object?>? o) {
      if (o == null) return null;
      final c = Map<String, Object?>.of(o);
      for (final k in const ['password', 'new_password', 'confirm_password', 'current_password', 'reset_password', 'reset_confirm']) {
        if (c.containsKey(k)) c[k] = '***';
      }
      return c;
    }

    insert('audit_logs', {
      'entity_type': entityType,
      'entity_id': entityId,
      'property_id': propertyId,
      'action': action,
      'old_value': scrub(oldV),
      'new_value': scrub(nv),
      'performed_by': u?['user_id'],
      'performed_by_name': name,
      'performed_by_designation': u?['designation'],
      'ip_address': '127.0.0.1',
      'user_agent': 'PRAMAN Android app',
      'performed_at': now(),
    });
  }

  /* ---------- notifications — one row per recipient ---------- */
  void notify(Object? userIds, {required String title, String type = 'SYSTEM', String message = '', String? link, String icon = 'fa-bell', String tone = 'info', String? ref}) {
    final ids = userIds is List ? userIds : [userIds];
    final seen = <int>{};
    for (final raw in ids) {
      final uid = toInt(raw);
      if (uid == null || uid == 0 || seen.contains(uid)) continue;
      seen.add(uid);
      insert('notifications', {
        'user_id': uid, 'type': type, 'title': title, 'message': message, 'link': link,
        'icon': icon, 'tone': tone, 'ref': ref, 'is_read': 0, 'read_at': null,
      });
    }
  }

  String zoneName(Object? id) => '—';

  String wardName(Object? id) => '—';

  String assemblyName(Object? id) => '—';

  List<int> panelUsers(String panel) =>
      where('users', {'panel': panel, 'is_active': 1}).map((u) => toInt(u['user_id'])!).toList();

  /// SLA reminders for a role user: one OVERDUE notification per overdue task (created lazily).
  void overdueReminders(int userId) {
    final t = today();
    for (final a in where('verification_task_assignees', {'user_id': userId})) {
      final task = find('verification_tasks', a['task_id']);
      if (task == null || task['task_status'] != 'PENDING' || str(task['sla_due_date']).compareTo(t) >= 0) continue;
      final r = find('verification_rounds', task['round_id']);
      if (r == null || r['round_status'] != 'OPEN' || exists('notifications', {'user_id': userId, 'ref': 'OVERDUE-${task['task_id']}'})) continue;
      final p = find('properties', r['property_id']);
      if (p == null) continue;
      notify(userId,
          type: 'TASK',
          tone: 'danger',
          icon: 'fa-stopwatch',
          ref: 'OVERDUE-${task['task_id']}',
          title: 'SLA overdue — Plot ${p['plot_no']}',
          message: 'Your verification response was due on ${str(task['sla_due_date']).split('-').reversed.join('-')}. Please respond as soon as possible.',
          link: '/role/tasks/${task['task_id']}');
    }
  }

  /* =================================================================
     Verification engine (mirrors App\Models\Verification)
     ================================================================= */
  VerifyResult verificationSend(int propertyId, int by, String? reason) {
    final p = find('properties', propertyId);
    if (p == null || !truthy(p['is_active'])) return VerifyResult(false, 'Property #$propertyId not found.');
    final sc = find('schemes', p['scheme_id']) ?? <String, dynamic>{};
    final se = find('scheme_sectors', p['sector_id']) ?? <String, dynamic>{};
    final lbl = 'Plot ${p['plot_no']} (${sc['scheme_name']} / ${se['sector_name']})';

    if (!sendable.contains(p['current_status'])) {
      return VerifyResult(false, '$lbl: status is ${label(p['current_status'])} — only Listed / Not Ready can be sent.');
    }
    if (exists('verification_rounds', {'property_id': p['property_id'], 'round_status': 'OPEN'})) {
      return VerifyResult(false, '$lbl: a verification round is already open.');
    }
    if (p['current_status'] == 'NOT_READY' && (reason == null || reason.trim().isEmpty)) {
      return VerifyResult(false, '$lbl: reason is required to re-send a Not Ready property.');
    }

    // active snapshot rows; role active, user active & still in that role
    final roles = <int, _RoleUsers>{};
    for (final pru in where('property_role_users', {'property_id': p['property_id'], 'is_active': 1})) {
      final r = find('roles', pru['role_id']);
      final u = find('users', pru['user_id']);
      if (r == null || !truthy(r['is_active']) || u == null || !truthy(u['is_active']) || u['panel'] != 'ROLE_USER' || toInt(u['role_id']) != toInt(r['role_id'])) {
        continue;
      }
      final g = roles.putIfAbsent(toInt(r['role_id'])!, () => _RoleUsers(r));
      final uid = toInt(u['user_id'])!;
      if (!g.users.contains(uid)) g.users.add(uid);
    }
    final missing = filter('roles', (r) => truthy(r['is_active']) && truthy(r['is_mandatory']) && !roles.containsKey(toInt(r['role_id'])));
    sortByOrder(missing);
    if (missing.isNotEmpty) {
      return VerifyResult(false, '$lbl: no active user assigned for mandatory role(s): ${missing.map((r) => r['role_name']).join(', ')}.');
    }
    final list = roles.values.toList()..sort((a, b) => toNum(a.role['sort_order']).compareTo(toNum(b.role['sort_order'])));
    if (list.isEmpty) return VerifyResult(false, '$lbl: no role users assigned.');

    final roundNo = where('verification_rounds', {'property_id': p['property_id']}).fold<int>(0, (m, r) => math.max(m, toInt(r['round_no']) ?? 0)) + 1;
    final maxSla = list.map((x) => toInt(x.role['sla_days']) ?? 0).reduce(math.max);
    var rs = (reason ?? '').trim();
    if (rs.length > 255) rs = rs.substring(0, 255);
    final round = insert('verification_rounds', {
      'property_id': p['property_id'], 'round_no': roundNo, 'round_status': 'OPEN', 'consolidated_result': null,
      'consolidation_remarks': null, 'initiated_by': by, 'initiated_at': now(), 'due_date': today(maxSla),
      'reinitiate_reason': roundNo > 1 ? rs : null,
      'closed_by': null, 'closed_at': null, 'valid_from': null, 'valid_till': null,
    });
    for (final x in list) {
      final task = insert('verification_tasks', {
        'round_id': round['round_id'], 'role_id': x.role['role_id'], 'task_status': 'PENDING',
        'sla_due_date': today(toInt(x.role['sla_days']) ?? 0), 'remarks': null, 'responded_by': null, 'responded_at': null, 'is_simulated': 0,
      });
      for (final uid in x.users) {
        insert('verification_task_assignees', {'task_id': task['task_id'], 'user_id': uid});
      }
    }
    final prevStatus = p['current_status']; // p is the live row — read before the update
    update('properties', p['property_id'], {'current_status': 'UNDER_VERIFICATION', 'current_round_id': round['round_id'], 'updated_by': by});
    for (final t in where('verification_tasks', {'round_id': round['round_id']})) {
      final role = find('roles', t['role_id'])!;
      final due = str(t['sla_due_date']).split('-').reversed.join('-');
      notify(where('verification_task_assignees', {'task_id': t['task_id']}).map((a) => a['user_id']).toList(),
          type: 'TASK',
          tone: roundNo > 1 ? 'warning' : 'info',
          icon: 'fa-clipboard-list',
          title: '${roundNo > 1 ? 'Re-verification task' : 'New verification task'} — Plot ${p['plot_no']}',
          message: '${sc['scheme_name']} / ${se['sector_name']} · Round R$roundNo · ${role['role_name']} · due $due${roundNo > 1 && reason != null && reason.isNotEmpty ? ' · Reason: $reason' : ''}',
          link: '/role/tasks/${t['task_id']}');
    }
    notify(toInt(p['created_by']) == by ? <int>[] : [p['created_by']],
        type: 'VERIFICATION',
        icon: 'fa-paper-plane',
        title: 'Plot ${p['plot_no']} sent for verification',
        message: 'Round R$roundNo sent to ${list.length} section(s) by OIC.',
        link: '/data-entry/properties');
    audit('ROUND', round['round_id'], roundNo > 1 ? 'REINITIATED' : 'ROUND_INITIATED', {'status': prevStatus},
        {'round_no': roundNo, 'roles': list.map((x) => x.role['role_name']).toList(), 'reason': (reason == null || reason.isEmpty) ? null : reason}, p['property_id']);
    return VerifyResult(true, '$lbl: sent as Round R$roundNo to ${list.length} role(s).', roundNo: roundNo);
  }

  VerifyResult verificationRespond(int taskId, String decision, String? remarksIn, int by, {bool simulated = false, List<String>? attachments}) {
    if (decision != 'APPROVED' && decision != 'REJECTED') return const VerifyResult(false, 'Invalid decision.');
    final String? remarks = remarksIn?.trim();
    if (decision == 'REJECTED' && (remarks == null || remarks.isEmpty)) {
      return const VerifyResult(false, 'Rejection reason (remark) is mandatory.');
    }
    final t = find('verification_tasks', taskId);
    if (t == null) return const VerifyResult(false, 'Task not found.');
    final round = find('verification_rounds', t['round_id'])!;
    final role = find('roles', t['role_id'])!;
    if (round['round_status'] != 'OPEN') return const VerifyResult(false, 'This round is already closed.');
    if (t['task_status'] != 'PENDING') return VerifyResult(false, '${role['role_name']} has already responded (${label(t['task_status'])}).');

    update('verification_tasks', t['task_id'], {
      'task_status': decision, 'remarks': (remarks == null || remarks.isEmpty) ? null : remarks, 'responded_by': by, 'responded_at': now(), 'is_simulated': simulated ? 1 : 0,
    });
    final outcome = consolidate(toInt(round['round_id'])!, toInt(round['property_id'])!, by);
    audit('TASK', t['task_id'], '${simulated ? 'TASK_SIMULATED_' : 'TASK_'}$decision', null, {
      'role': role['role_name'], 'round_no': round['round_no'], 'remarks': remarks,
      if (attachments != null && attachments.isNotEmpty) 'attachments': attachments,
    }, round['property_id']);
    if (outcome != null) audit('ROUND', round['round_id'], 'ROUND_CLOSED', null, {'result': outcome}, round['property_id']);

    final prop = find('properties', round['property_id'])!;
    if (decision == 'REJECTED') {
      notify(panelUsers('OIC'),
          type: 'VERIFICATION', tone: 'danger', icon: 'fa-circle-xmark', title: '${role['role_name']} rejected Plot ${prop['plot_no']}',
          message: 'Round R${round['round_no']}: "$remarks". Property is NOT READY — re-send after correction.', link: '/oic/properties/${prop['property_id']}');
      notify(prop['created_by'],
          type: 'VERIFICATION', tone: 'danger', icon: 'fa-circle-xmark', title: 'Plot ${prop['plot_no']} is Not Ready',
          message: '${role['role_name']}: $remarks', link: '/data-entry/properties');
      for (final o in where('verification_tasks', {'round_id': round['round_id'], 'task_status': 'PENDING'})) {
        notify(where('verification_task_assignees', {'task_id': o['task_id']}).map((a) => a['user_id']).toList(),
            type: 'TASK', icon: 'fa-minus', title: 'Response not required — Plot ${prop['plot_no']}',
            message: 'Round R${round['round_no']} was closed because ${role['role_name']} rejected it.', link: '/role/tasks/${o['task_id']}');
      }
    } else if (outcome == 'READY') {
      notify([...panelUsers('OIC'), prop['created_by']],
          type: 'VERIFICATION', tone: 'success', icon: 'fa-gavel', title: 'Plot ${prop['plot_no']} is AUCTION READY',
          message: 'All sections approved round R${round['round_no']}. Valid for $validityDays days.', link: '/oic/properties/${prop['property_id']}');
    }
    var msg = '${role['role_name']}: ${label(decision)}.';
    if (outcome == 'READY') msg += ' All roles approved — property is now AUCTION READY.';
    if (outcome == 'NOT_READY') msg += ' Round closed as NOT READY — property is back with OIC for re-verification.';
    return VerifyResult(true, msg, outcome: outcome);
  }

  String? consolidate(int roundId, int propertyId, int by) {
    final tasks = where('verification_tasks', {'round_id': roundId}).map((t) {
      final c = Map<String, dynamic>.of(t);
      c['role'] = find('roles', t['role_id']);
      return c;
    }).toList()
      ..sort((a, b) => toNum((a['role'] as Rec)['sort_order']).compareTo(toNum((b['role'] as Rec)['sort_order'])));
    final rejected = tasks.where((t) => t['task_status'] == 'REJECTED' || t['task_status'] == 'NO_RESPONSE').toList();
    final pending = tasks.where((t) => t['task_status'] == 'PENDING').toList();
    if (rejected.isNotEmpty) {
      update('verification_rounds', roundId, {
        'round_status': 'CLOSED', 'consolidated_result': 'NOT_READY',
        'consolidation_remarks': rejected.map((t) => '• ${(t['role'] as Rec)['role_name']}: ${t['remarks'] ?? 'No response'}').join('\n'),
        'closed_by': by, 'closed_at': now(),
      });
      update('properties', propertyId, {'current_status': 'NOT_READY', 'updated_by': by});
      return 'NOT_READY';
    }
    if (pending.isEmpty) {
      final till = today(validityDays);
      update('verification_rounds', roundId, {
        'round_status': 'CLOSED', 'consolidated_result': 'READY', 'consolidation_remarks': 'All roles approved',
        'closed_by': by, 'closed_at': now(), 'valid_from': today(), 'valid_till': till,
      });
      update('properties', propertyId, {
        'current_status': 'AUCTION_READY', 'last_verified_round_id': roundId, 'verification_valid_till': till, 'updated_by': by,
      });
      return 'READY';
    }
    return null;
  }

  /* =================================================================
     Property / scheme helpers (mirror App\Models\Property, Scheme)
     ================================================================= */
  List<String> missingMandatoryRoles(int schemeId) {
    final roles = filter('roles', (r) => truthy(r['is_active']) && truthy(r['is_mandatory']));
    sortByOrder(roles);
    return roles.where((r) {
      return !where('scheme_role_users', {'scheme_id': schemeId, 'role_id': r['role_id'], 'is_active': 1}).any((m) {
        final u = find('users', m['user_id']);
        return u != null && truthy(u['is_active']) && u['panel'] == 'ROLE_USER' && toInt(u['role_id']) == toInt(r['role_id']);
      });
    }).map((r) => str(r['role_name'])).toList();
  }

  /// Returns the new property row and how many role users were snapshotted.
  (Rec, int) propertyCreate(Map<String, Object?> d, int by) {
    final p = insert('properties', {
      'scheme_id': d['scheme_id'], 'sector_id': d['sector_id'],
      'plot_no': d['plot_no'], 'property_type': d['property_type'],
      'area_value': d['area_value'], 'area_unit': d['area_unit'], 'area_sqm': d['area_sqm'], 'remarks': d['remarks'],
      'current_status': 'LISTED', 'current_round_id': null, 'last_verified_round_id': null, 'verification_valid_till': null,
      'is_active': 1, 'created_by': by, 'property_code': null,
    });
    p['property_code'] = 'PRM-${str(p['property_id']).padLeft(6, '0')}';
    var snap = 0;
    for (final m in where('scheme_role_users', {'scheme_id': d['scheme_id'], 'is_active': 1})) {
      final r = find('roles', m['role_id']);
      final u = find('users', m['user_id']);
      if (r != null && truthy(r['is_active']) && u != null && truthy(u['is_active']) && u['panel'] == 'ROLE_USER' && toInt(u['role_id']) == toInt(m['role_id'])) {
        insert('property_role_users', {'property_id': p['property_id'], 'role_id': m['role_id'], 'user_id': m['user_id'], 'is_active': 1, 'created_by': by});
        snap++;
      }
    }
    final scName = str(find('schemes', d['scheme_id'])?['scheme_name']);
    notify(panelUsers('OIC'),
        type: 'PROPERTY', icon: 'fa-building-circle-check', title: 'New property listed — Plot ${p['plot_no']}',
        message: '$scName · ${p['property_code']} is ready to be sent for verification.', link: '/oic/properties/${p['property_id']}');
    save();
    return (p, snap);
  }

  /// Snapshot of the role users of a property, sorted by role order then name.
  List<Rec> propertyRoleUsers(Object? propertyId) {
    final out = <Rec>[];
    for (final x in where('property_role_users', {'property_id': toInt(propertyId), 'is_active': 1})) {
      final r = find('roles', x['role_id']);
      final u = find('users', x['user_id']);
      if (r == null || u == null) continue;
      out.add({
        'role_id': r['role_id'], 'role_name': r['role_name'], 'sort_order': r['sort_order'], 'is_mandatory': r['is_mandatory'],
        'role_active': r['is_active'], 'user_id': u['user_id'], 'name': u['name'], 'designation': u['designation'], 'user_active': u['is_active'],
      });
    }
    out.sort((a, b) {
      final c = toNum(a['sort_order']).compareTo(toNum(b['sort_order']));
      return c != 0 ? c : str(a['name']).compareTo(str(b['name']));
    });
    return out;
  }

  List<Rec> schemeSectors(Object? schemeId, {bool activeOnly = false}) {
    final sid = toInt(schemeId);
    final l = filter('scheme_sectors', (s) => toInt(s['scheme_id']) == sid && (!activeOnly || s['is_active'] == 1));
    sortByOrder(l);
    return l;
  }

  /// Active schemes having at least one active sector (eligible for property entry).
  List<Rec> schemeEligible() {
    final l = where('schemes', {'is_active': 1}).where((s) => schemeSectors(s['scheme_id'], activeOnly: true).isNotEmpty).toList();
    l.sort((a, b) => str(a['scheme_name']).compareTo(str(b['scheme_name'])));
    return l;
  }

  /// role_id => user_ids currently mapped
  Map<int, List<int>> schemeMapping(Object? schemeId) {
    final out = <int, List<int>>{};
    for (final m in where('scheme_role_users', {'scheme_id': toInt(schemeId), 'is_active': 1})) {
      out.putIfAbsent(toInt(m['role_id'])!, () => <int>[]).add(toInt(m['user_id'])!);
    }
    return out;
  }

  /// Active roles with their active role users — the mapping form options.
  List<(Rec, List<Rec>)> roleUserOptions() {
    final roles = where('roles', {'is_active': 1});
    sortByOrder(roles);
    return roles.map((r) {
      final users = where('users', {'is_active': 1, 'panel': 'ROLE_USER', 'role_id': r['role_id']});
      users.sort((a, b) => str(a['name']).compareTo(str(b['name'])));
      return (r, users);
    }).toList();
  }

  /* =================================================================
     SEED — same roles & users as praman_db.sql, plus demo schemes,
     properties and verification rounds so every panel has data.
     ================================================================= */
  void seed() {
    _batch = true;
    final prevActor = actorOverride;
    try {
      _seedAll();
    } finally {
      _shift = 0;
      actorOverride = prevActor;
      _batch = false;
    }
    save();
    flush();
  }

  void _withActor(Rec? u, void Function() fn) {
    final prev = actorOverride;
    actorOverride = u;
    try {
      fn();
    } finally {
      actorOverride = prev;
    }
  }

  void _seedAll() {
    _empty();
    _at(30 * kDay);
    const pw = 'Praman@123';
    const roles = [
      ['AUCTION_CELL', 'Auction Cell', 'नीलामी शाखा', 'Allotment by auction — allottee / cancellation record', 5],
      ['LOTTERY_CELL', 'Lottery Cell', 'लॉटरी शाखा', 'Allotment by lottery — allottee / cancellation record', 5],
      ['SALE_CELL', 'Sale Cell', 'विक्रय शाखा', 'Sale register — sold / reserved / vacant on record', 5],
      ['LAND_FOR_LAND_CELL', 'Land-for-Land Cell', 'भूमि के बदले भूमि शाखा', 'Land given against acquired land', 5],
      ['PUNARVAS_CELL', 'Punarvas Cell', 'पुनर्वास शाखा', 'Rehabilitation allotments', 5],
      ['ENGINEERING_CELL', 'Engineering Cell', 'अभियांत्रिकी शाखा', 'Site inspection — vacancy, encroachment, measurements', 10],
      ['TOWN_PLANNING_CELL', 'Town Planning Cell', 'नगर नियोजन शाखा', 'Master plan / layout / road widening check', 7],
      ['LEGAL_CELL', 'Legal Cell', 'विधि शाखा', 'Litigation / stay order check', 7],
    ];
    for (var i = 0; i < roles.length; i++) {
      final r = roles[i];
      insert('roles', {
        'role_code': r[0], 'role_name': r[1], 'role_name_hi': r[2], 'description': r[3], 'sla_days': r[4],
        'is_mandatory': 1, 'sort_order': i + 1, 'is_active': 1, 'created_by': 1,
      });
    }
    const users = [
      ['superadmin', 'KDA-IT-001', 'System Administrator', 'Programmer (IT Cell)', '9800000001', 'SUPER_ADMIN', null],
      ['dataentry', 'KDA-DE-001', 'Ramesh Kumar Meena', 'Data Entry Operator', '9800000002', 'DATA_ENTRY', null],
      ['oic', 'KDA-OIC-001', 'Suresh Chand Sharma', 'OIC — Sale / Auction', '9800000003', 'OIC', null],
      ['auction1', 'KDA-AUC-001', 'Anil Kumar Jain', 'Assistant, Auction Cell', '9800000011', 'ROLE_USER', 1],
      ['auction2', 'KDA-AUC-002', 'Pooja Saini', 'Section Incharge, Auction', '9800000012', 'ROLE_USER', 1],
      ['lottery1', 'KDA-LOT-001', 'Mahesh Gurjar', 'Assistant, Lottery Cell', '9800000021', 'ROLE_USER', 2],
      ['lottery2', 'KDA-LOT-002', 'Kavita Rathore', 'Section Incharge, Lottery', '9800000022', 'ROLE_USER', 2],
      ['sale1', 'KDA-SAL-001', 'Deepak Verma', 'Clerk, Sale Cell', '9800000031', 'ROLE_USER', 3],
      ['sale2', 'KDA-SAL-002', 'Neha Agarwal', 'Section Incharge, Sale', '9800000032', 'ROLE_USER', 3],
      ['lfl1', 'KDA-LFL-001', 'Bhanwar Lal Bairwa', 'Assistant, Land-for-Land', '9800000041', 'ROLE_USER', 4],
      ['lfl2', 'KDA-LFL-002', 'Sunita Mehra', 'Section Incharge, LFL', '9800000042', 'ROLE_USER', 4],
      ['punarvas1', 'KDA-PUN-001', 'Rajendra Prasad', 'Assistant, Punarvas Cell', '9800000051', 'ROLE_USER', 5],
      ['punarvas2', 'KDA-PUN-002', 'Asha Kanwar', 'Section Incharge, Punarvas', '9800000052', 'ROLE_USER', 5],
      ['eng1', 'KDA-ENG-001', 'Vikas Choudhary', 'Junior Engineer', '9800000061', 'ROLE_USER', 6],
      ['eng2', 'KDA-ENG-002', 'Manoj Kumar Yadav', 'Assistant Engineer', '9800000062', 'ROLE_USER', 6],
      ['tp1', 'KDA-TP-001', 'Shweta Joshi', 'Assistant Town Planner', '9800000071', 'ROLE_USER', 7],
      ['tp2', 'KDA-TP-002', 'Gaurav Singhal', 'Deputy Town Planner', '9800000072', 'ROLE_USER', 7],
      ['legal1', 'KDA-LEG-001', 'Advocate Ritu Sharma', 'Legal Assistant', '9800000081', 'ROLE_USER', 8],
      ['legal2', 'KDA-LEG-002', 'Hemant Gupta', 'Law Officer', '9800000082', 'ROLE_USER', 8],
      ['eng3', 'KDA-ENG-003', 'Rohit Meena', 'Assistant Engineer (Civil)', '9800000063', 'ROLE_USER', 6],
      ['eng4', 'KDA-ENG-004', 'Sanjay Gurjar', 'Junior Engineer (Survey)', '9800000064', 'ROLE_USER', 6],
      ['legal3', 'KDA-LEG-003', 'Neelam Shekhawat', 'Legal Assistant', '9800000083', 'ROLE_USER', 8],
    ];
    for (final u in users) {
      insert('users', {
        'username': u[0], 'emp_code': u[1], 'name': u[2], 'designation': u[3], 'mobile': u[4], 'email': '${u[0]}@kda.local',
        'password': pw, 'panel': u[5], 'role_id': u[6], 'is_active': 1, 'must_change_password': 0, 'failed_attempts': 0,
        'locked_until': null, 'last_login_at': null, 'last_login_ip': null, 'password_changed_at': null,
        'created_by': 1,
      });
    }
    const panelNames = {'SUPER_ADMIN': 'Super Admin', 'DATA_ENTRY': 'Data Entry', 'OIC': 'OIC', 'ROLE_USER': 'Role User'};
    for (final u in all('users')) {
      notify(u['user_id'],
          type: 'ACCOUNT', tone: 'success', icon: 'fa-hand-sparkles', title: 'Welcome to PRAMAN',
          message: 'Your ${panelNames[u['panel']]} account is ready.', link: '/change-password');
    }
    audit('SYSTEM', null, 'DATABASE_SEEDED', null, {'roles': 8, 'users': 19}, null, find('users', 1));

    // ---- masters ----
    const plotTypes = [
      ['PLOT', 'Plot', 'भूखंड', 'Open residential / commercial plot'], ['SHOP', 'Shop', 'दुकान', 'Built shop'], ['KIOSK', 'Kiosk', 'कियोस्क', 'Small kiosk / booth'],
      ['FARMHOUSE', 'Farmhouse', 'फार्महाउस', 'Farmhouse land'], ['FLAT', 'Flat', 'फ्लैट', 'Flat in a building'], ['OTHER', 'Other', 'अन्य', 'Any other property'],
    ];
    for (var i = 0; i < plotTypes.length; i++) {
      final t = plotTypes[i];
      insert('master_plot_types', {'code': t[0], 'name': t[1], 'name_hi': t[2], 'description': t[3], 'sort_order': i + 1, 'is_active': 1, 'is_system': 0, 'created_by': 1});
    }
    const statuses = [
      ['LISTED', 'Listed', 'info', 'fa-list', 'Registered, not yet sent for verification'],
      ['UNDER_VERIFICATION', 'Under Verification', 'warning', 'fa-hourglass-half', 'Round open — sections are verifying'],
      ['AUCTION_READY', 'Auction Ready', 'success', 'fa-circle-check', 'All sections approved'],
      ['NOT_READY', 'Not Ready', 'danger', 'fa-circle-xmark', 'A section rejected — needs correction and re-send'],
      ['SENT_FOR_AUCTION', 'Sent for Auction', 'primary', 'fa-gavel', 'Handed over to the auction cell'],
      ['SOLD', 'Sold', 'dark', 'fa-handshake', 'Sold in auction'], ['WITHHELD', 'Withheld', 'muted', 'fa-pause', 'Kept on hold by order'],
      ['ARCHIVED', 'Archived', 'muted', 'fa-box-archive', 'No longer active'],
    ];
    for (var i = 0; i < statuses.length; i++) {
      final st = statuses[i];
      insert('master_statuses', {'code': st[0], 'name': st[1], 'name_hi': null, 'color': st[2], 'icon': st[3], 'description': st[4], 'sort_order': i + 1, 'is_active': 1, 'is_system': 1, 'created_by': 1});
    }

    final de = find('users', 2);
    final oic = find('users', 3);
    void loginAt(int uid, num minutesAgo, num logoutAfter) {
      final u = find('users', uid)!;
      _at(minutesAgo);
      insert('login_attempts', {'username': u['username'], 'ip_address': '127.0.0.1', 'success': 1, 'attempted_at': now()});
      audit('AUTH', uid, 'LOGIN', null, {'message': 'Login successful'}, null, u);
      u['last_login_at'] = now();
      u['last_login_ip'] = '127.0.0.1';
      if (logoutAfter > 0) {
        _at(minutesAgo - logoutAfter);
        audit('AUTH', uid, 'LOGOUT', null, {'message': 'User logged out'}, null, u);
      }
    }

    // ---- demo schemes (created by Data Entry, 20-16 days ago) ----
    const schemes = [
      ['Ravindra Nagar Awasiya Yojana', 'RNY-2019', 'RESIDENTIAL', 2019, ['Sector-1', 'Sector-2', 'Sector-3', 'Sector-4']],
      ['Vigyan Nagar Commercial Complex', 'VNC-2021', 'COMMERCIAL', 2021, ['Block-A', 'Block-B']],
      ['Kunhadi Industrial Area', 'KIA-2016', 'INDUSTRIAL', 2016, ['Phase-I', 'Phase-II', 'Phase-III']],
    ];
    for (var i = 0; i < schemes.length; i++) {
      final s = schemes[i];
      loginAt(2, (20 - i * 2) * kDay + 30, 0);
      _at((20 - i * 2) * kDay);
      final sectors = (s[4] as List).cast<String>();
      final sc = insert('schemes', {
        'scheme_name': s[0], 'scheme_code': s[1], 'scheme_type': s[2],
        'year_of_scheme': s[3], 'is_active': 1, 'created_by': 2,
      });
      for (var j = 0; j < sectors.length; j++) {
        insert('scheme_sectors', {'scheme_id': sc['scheme_id'], 'sector_name': sectors[j], 'sort_order': j + 1, 'is_active': 1, 'created_by': 2});
      }
      // map role users: user 1 of each role (both for Engineering)
      for (final u in where('users', {'panel': 'ROLE_USER'})) {
        final un = str(u['username']);
        if (un.endsWith('1') || u['role_id'] == 6) {
          insert('scheme_role_users', {'scheme_id': sc['scheme_id'], 'role_id': u['role_id'], 'user_id': u['user_id'], 'is_active': 1, 'created_by': 2});
        }
      }
      audit('SCHEME', sc['scheme_id'], 'CREATED', null, {
        'scheme_name': sc['scheme_name'], 'scheme_code': sc['scheme_code'], 'scheme_type': s[2], 'year_of_scheme': s[3], 'sectors': sectors.join(', '),
      }, null, de);
    }
    // a later edit, so the timeline shows a before -> after change table
    _at(15 * kDay);
    audit('SCHEME', 1, 'UPDATED', {'year_of_scheme': 2018}, {'year_of_scheme': 2019}, null, de);

    // ---- demo properties (10-6 days ago) ----
    const props = [
      [1, 1, '101', 'PLOT', 150, 'SQM'], [1, 1, '102', 'PLOT', 180, 'SQM'], [1, 2, '205-A', 'PLOT', 2400, 'SQFT'],
      [1, 3, '310', 'FLAT', 95.5, 'SQM'], [2, 5, 'SH-12', 'SHOP', 320, 'SQFT'], [2, 6, 'K-04', 'KIOSK', 12, 'SQM'],
      [3, 7, 'IND-21', 'PLOT', 1000, 'SQM'], [3, 8, 'IND-45', 'PLOT', 1500, 'SQM'], [1, 4, '412', 'PLOT', 200, 'SQM'],
      [2, 5, 'SH-15', 'SHOP', 280, 'SQFT'],
    ];
    loginAt(2, 10 * kDay + 20, 0);
    for (var i = 0; i < props.length; i++) {
      final x = props[i];
      _at(10 * kDay - i * 3 * 60);
      final scheme = x[0] as int;
      final area = x[4] as num;
      final sqm = x[5] == 'SQFT' ? (area * 0.092903 * 10000).round() / 10000 : area;
      final (p, _) = propertyCreate({
        'scheme_id': scheme, 'sector_id': x[1],
        'plot_no': x[2], 'property_type': x[3], 'area_value': area, 'area_unit': x[5], 'area_sqm': sqm, 'remarks': null,
      }, 2);
      audit('PROPERTY', p['property_id'], 'CREATED', null, {
        'property_code': p['property_code'], 'plot_no': p['plot_no'], 'property_type': p['property_type'], 'area_value': p['area_value'], 'area_unit': p['area_unit'], 'status': 'LISTED',
      }, p['property_id'], de);
    }
    _at(5 * kDay);
    audit('PROPERTY', 2, 'UPDATED', {'area_value': 175, 'remarks': null}, {'area_value': 180, 'remarks': 'Area corrected as per site map'}, 2, de);
    update('properties', 2, {'remarks': 'Area corrected as per site map'});

    // ---- verification: OIC sends 8 days ago, sections respond over the following days ----
    var clock = 8 * kDay;
    void respondAll(int propertyId, [Set<int> except = const <int>{}]) {
      final p = find('properties', propertyId)!;
      for (final t in where('verification_tasks', {'round_id': p['current_round_id']})) {
        if (except.contains(toInt(t['role_id']))) continue;
        final uid = toInt(where('verification_task_assignees', {'task_id': t['task_id']}).first['user_id'])!;
        clock -= 97;
        _at(clock);
        _withActor(find('users', uid), () {
          verificationRespond(toInt(t['task_id'])!, 'APPROVED', 'Record clear - no objection.', uid);
        });
      }
    }

    loginAt(3, 8 * kDay + 25, 0);
    _at(8 * kDay);
    _withActor(oic, () {
      for (final id in const [3, 4, 5, 6, 7, 8]) {
        verificationSend(id, 3, null);
      }
    });
    // P3: 5 of 8 approved, Engineering + TP + Legal pending
    respondAll(3, {6, 7, 8});
    // P5, P6: all approved -> AUCTION_READY
    respondAll(5);
    respondAll(6);
    // P7: Engineering rejects -> NOT_READY
    respondAll(7, {6, 8});
    _at(2 * kDay + 180);
    final p7 = find('properties', 7)!;
    final engTask = first('verification_tasks', {'round_id': p7['current_round_id'], 'role_id': 6})!;
    _withActor(find('users', 14), () {
      verificationRespond(toInt(engTask['task_id'])!, 'REJECTED', 'Encroachment - temporary tin shed approx. 8 sq m on the front side.', 14,
          attachments: ['site_inspection_photo.jpg', 'inspection_report.pdf']);
    });
    _sampleAttachment(engTask, 14, 'site_inspection_photo.jpg', 'image',
        ['SITE INSPECTION — Plot IND-21', 'Kunhadi Industrial Area / Phase-I', 'Encroachment: tin shed ~8 sq m (front side)', 'Inspected by: Vikas Choudhary, JE']);
    _sampleAttachment(engTask, 14, 'inspection_report.pdf', 'pdf', [
      'KDA Engineering Cell - Site Inspection Report', 'Plot IND-21, Kunhadi Industrial Area, Phase-I', 'Finding: temporary tin shed approx. 8 sq m on front side.',
      'Recommendation: remove encroachment before auction.', 'Junior Engineer - Vikas Choudhary',
    ]);
    // P8: Legal rejects -> NOT_READY, then OIC re-sends (round R2 open)
    _at(3 * kDay);
    final p8 = find('properties', 8)!;
    final legalTask = first('verification_tasks', {'round_id': p8['current_round_id'], 'role_id': 8})!;
    _withActor(find('users', 18), () {
      verificationRespond(toInt(legalTask['task_id'])!, 'REJECTED', 'Stay order in civil suit no. 45/2023 - pending.', 18, attachments: ['stay_order_cs_45_2023.pdf']);
    });
    _sampleAttachment(legalTask, 18, 'stay_order_cs_45_2023.pdf', 'pdf', [
      'Civil Court, Kota - Civil Suit No. 45/2023', 'Interim stay order in respect of Plot IND-45,', 'Kunhadi Industrial Area, Phase-II.',
      'Status as on date: stay operative.', 'Legal Cell, KDA',
    ]);
    loginAt(3, kDay + 15, 0);
    _at(kDay);
    _withActor(oic, () {
      verificationSend(8, 3, 'Stay vacated by court order dated last month - record corrected.');
    });

    // ---- logins / logouts over the last week (incl. today) ----
    const logins = [
      [1, 6 * kDay + 600, 45], [14, 6 * kDay + 300, 30], [4, 5 * kDay + 200, 20], [16, 4 * kDay + 420, 25], [18, 3 * kDay + 60, 15],
      [15, 2 * kDay + 240, 0], [8, kDay + 500, 20], [3, 300, 60], [2, 240, 0], [14, 150, 35], [18, 95, 0], [1, 40, 0],
    ];
    for (final x in logins) {
      loginAt(x[0], x[1], x[2]);
    }
    // a few failed attempts
    _at(2 * kDay + 100);
    insert('login_attempts', {'username': 'eng2', 'ip_address': '127.0.0.1', 'success': 0, 'attempted_at': now()});
    _at(70);
    insert('login_attempts', {'username': 'tp1', 'ip_address': '127.0.0.1', 'success': 0, 'attempted_at': now()});

    // Super Admin security alerts
    _at(2 * kDay + 95);
    notify(panelUsers('SUPER_ADMIN'),
        type: 'ACCOUNT', tone: 'warning', icon: 'fa-user-shield', title: 'Failed login attempt — eng2',
        message: 'A wrong password was entered for eng2. The account locks after 5 failed attempts.', link: '/admin/audit-logs?area=AUTH&quick=area:AUTH');
    _at(65);
    notify(panelUsers('SUPER_ADMIN'),
        type: 'ACCOUNT', tone: 'warning', icon: 'fa-user-shield', title: 'Failed login attempt — tp1',
        message: 'A wrong password was entered for tp1 today.', link: '/admin/audit-logs?area=AUTH&quick=area:AUTH');
    _at(30);
    notify(panelUsers('SUPER_ADMIN'),
        type: 'SYSTEM', tone: 'info', icon: 'fa-chart-line', title: 'Daily activity summary',
        message: 'Verification is active; 2 properties are Auction Ready and 2 need re-verification. Open Activity & Audit for details.', link: '/admin/audit-logs');
    _at(0);
    final cutoff = now(-4 * kDay);
    for (final n in _t('notifications')) {
      if (str(n['created_at']).compareTo(cutoff) < 0) {
        n['is_read'] = 1;
        n['read_at'] = n['created_at'];
      }
    }
  }

  /* ---------- tiny sample files for the seeded rejections ---------- */
  void _sampleAttachment(Rec task, int by, String name, String kind, List<String> lines) {
    final r = find('verification_rounds', task['round_id'])!;
    String? data;
    int size;
    if (kind == 'pdf') {
      data = base64Encode(utf8.encode(samplePdf(lines)));
      size = (data.length * 3 / 4).round();
    } else {
      size = 23000 + lines.join().length * 37;
    }
    insert('attachments', {
      'task_id': task['task_id'], 'round_id': task['round_id'], 'property_id': r['property_id'], 'context': 'TASK_RESPONSE', 'file_name': name,
      'mime': kind == 'pdf' ? 'application/pdf' : 'image/jpeg', 'size': size,
      'storage': 'sample', 'kind': kind, 'lines': lines, 'data': data, 'path': null, 'uploaded_by': by,
    });
  }
}

/// A one-page PDF with the given text lines (same bytes as samplePdf() in db.js).
String samplePdf(List<String> lines) {
  String esc(String t) => t.replaceAllMapped(RegExp(r'[\\()]'), (m) => '\\${m[0]}');
  final parts = <String>[];
  for (var i = 0; i < lines.length; i++) {
    parts.add('${i > 0 ? 'T* ' : ''}(${esc(lines[i])}) Tj');
  }
  final stream = 'BT /F1 13 Tf 50 780 Td 18 TL ${parts.join(' ')} ET';
  final objs = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
    '<< /Length ${stream.length} >>\nstream\n$stream\nendstream',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
  ];
  final out = StringBuffer('%PDF-1.4\n');
  final offs = <int>[];
  for (var i = 0; i < objs.length; i++) {
    offs.add(out.length);
    out.write('${i + 1} 0 obj\n${objs[i]}\nendobj\n');
  }
  final x = out.length;
  out.write('xref\n0 6\n0000000000 65535 f \n');
  for (final o in offs) {
    out.write('${o.toString().padLeft(10, '0')} 00000 n \n');
  }
  out.write('trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$x\n%%EOF');
  return out.toString();
}
