// PRAMAN — Notifications page (js/pages/notifications.js): stats, filter tabs, search, list grouped by day.
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../ui/common.dart';
import '../ui/lists.dart';
import '../ui/shell.dart';
import 'tasks.dart' show TabChip;

class NotificationsPage extends StatelessWidget {
  final RouteCtx ctx;
  const NotificationsPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: P,
      builder: (context, _) {
        final db = P.db;
        final all = P.myNotifications();
        final unread = all.where((n) => !truthy(n['is_read'])).length;
        final cur = ctx.q('filter');
        var rows = all;
        if (cur == 'unread') {
          rows = rows.where((n) => !truthy(n['is_read'])).toList();
        } else if (kNotifTypes.containsKey(cur)) {
          rows = rows.where((n) => n['type'] == cur).toList();
        }
        final p = paginate<Rec>(rows, ctx,
            sort: 'id', dir: 'desc', filters: const ['filter'], search: (n) => [n['title'], n['message'], n['type']], sortable: {'id': (n) => toNum(n['notification_id'])});
        final t = db.today();
        final y = db.today(-1);
        final today = all.where((n) => str(n['created_at']).startsWith(t)).length;
        final tabs = <(String, String, IconData, int)>[
          ('', 'All', FA.list, all.length),
          ('unread', 'Unread', FA.envelope, unread),
          for (final e in kNotifTypes.entries) (e.key, e.value.$1, e.value.$2, all.where((n) => n['type'] == e.key).length),
        ];
        final list = <Widget>[];
        var lastDay = '';
        for (final n in p.items) {
          final d = str(n['created_at']).length >= 10 ? str(n['created_at']).substring(0, 10) : '';
          if (d != lastDay) {
            list.add(Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 6),
              child: Text((d == t ? 'Today' : (d == y ? 'Yesterday' : fmtDate(d))).toUpperCase(), style: T.s(11.5, weight: FontWeight.w700, color: C.navy700, spacing: .6)),
            ));
            lastDay = d;
          }
          list.add(NotifItem(n));
        }
        return PageBody(children: [
          PageHeader(
            icon: FA.bell,
            title: 'Notifications',
            subtitle: 'Everything that needs your attention — tasks, verification results, property updates and account alerts.',
            actions: [
              Btn('Delete read', icon: FA.broom, variant: 'light', onPressed: () async {
                final yes = await P.confirm(title: 'Delete read notifications', message: 'Delete all read notifications? Unread ones are kept.', okText: 'Yes, delete', variant: 'danger');
                if (!yes) return;
                final uid = P.uid;
                final before = db.count('notifications');
                db.removeWhere('notifications', (x) => toInt(x['user_id']) == uid && truthy(x['is_read']));
                P.flash('success', '${before - db.count('notifications')} read notification(s) deleted.');
                P.render();
              }),
              Btn('Mark all read', icon: FA.checkDouble, onPressed: () {
                for (final n in db.where('notifications', {'user_id': P.uid, 'is_read': 0})) {
                  P.markRead(n, true);
                }
                P.touch();
                P.toast('success', 'All notifications marked as read.');
              }),
            ],
          ),
          TileGrid(phoneColumns: 3, children: [
            StatCard(color: 'navy', icon: FA.bell, value: '${all.length}', label: 'Total', onTap: () => P.go('/notifications', null, true)),
            StatCard(color: 'gold', icon: FA.envelope, value: '$unread', label: 'Unread', onTap: () => P.go('/notifications', {'filter': 'unread'}, true)),
            StatCard(color: 'green', icon: FA.calendarDay, value: '$today', label: 'Today'),
          ]),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(children: [
              for (final x in tabs)
                Padding(
                  padding: const EdgeInsets.only(right: 6),
                  child: TabChip(text: x.$2, icon: x.$3, count: x.$4, active: cur == x.$1, onTap: () => P.go('/notifications', {'filter': x.$1}, true)),
                ),
            ]),
          ),
          ListCard(
            toolbar: ListToolbar(p: p, placeholder: 'Search notifications…', keep: {'filter': cur}),
            rows: list,
            empty: Padding(
              padding: const EdgeInsets.symmetric(vertical: 30),
              child: Column(children: [
                Icon(cur == 'unread' ? FA.mugHot : FA.bellSlash, size: 30, color: C.borderStrong),
                const SizedBox(height: 10),
                Text(cur == 'unread' ? 'You are all caught up!' : (p.hasFilters ? 'No matching notifications' : 'No notifications yet'), style: T.s(14, weight: FontWeight.w600, color: C.slate600)),
              ]),
            ),
            pager: p,
          ),
        ]);
      },
    );
  }
}
