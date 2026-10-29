// PRAMAN — route context passed to every page (ctx in the web views) + the page result (title + body).
import 'package:flutter/widgets.dart';

import 'format.dart';

class RouteCtx {
  final String path;
  final Map<String, String> query;
  final Map<String, int> params;
  final Rec user;
  const RouteCtx(this.path, this.query, this.params, this.user);

  /// Query value or '' (ctx.query.x || '').
  String q(String k) => query[k] ?? '';
  int get id => params['id'] ?? 0;
  int get uid => toInt(user['user_id']) ?? 0;
}

class PageSpec {
  final String title;
  final Widget body;
  const PageSpec(this.title, this.body);
}
