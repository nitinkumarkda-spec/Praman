// PRAMAN — app state: auth / session, navigation (the web app's hash router), flash messages,
// toasts, confirm dialog and new-notification alerts. Port of core.js + notifications.js glue.
import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../ui/dialogs.dart';
import 'db.dart';
import 'format.dart';

class Flash {
  final String type; // success | error | warning | info
  final String message;
  Flash(this.type, this.message);
}

/// Route guard: returns a new location to redirect to (or null). Set by routes.dart.
typedef RouteGuard = String? Function(String path, Map<String, String> query, Rec user);

class App extends ChangeNotifier {
  App._();
  static final App I = App._();

  static const String name = 'PRAMAN';
  static const String fullName = 'Property Records & Auction-readiness Multi-section Assessment Network';
  static const String org = 'Kota Development Authority';
  static const String kdaSite = 'https://kdakota.rajasthan.gov.in/';
  static const Map<String, String> panels = {'SUPER_ADMIN': 'Super Admin', 'DATA_ENTRY': 'Data Entry', 'OIC': 'OIC Auction', 'ROLE_USER': 'Role User'};
  static const Map<String, String> homes = {
    'SUPER_ADMIN': '/admin/dashboard',
    'DATA_ENTRY': '/data-entry/dashboard',
    'OIC': '/oic/dashboard',
    'ROLE_USER': '/role/dashboard',
  };
  static const int maxAttempts = 5;
  static const int lockoutMinutes = 15;
  static const int passwordMin = 10;
  static const String _sessionKey = 'praman_demo_session';
  static const String _prefKey = 'praman_notif_prefs';
  static const String _seenKey = 'praman_notif_seen_';

  final Db db = Db();
  late SharedPreferences prefs;
  bool ready = false;
  Map<String, dynamic>? _session;
  RouteGuard? guard;

  // navigation (hash router)
  String path = '/';
  Map<String, String> query = {};
  final List<String> history = [];
  int nav = 0; // bumps on every navigation — page widgets are keyed by it
  int bellRing = 0;
  List<Flash> flashes = [];
  final List<Flash> _pending = [];
  Timer? _notifTimer;

  final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();
  final GlobalKey<ScaffoldMessengerState> messengerKey = GlobalKey<ScaffoldMessengerState>();

  Future<void> init() async {
    prefs = await SharedPreferences.getInstance();
    db.attach(prefs, user: user, impersonator: impersonator);
    _session = _readSession();
    db.load();
    ready = true;
    _setLocation('/');
  }

  /* ---------------- session ---------------- */
  Map<String, dynamic>? _readSession() {
    try {
      final raw = prefs.getString(_sessionKey);
      if (raw == null) return null;
      final m = jsonDecode(raw);
      return m is Map ? Map<String, dynamic>.from(m) : null;
    } catch (_) {
      return null;
    }
  }

  void _writeSession(Map<String, dynamic>? s) {
    _session = s;
    if (s == null) {
      prefs.remove(_sessionKey);
    } else {
      prefs.setString(_sessionKey, jsonEncode(s));
    }
  }

  /* ---------------- auth (PramanAuth) ---------------- */
  Rec? user() {
    final s = _session;
    if (s == null) return null;
    final u = db.find('users', s['user_id']);
    if (u == null || !truthy(u['is_active'])) return null;
    final r = u['role_id'] != null ? db.find('roles', u['role_id']) : null;
    return <String, dynamic>{...u, 'role_name': r?['role_name']};
  }

  int get uid => toInt(user()?['user_id']) ?? 0;
  bool get loggedIn => user() != null;

  Rec? impersonator() {
    final s = _session;
    if (s == null || s['impersonator_id'] == null) return null;
    return db.find('users', s['impersonator_id']);
  }

  String homeOf(Rec? u) => u == null ? '/login' : (homes[u['panel']] ?? '/login');

  ({bool ok, String message}) attempt(String usernameIn, String password) {
    final username = usernameIn.trim().toLowerCase();
    final u = db.firstWhere('users', (x) => x['username'] == username);
    void record(bool ok) => db.insert('login_attempts', {
          'username': username.length > 50 ? username.substring(0, 50) : username,
          'ip_address': '127.0.0.1',
          'success': ok ? 1 : 0,
          'attempted_at': db.now(),
        });

    if (u != null && u['locked_until'] != null && str(u['locked_until']).compareTo(db.now()) > 0) {
      record(false);
      final until = parseDate(u['locked_until']) ?? DateTime.now();
      final mins = (until.difference(DateTime.now()).inSeconds / 60).ceil();
      return (ok: false, message: 'Account is temporarily locked due to repeated failed logins. Try again in $mins minute(s).');
    }
    if (u == null || u['password'] != password) {
      record(false);
      if (u != null) {
        final f = (toInt(u['failed_attempts']) ?? 0) + 1;
        if (f >= maxAttempts) {
          db.update('users', u['user_id'], {'failed_attempts': 0, 'locked_until': db.now(lockoutMinutes)});
          db.audit('USER', u['user_id'], 'ACCOUNT_LOCKED', null, {'minutes': lockoutMinutes}, null, u);
          db.notify(db.panelUsers('SUPER_ADMIN'),
              type: 'ACCOUNT', tone: 'danger', icon: 'fa-user-lock', title: 'Account locked — ${u['username']}',
              message: '${u['name']} was locked for $lockoutMinutes minutes after $maxAttempts failed logins.', link: '/admin/users?status=locked');
        } else {
          db.update('users', u['user_id'], {'failed_attempts': f});
        }
      }
      return (ok: false, message: 'Invalid username or password.');
    }
    if (!truthy(u['is_active'])) {
      record(false);
      return (ok: false, message: 'Your account is deactivated. Please contact the System Administrator.');
    }
    db.update('users', u['user_id'], {'failed_attempts': 0, 'locked_until': null, 'last_login_at': db.now(), 'last_login_ip': '127.0.0.1'});
    record(true);
    _writeSession({'user_id': u['user_id'], 'impersonator_id': null});
    db.audit('AUTH', u['user_id'], 'LOGIN', null, {'message': 'Login successful'}, null, u);
    return (ok: true, message: 'Welcome, ${u['name']}.');
  }

  void logout() {
    final u = user();
    messengerKey.currentState?.clearSnackBars(); // never show one user's toasts to the next user
    if (u != null) db.audit('AUTH', u['user_id'], 'LOGOUT', null, {'message': 'User logged out'}, null, u);
    _writeSession(null);
  }

  void impersonate(Rec target) {
    final admin = user();
    db.audit('USER', target['user_id'], 'IMPERSONATION_STARTED', null, {'target': target['username']}, null, admin);
    _writeSession({'user_id': target['user_id'], 'impersonator_id': admin?['user_id']});
  }

  Rec? stopImpersonation() {
    final imp = impersonator();
    final u = user();
    if (imp == null) return null;
    _writeSession({'user_id': imp['user_id'], 'impersonator_id': null});
    db.audit('USER', u?['user_id'], 'IMPERSONATION_ENDED', null, null, null, imp);
    return u;
  }

  /* ---------------- shared actions (core.js actions) ---------------- */
  void logoutAction() {
    logout();
    flash('success', 'You have been logged out securely.');
    history.clear();
    go('/login', null, true);
  }

  void demoReset() {
    final u = user();
    db.reset();
    _writeSession(u != null ? {'user_id': u['user_id'], 'impersonator_id': null} : null);
    flash('success', 'Demo data has been reset to the original sample data.');
    history.clear();
    go(u != null ? homeOf(user()) : '/login', null, true);
  }

  /* ---------------- navigation ---------------- */
  String get location => urlOf(path, query);

  static String urlOf(String p, [Map<String, Object?>? q]) {
    var u = p;
    if (q != null) {
      final qs = q.entries
          .where((e) => e.value != null && e.value.toString() != '')
          .map((e) => '${Uri.encodeQueryComponent(e.key)}=${Uri.encodeQueryComponent(e.value.toString())}')
          .join('&');
      if (qs.isNotEmpty) u += '?$qs';
    }
    return u;
  }

  static (String, Map<String, String>) parseLocation(String loc) {
    final h = loc.split('#').first;
    final i = h.indexOf('?');
    var p = i == -1 ? h : h.substring(0, i);
    if (p.isEmpty) p = '/';
    final q = <String, String>{};
    if (i != -1) {
      for (final pair in h.substring(i + 1).split('&')) {
        if (pair.isEmpty) continue;
        final j = pair.indexOf('=');
        final k = Uri.decodeQueryComponent(j == -1 ? pair : pair.substring(0, j));
        final v = j == -1 ? '' : Uri.decodeQueryComponent(pair.substring(j + 1));
        q[k] = v;
      }
    }
    return (p, q);
  }

  /// P.go(path, query) — [replace] keeps the back history unchanged (filters, redirects).
  void go(String p, [Map<String, Object?>? q, bool replace = false]) {
    final target = urlOf(p, q);
    if (!replace && target != location) {
      history.add(location);
      if (history.length > 80) history.removeAt(0);
    }
    _setLocation(target);
  }

  /// P.render() — rebuild the current page (also shows pending flash messages).
  void render() => _setLocation(location);

  /// Light refresh (no page reset) after an in-place change.
  void touch() => notifyListeners();

  /// Back button: previous page of the in-app history.
  bool back() {
    if (history.isEmpty) return false;
    _setLocation(history.removeLast());
    return true;
  }

  void _setLocation(String loc) {
    final parsed = parseLocation(loc);
    var p = parsed.$1;
    var q = parsed.$2;
    final u = user();
    if (_session != null && u == null) _writeSession(null);
    if (p == '/' || p == '/login') {
      if (u != null) {
        p = homeOf(u);
        q = {};
      }
    } else if (u == null) {
      flash('warning', 'Please login to continue.');
      p = '/login';
      q = {};
    } else if (guard != null) {
      final to = guard!(p, q, u);
      if (to != null && to != urlOf(p, q)) {
        _setLocation(to);
        return;
      }
    }
    path = p;
    query = q;
    flashes = List<Flash>.of(_pending);
    _pending.clear();
    for (final f in flashes) {
      if (f.type == 'success' || f.type == 'info') {
        Timer(const Duration(seconds: 6), () {
          if (flashes.contains(f)) {
            flashes = flashes.where((x) => !identical(x, f)).toList();
            notifyListeners();
          }
        });
      }
    }
    nav++;
    notifyListeners();
    _notifTimer?.cancel();
    _notifTimer = Timer(const Duration(milliseconds: 450), checkNewNotifications);
  }

  /* ---------------- flash / toast / confirm ---------------- */
  void flash(String type, String message) => _pending.add(Flash(type, message));
  bool get hasPendingFlash => _pending.isNotEmpty;

  void dismissFlash(Flash f) {
    flashes = flashes.where((x) => !identical(x, f)).toList();
    notifyListeners();
  }

  void toast(String type, String message, [int? ms]) {
    final m = messengerKey.currentState;
    if (m == null || message.isEmpty) return;
    m.showSnackBar(SnackBar(
      behavior: SnackBarBehavior.floating,
      backgroundColor: Colors.transparent,
      elevation: 0,
      padding: EdgeInsets.zero,
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 14),
      duration: Duration(milliseconds: ms ?? (type == 'error' ? 7000 : 4500)),
      content: ToastView(type: type, message: message),
    ));
  }

  Future<bool> confirm({String title = 'Please confirm', required String message, String okText = 'Yes, continue', String cancelText = 'No, cancel', String variant = 'primary'}) async {
    final ctx = navigatorKey.currentContext;
    if (ctx == null) return false;
    final r = await showDialog<bool>(
      context: ctx,
      builder: (c) => ConfirmDialog(title: title, message: message, okText: okText, cancelText: cancelText, variant: variant),
    );
    return r ?? false;
  }

  /* ---------------- notifications (bell, alerts) ---------------- */
  List<Rec> myNotifications() {
    final l = db.where('notifications', {'user_id': uid});
    l.sort((a, b) => (toInt(b['notification_id']) ?? 0).compareTo(toInt(a['notification_id']) ?? 0));
    return l;
  }

  int unreadCount() => db.count('notifications', {'user_id': uid, 'is_read': 0});

  void markRead(Rec n, bool read) {
    db.update('notifications', n['notification_id'], {'is_read': read ? 1 : 0, 'read_at': read ? db.now() : null});
  }

  bool get soundOn {
    try {
      final m = jsonDecode(prefs.getString(_prefKey) ?? '{}');
      return m is Map && m['sound'] is bool ? m['sound'] as bool : true;
    } catch (_) {
      return true;
    }
  }

  set soundOn(bool v) {
    prefs.setString(_prefKey, jsonEncode({'sound': v}));
    notifyListeners();
  }

  /// Toast + bell ring + sound for notifications that arrived since the last check.
  void checkNewNotifications() {
    final u = user();
    if (u == null) return;
    final id = toInt(u['user_id'])!;
    if (u['panel'] == 'ROLE_USER') db.overdueReminders(id);
    final seenKey = '$_seenKey$id';
    final seen = prefs.getInt(seenKey) ?? 0;
    final mine = myNotifications();
    final fresh = mine.where((n) => !truthy(n['is_read']) && (toInt(n['notification_id']) ?? 0) > seen).toList();
    final maxId = mine.fold<int>(seen, (m, n) => math.max(m, toInt(n['notification_id']) ?? 0));
    prefs.setInt(seenKey, maxId);
    if (fresh.isEmpty) return;
    bellRing++;
    notifyListeners();
    if (fresh.length <= 2) {
      for (final n in fresh) {
        final tone = n['tone'] == 'danger' ? 'warning' : (n['tone'] == 'success' ? 'success' : 'info');
        toast(tone, '${n['title']} — ${n['message']}', 6500);
      }
    } else {
      toast('info', 'You have ${fresh.length} new notifications. Tap the bell to see them.', 6500);
    }
    if (soundOn) {
      SystemSound.play(SystemSoundType.click);
      HapticFeedback.mediumImpact();
    }
  }
}

/// Short alias used by the pages: `P.db`, `P.go(...)`, `P.toast(...)` …
App get P => App.I;
