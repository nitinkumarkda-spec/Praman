// PRAMAN — app layout (core.js sidebar() / topbar()): drawer sidebar with the panel menu, white top bar
// with notification bell + user menu, "Back to Admin" banner while impersonating, error pages.
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';

/// Sidebar menus per panel (MENUS in core.js). null entry = section heading.
const Map<String, List<(String, IconData?, String?)>> kMenus = {
  'SUPER_ADMIN': [
    ('Dashboard', FA.gaugeHigh, '/admin/dashboard'),
    ('Masters', null, null),
    ('Role Master', FA.sitemap, '/admin/roles'),
    ('User Master', FA.users, '/admin/users'),
    ('Property Masters', null, null),
    ('Scheme', FA.map, '/admin/masters/schemes'),
    ('Sector', FA.tableCells, '/admin/masters/sectors'),
    ('Zone', FA.mapLocationDot, '/admin/masters/zones'),
    ('Ward', FA.locationCrosshairs, '/admin/masters/wards'),
    ('Assembly', FA.landmark, '/admin/masters/assemblies'),
    ('Plot Type', FA.shapes, '/admin/masters/plot-types'),
    ('Status', FA.flag, '/admin/masters/statuses'),
    ('Monitoring', null, null),
    ('Activity & Audit', FA.chartLine, '/admin/audit-logs'),
  ],
  'DATA_ENTRY': [
    ('Dashboard', FA.gaugeHigh, '/data-entry/dashboard'),
    ('Masters', null, null),
    ('Scheme Master', FA.map, '/data-entry/schemes'),
    ('Property', null, null),
    ('Property Register', FA.building, '/data-entry/properties'),
    ('Add Property', FA.circlePlus, '/data-entry/properties/create'),
  ],
  'OIC': [
    ('Dashboard', FA.gaugeHigh, '/oic/dashboard'),
    ('Verification', null, null),
    ('Properties', FA.listCheck, '/oic/properties'),
  ],
  'ROLE_USER': [
    ('Dashboard', FA.gaugeHigh, '/role/dashboard'),
    ('Verification', null, null),
    ('My Tasks', FA.clipboardList, '/role/tasks'),
  ],
};

final GlobalKey<ScaffoldState> shellScaffoldKey = GlobalKey<ScaffoldState>();

class AppShell extends StatelessWidget {
  final PageSpec spec;
  final Rec user;
  const AppShell({super.key, required this.spec, required this.user});

  @override
  Widget build(BuildContext context) {
    final imp = P.impersonator();
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent, systemNavigationBarColor: Colors.white, systemNavigationBarIconBrightness: Brightness.dark),
      child: Scaffold(
        key: shellScaffoldKey,
        backgroundColor: C.bg,
        drawer: Sidebar(user: user, path: P.path),
        drawerEdgeDragWidth: 28,
        appBar: PreferredSize(preferredSize: const Size.fromHeight(60), child: Topbar(title: spec.title, user: user)),
        body: Column(children: [
          if (imp != null) ImpersonationBanner(user: user, admin: imp),
          Expanded(child: KeyedSubtree(key: ValueKey('page-${P.nav}'), child: spec.body)),
        ]),
      ),
    );
  }
}

/* ======================= sidebar ======================= */

class Sidebar extends StatelessWidget {
  final Rec user;
  final String path;
  const Sidebar({super.key, required this.user, required this.path});

  @override
  Widget build(BuildContext context) {
    final items = kMenus[user['panel']] ?? const <(String, IconData?, String?)>[];
    var active = '';
    for (final it in items) {
      final p = it.$3;
      if (p != null && (path == p || path.startsWith('$p/')) && p.length > active.length) active = p;
    }
    void open(String to) {
      Navigator.of(context).pop();
      if (to != P.path || P.query.isNotEmpty) P.go(to);
    }

    return Drawer(
      width: math.min(300.0, MediaQuery.of(context).size.width * .86),
      backgroundColor: C.side3,
      shape: const RoundedRectangleBorder(),
      child: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [C.side1, C.side2, C.side3], stops: [0, .55, 1]),
        ),
        child: SafeArea(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(
              padding: const EdgeInsets.fromLTRB(16, 16, 8, 14),
              decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0x24FFFFFF)))),
              child: Row(children: [
                const Emblem(size: 54, ring: false),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(App.name, style: T.s(18, weight: FontWeight.w700, color: Colors.white, spacing: 2)),
                    Text(App.org, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(11, color: C.gold400)),
                  ]),
                ),
                IconButton(onPressed: () => Navigator.of(context).pop(), icon: const Icon(FA.xmark, size: 16, color: Colors.white)),
              ]),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
              child: Align(
                alignment: Alignment.centerLeft,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: C.gold400, borderRadius: BorderRadius.circular(999)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(FA.shieldHalved, size: 10, color: C.navy900),
                    const SizedBox(width: 6),
                    Text('${App.panels[user['panel']] ?? ''} Panel'.toUpperCase(), style: T.s(10.5, weight: FontWeight.w700, color: C.navy900, spacing: .5)),
                  ]),
                ),
              ),
            ),
            Expanded(
              child: ListView(padding: const EdgeInsets.fromLTRB(10, 4, 10, 16), children: [
                for (final it in items)
                  if (it.$3 == null) _section(it.$1) else _link(it.$1, it.$2!, it.$3 == active, () => open(it.$3!)),
                _section('Account'),
                _link('Change Password', FA.key, path == '/change-password', () => open('/change-password')),
                _link('Notifications', FA.bell, path == '/notifications', () => open('/notifications')),
                _link('Logout', FA.rightFromBracket, false, () {
                  Navigator.of(context).pop();
                  P.logoutAction();
                }),
              ]),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _section(String text) => Padding(
        padding: const EdgeInsets.fromLTRB(10, 16, 10, 6),
        child: Text(text.toUpperCase(), style: T.s(11, weight: FontWeight.w600, color: const Color(0xFFB9C9DF), spacing: .9)),
      );

  Widget _link(String text, IconData icon, bool on, VoidCallback onTap) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Material(
        color: on ? const Color(0x38D9B84A) : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        child: InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: onTap,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
            decoration: BoxDecoration(border: Border(left: BorderSide(color: on ? C.gold500 : Colors.transparent, width: 3))),
            child: Row(children: [
              SizedBox(width: 22, child: Icon(icon, size: 15, color: on ? C.gold400 : const Color(0xFFD7E1EF))),
              const SizedBox(width: 10),
              Expanded(child: Text(text, style: T.s(14, weight: on ? FontWeight.w600 : FontWeight.w500, color: on ? Colors.white : const Color(0xFFEEF3FA)))),
            ]),
          ),
        ),
      ),
    );
  }
}

/* ======================= top bar ======================= */

class Topbar extends StatelessWidget {
  final String title;
  final Rec user;
  const Topbar({super.key, required this.title, required this.user});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(color: Colors.white, boxShadow: [BoxShadow(color: Color(0x140F2B52), blurRadius: 10, offset: Offset(0, 2))]),
      child: SafeArea(
        bottom: false,
        child: SizedBox(
          height: 60,
          child: Row(children: [
            IconButton(
              tooltip: 'Menu',
              onPressed: () => shellScaffoldKey.currentState?.openDrawer(),
              icon: const Icon(FA.bars, size: 18, color: C.navy800),
            ),
            Expanded(child: Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(16.5, weight: FontWeight.w600, color: C.text))),
            const NotifBell(),
            const SizedBox(width: 2),
            UserMenu(user: user),
            const SizedBox(width: 6),
          ]),
        ),
      ),
    );
  }
}

class UserMenu extends StatelessWidget {
  final Rec user;
  const UserMenu({super.key, required this.user});

  @override
  Widget build(BuildContext context) {
    return PopupMenuButton<String>(
      tooltip: 'Account',
      offset: const Offset(0, 50),
      color: Colors.white,
      surfaceTintColor: Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12), side: const BorderSide(color: C.border)),
      onSelected: (v) async {
        if (v == 'password') {
          P.go('/change-password');
        } else if (v == 'notifications') {
          P.go('/notifications');
        } else if (v == 'reset') {
          final yes = await P.confirm(title: 'Reset demo data', message: 'Reset ALL demo data to the original sample data? Everything you added will be removed.', okText: 'Yes, reset', variant: 'danger');
          if (yes) P.demoReset();
        } else if (v == 'logout') {
          P.logoutAction();
        }
      },
      itemBuilder: (c) => [
        PopupMenuItem<String>(
          enabled: false,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(str(user['name']), style: T.s(14, weight: FontWeight.w700, color: C.text)),
            Text('${user['username']} · ${user['designation']}', style: T.tiny),
            Text('Emp. Code: ${user['emp_code']}', style: T.tiny),
            Text('Last login: ${fmtDateTime(user['last_login_at'])}', style: T.tiny),
          ]),
        ),
        const PopupMenuDivider(),
        _item('password', FA.key, 'Change Password'),
        _item('notifications', FA.bell, 'Notifications'),
        _item('reset', FA.rotate, 'Reset demo data'),
        _item('logout', FA.rightFromBracket, 'Logout', danger: true),
      ],
      child: Padding(padding: const EdgeInsets.all(4), child: Avatar(str(user['name']), size: 36)),
    );
  }

  PopupMenuItem<String> _item(String v, IconData icon, String text, {bool danger = false}) => PopupMenuItem<String>(
        value: v,
        child: Row(children: [
          Icon(icon, size: 14, color: danger ? C.red600 : C.slate600),
          const SizedBox(width: 12),
          Text(text, style: T.s(14, color: danger ? C.red600 : C.text)),
        ]),
      );
}

class ImpersonationBanner extends StatelessWidget {
  final Rec user;
  final Rec admin;
  const ImpersonationBanner({super.key, required this.user, required this.admin});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(14, 8, 8, 8),
      color: C.gold100,
      child: Row(children: [
        const Icon(FA.userSecret, size: 14, color: C.gold600),
        const SizedBox(width: 8),
        Expanded(
          child: Text('Logged in as ${user['name']} — you are ${admin['name']}', maxLines: 2, style: T.s(12.5, weight: FontWeight.w600, color: const Color(0xFF6B5310))),
        ),
        Btn('Back to Admin', icon: FA.arrowLeft, variant: 'gold', size: 'sm', onPressed: () {
          final left = P.stopImpersonation();
          P.flash('success', "Back to Super Admin. You left ${left != null ? left['name'] : 'the user'}'s panel.");
          P.history.clear();
          P.go('/admin/users', null, true);
        }),
      ]),
    );
  }
}

/* ======================= notifications bell + panel ======================= */

const Map<String, (String, IconData)> kNotifTypes = {
  'TASK': ('Tasks', FA.clipboardList),
  'VERIFICATION': ('Verification', FA.listCheck),
  'PROPERTY': ('Property', FA.building),
  'ACCOUNT': ('Account', FA.userShield),
  'SYSTEM': ('System', FA.bullhorn),
};

class NotifBell extends StatefulWidget {
  const NotifBell({super.key});
  @override
  State<NotifBell> createState() => _NotifBellState();
}

class _NotifBellState extends State<NotifBell> with SingleTickerProviderStateMixin {
  late final AnimationController _ring = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
  int _seenRing = P.bellRing;

  @override
  void dispose() {
    _ring.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (P.bellRing != _seenRing) {
      _seenRing = P.bellRing;
      // start the ring after this frame (not while building)
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) _ring.forward(from: 0);
      });
    }
    final n = P.unreadCount();
    return IconButton(
      tooltip: 'Notifications, $n unread',
      onPressed: () => showNotifPanel(context),
      icon: Stack(clipBehavior: Clip.none, children: [
        AnimatedBuilder(
          animation: _ring,
          builder: (context, child) {
            final t = _ring.value;
            final a = t == 0 || t == 1 ? 0.0 : math.sin(t * math.pi * 6) * .35 * (1 - t);
            return Transform.rotate(angle: a, alignment: Alignment.topCenter, child: child);
          },
          child: Icon(FA.bell, size: 19, color: n > 0 ? C.navy700 : C.slate600),
        ),
        if (n > 0)
          Positioned(
            right: -8,
            top: -7,
            child: Container(
              constraints: const BoxConstraints(minWidth: 17),
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
              decoration: BoxDecoration(color: C.red600, borderRadius: BorderRadius.circular(999), border: Border.all(color: Colors.white, width: 1.5)),
              child: Text(n > 99 ? '99+' : '$n', textAlign: TextAlign.center, style: T.s(10, weight: FontWeight.w700, color: Colors.white)),
            ),
          ),
      ]),
    );
  }
}

Future<void> showNotifPanel(BuildContext context) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
    builder: (c) => const _NotifPanel(),
  );
}

class _NotifPanel extends StatefulWidget {
  const _NotifPanel();
  @override
  State<_NotifPanel> createState() => _NotifPanelState();
}

class _NotifPanelState extends State<_NotifPanel> {
  String _tab = 'all';
  bool _settings = false;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: P,
      builder: (context, _) {
        final all = P.myNotifications();
        final unread = all.where((n) => !truthy(n['is_read'])).length;
        final list = (_tab == 'unread' ? all.where((n) => !truthy(n['is_read'])).toList() : all).take(8).toList();
        return SafeArea(
          child: ConstrainedBox(
            constraints: BoxConstraints(maxHeight: MediaQuery.of(context).size.height * .82),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Center(child: Container(margin: const EdgeInsets.only(top: 10), width: 40, height: 4, decoration: BoxDecoration(color: C.border, borderRadius: BorderRadius.circular(4)))),
              Padding(
                padding: const EdgeInsets.fromLTRB(18, 12, 8, 6),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('Notifications', style: T.s(17, weight: FontWeight.w700, color: C.navy800)),
                      Text(unread > 0 ? '$unread unread' : 'All caught up', style: T.small),
                    ]),
                  ),
                  TextButton.icon(
                    onPressed: unread == 0
                        ? null
                        : () {
                            for (final n in P.db.where('notifications', {'user_id': P.uid, 'is_read': 0})) {
                              P.markRead(n, true);
                            }
                            P.touch();
                            P.toast('success', 'All notifications marked as read.');
                          },
                    icon: const Icon(FA.checkDouble, size: 13),
                    label: const Text('Mark all read'),
                  ),
                  IconButton(onPressed: () => setState(() => _settings = !_settings), icon: Icon(FA.gear, size: 15, color: _settings ? C.navy700 : C.slate600), tooltip: 'Settings'),
                ]),
              ),
              if (_settings)
                Container(
                  margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(10), border: Border.all(color: C.border)),
                  child: Row(children: [
                    Expanded(child: Text('Sound & vibration for new notifications', style: T.s(13.5))),
                    Switch(value: P.soundOn, onChanged: (v) => P.soundOn = v),
                  ]),
                ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: Row(children: [
                  _tabBtn('all', 'All'),
                  const SizedBox(width: 8),
                  _tabBtn('unread', unread > 0 ? 'Unread ($unread)' : 'Unread'),
                ]),
              ),
              const Divider(height: 1, color: C.border),
              Flexible(
                child: list.isEmpty
                    ? Padding(
                        padding: const EdgeInsets.symmetric(vertical: 30),
                        child: Column(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(FA.bellSlash, size: 26, color: C.borderStrong),
                          const SizedBox(height: 8),
                          Text(_tab == 'unread' ? 'You are all caught up!' : 'No notifications yet', style: T.small),
                        ]),
                      )
                    : ListView(shrinkWrap: true, children: [for (final n in list) NotifItem(n, compact: true, onOpened: () => Navigator.of(context).pop())]),
              ),
              const Divider(height: 1, color: C.border),
              InkWell(
                onTap: () {
                  Navigator.of(context).pop();
                  P.go('/notifications');
                },
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(FA.list, size: 12, color: C.navy700),
                    const SizedBox(width: 8),
                    Text('View all notifications', style: T.s(13.5, weight: FontWeight.w600, color: C.navy700)),
                  ]),
                ),
              ),
            ]),
          ),
        );
      },
    );
  }

  Widget _tabBtn(String key, String text) {
    final on = _tab == key;
    return Material(
      color: on ? C.navy700 : Colors.white,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: BorderSide(color: on ? C.navy700 : C.border)),
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: () => setState(() => _tab = key),
        child: Padding(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7), child: Text(text, style: T.s(13, weight: FontWeight.w600, color: on ? Colors.white : C.slate600))),
      ),
    );
  }
}

/// Open a notification link like '/admin/audit-logs?area=AUTH'.
void openLink(String link) {
  final parsed = App.parseLocation(link);
  P.go(parsed.$1, parsed.$2);
}

/// One notification row (bell panel + notifications page).
class NotifItem extends StatelessWidget {
  final Rec n;
  final bool compact;
  final VoidCallback? onOpened;
  const NotifItem(this.n, {super.key, this.compact = false, this.onOpened});

  @override
  Widget build(BuildContext context) {
    final unread = !truthy(n['is_read']);
    final tone = Tone.of(str(n['tone']) == 'info' ? 'info' : str(n['tone']));
    final type = kNotifTypes[n['type']];
    return Material(
      color: unread ? const Color(0xFFF3F7FD) : Colors.white,
      child: InkWell(
        onTap: () {
          P.markRead(n, true);
          if (onOpened != null) onOpened!();
          final link = n['link'];
          if (link != null && str(link).isNotEmpty) {
            openLink(str(link));
          } else {
            P.touch();
          }
        },
        child: Container(
          padding: const EdgeInsets.fromLTRB(14, 12, 6, 12),
          decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: C.border))),
          child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(
              width: 36,
              height: 36,
              decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(10)),
              alignment: Alignment.center,
              child: Icon(fa(str(n['icon'])), size: 14, color: tone.fg),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(str(n['title']), style: T.s(13.5, weight: unread ? FontWeight.w700 : FontWeight.w600, color: unread ? C.text : C.slate600)),
                const SizedBox(height: 2),
                Text(str(n['message']), maxLines: compact ? 2 : 6, overflow: TextOverflow.ellipsis, style: T.s(12.5, color: C.slate600, height: 1.4)),
                const SizedBox(height: 4),
                Row(children: [
                  const Icon(FA.clock, size: 10, color: C.muted),
                  const SizedBox(width: 5),
                  Text('${ago(n['created_at'])} · ${type?.$1 ?? str(n['type'])}', style: T.tiny),
                ]),
              ]),
            ),
            Column(children: [
              if (unread) Container(margin: const EdgeInsets.only(top: 4, bottom: 4), width: 8, height: 8, decoration: const BoxDecoration(color: C.blue600, shape: BoxShape.circle)),
              IconButton(
                visualDensity: VisualDensity.compact,
                tooltip: unread ? 'Mark as read' : 'Mark as unread',
                onPressed: () {
                  P.markRead(n, unread);
                  P.touch();
                },
                icon: Icon(unread ? FA.envelopeOpen : FA.envelope, size: 13, color: C.slate600),
              ),
              IconButton(
                visualDensity: VisualDensity.compact,
                tooltip: 'Delete',
                onPressed: () {
                  P.db.removeWhere('notifications', (x) => x['notification_id'] == n['notification_id']);
                  P.touch();
                  P.toast('info', 'Notification deleted.');
                },
                icon: const Icon(FA.trashCan, size: 13, color: C.slate600),
              ),
            ]),
          ]),
        ),
      ),
    );
  }
}

/* ======================= error page ======================= */

class ErrorPage extends StatelessWidget {
  final int status;
  final String title;
  final String message;
  const ErrorPage({super.key, required this.status, required this.title, required this.message});

  @override
  Widget build(BuildContext context) {
    final IconData icon = status == 403 ? FA.hand : (status == 404 ? FA.mapLocationDot : FA.triangleExclamation);
    return PageBody(children: [
      FadeSlideIn(
        child: AppCard(
          padding: const EdgeInsets.fromLTRB(20, 34, 20, 30),
          child: Column(children: [
            Bounce(child: Icon(icon, size: 40, color: C.gold500)),
            const SizedBox(height: 12),
            Text('$status', style: T.s(44, weight: FontWeight.w800, color: C.navy800, height: 1)),
            const SizedBox(height: 6),
            Text(title, textAlign: TextAlign.center, style: T.s(19, weight: FontWeight.w700)),
            const SizedBox(height: 6),
            Text(message, textAlign: TextAlign.center, style: T.small),
            const SizedBox(height: 18),
            Btn('Go to Dashboard', icon: FA.house, onPressed: () => P.go(P.homeOf(P.user()))),
          ]),
        ),
      ),
    ]);
  }
}
