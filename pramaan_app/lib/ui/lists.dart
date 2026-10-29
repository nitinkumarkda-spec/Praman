// PRAMAN — list pages: paginator (App\Core\Paginator / core.js paginate), toolbar (search, filters, sort,
// rows per page), pagination bar and the record card used instead of wide tables on phones.
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';
import 'forms.dart';

const List<int> kPerPage = [10, 25, 50];

class PageResult<E> {
  final List<E> items;
  final int total;
  final int page;
  final int perPage;
  final String sort;
  final String dir;
  final String search;
  final String path;
  final Map<String, String> query;
  final List<String> filterKeys;
  const PageResult({required this.items, required this.total, required this.page, required this.perPage, required this.sort, required this.dir, required this.search, required this.path, required this.query, required this.filterKeys});

  int get pages => math.max(1, (total / perPage).ceil());
  int get from => total == 0 ? 0 : (page - 1) * perPage + 1;
  int get to => math.min(page * perPage, total);
  bool get hasFilters => search.isNotEmpty || filterKeys.any((k) => (query[k] ?? '').isNotEmpty);
  String filter(String k) => query[k] ?? '';

  /// The current list state as query parameters (p.url()).
  Map<String, Object?> base() {
    final b = <String, Object?>{'q': search, 'sort': sort, 'dir': dir, 'per_page': perPage == 10 ? '' : perPage, 'page': page > 1 ? page : ''};
    for (final k in filterKeys) {
      b[k] = query[k] ?? '';
    }
    return b;
  }

  void go(Map<String, Object?> over) => P.go(path, {...base(), ...over}, true);

  void goPage(int n) => P.go(path, {...base(), 'page': n > 1 ? n : ''}, true);

  List<int> window() {
    final out = <int>[];
    for (var i = 1; i <= pages; i++) {
      if (i == 1 || i == pages || (i - page).abs() <= 1) {
        out.add(i);
      } else if (out.isNotEmpty && out.last != 0) {
        out.add(0);
      }
    }
    return out;
  }
}

PageResult<E> paginate<E>(
  List<E> rows,
  RouteCtx ctx, {
  required String sort,
  String dir = 'asc',
  List<String> filters = const [],
  List<Object?> Function(E e)? search,
  Map<String, Object? Function(E e)> sortable = const {},
}) {
  final q = ctx.query;
  final s = sortable.containsKey(q['sort']) ? q['sort']! : sort;
  final d = q['dir'] == 'asc' || q['dir'] == 'desc' ? q['dir']! : dir;
  final pp = int.tryParse(q['per_page'] ?? '') ?? 10;
  final perPage = kPerPage.contains(pp) ? pp : 10;
  final term = (q['q'] ?? '').trim();

  var list = rows;
  if (term.isNotEmpty && search != null) {
    final t = term.toLowerCase();
    list = list.where((r) => search(r).any((v) => str(v).toLowerCase().contains(t))).toList();
  }
  final key = sortable[s];
  if (key != null) {
    list = List<E>.of(list);
    sortBy<E>(list, key, desc: d == 'desc');
  }
  final total = list.length;
  final pages = math.max(1, (total / perPage).ceil());
  final page = math.min(math.max(1, int.tryParse(q['page'] ?? '') ?? 1), pages);
  final items = list.skip((page - 1) * perPage).take(perPage).toList();
  return PageResult<E>(items: items, total: total, page: page, perPage: perPage, sort: s, dir: d, search: term, path: ctx.path, query: q, filterKeys: filters);
}

/// One select filter of the toolbar.
class FilterDef {
  final String key;
  final String label;
  final Map<String, String> options;
  final String allText;
  final bool enabled;
  final String? resetKey; // data-reset-on-change (scheme → sector)
  const FilterDef(this.key, this.label, this.options, this.allText, {this.enabled = true, this.resetKey});
}

/// .toolbar — search box, filter selects, sort, rows per page, reset.
class ListToolbar extends StatefulWidget {
  final PageResult p;
  final String placeholder;
  final List<FilterDef> filters;
  final List<(String, String)> sorts; // key, label
  final Map<String, Object?> keep; // hidden inputs (e.g. status tab)
  const ListToolbar({super.key, required this.p, this.placeholder = 'Search…', this.filters = const [], this.sorts = const [], this.keep = const {}});
  @override
  State<ListToolbar> createState() => _ListToolbarState();
}

class _ListToolbarState extends State<ListToolbar> {
  late final TextEditingController _c = TextEditingController(text: widget.p.search);
  bool _open = false;

  @override
  void initState() {
    super.initState();
    _open = widget.filters.any((f) => widget.p.filter(f.key).isNotEmpty);
  }

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  void _apply(Map<String, Object?> over) {
    final p = widget.p;
    P.go(p.path, {...p.base(), ...widget.keep, 'page': '', ...over}, true);
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.p;
    final activeFilters = widget.filters.where((f) => p.filter(f.key).isNotEmpty).length;
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 12, 12, 10),
      decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: C.border))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          Expanded(
            child: TextField(
              controller: _c,
              textInputAction: TextInputAction.search,
              onSubmitted: (v) => _apply({'q': v.trim()}),
              onChanged: (_) => setState(() {}),
              maxLength: 100,
              style: T.s(14),
              decoration: inputDeco(
                hint: widget.placeholder,
                prefix: FA.magnifyingGlass,
                suffix: _c.text.isEmpty && p.search.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(FA.xmark, size: 13, color: C.slate600),
                        onPressed: () {
                          _c.clear();
                          _apply({'q': ''});
                        },
                      ),
              ),
            ),
          ),
          if (widget.filters.isNotEmpty || widget.sorts.isNotEmpty) ...[
            const SizedBox(width: 8),
            Stack(clipBehavior: Clip.none, children: [
              IconBtn(FA.sliders, size: 44, variant: _open ? 'primary' : 'light', tooltip: 'Filters & sort', onPressed: () => setState(() => _open = !_open)),
              if (activeFilters > 0)
                Positioned(
                  right: -4,
                  top: -4,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(color: C.gold500, borderRadius: BorderRadius.circular(999)),
                    child: Text('$activeFilters', style: T.s(10.5, weight: FontWeight.w700, color: C.navy900)),
                  ),
                ),
            ]),
          ],
        ]),
        AnimatedSize(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOut,
          alignment: Alignment.topCenter,
          child: !_open
              ? const SizedBox(width: double.infinity)
              : Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: LayoutBuilder(builder: (context, c) {
                    final w = c.maxWidth >= 520 ? (c.maxWidth - 16) / 3 : (c.maxWidth - 8) / 2;
                    return Wrap(spacing: 8, runSpacing: 8, children: [
                      for (final f in widget.filters)
                        SizedBox(
                          width: w,
                          child: SelectBox(
                            compact: true,
                            enabled: f.enabled,
                            text: f.options[p.filter(f.key)] ?? f.allText,
                            isPlaceholder: p.filter(f.key).isEmpty,
                            onTap: () async {
                              final v = await pickOption(context, title: f.label, options: f.options, selected: p.filter(f.key), placeholder: f.allText);
                              if (v == null) return;
                              _apply({f.key: v, if (f.resetKey != null) f.resetKey!: ''});
                            },
                          ),
                        ),
                      if (widget.sorts.isNotEmpty)
                        SizedBox(
                          width: w,
                          child: SelectBox(
                            compact: true,
                            icon: FA.sort,
                            text: 'Sort: ${widget.sorts.firstWhere((s) => s.$1 == p.sort, orElse: () => widget.sorts.first).$2}',
                            onTap: () async {
                              final v = await pickOption(context, title: 'Sort by', options: {for (final s in widget.sorts) s.$1: s.$2}, selected: p.sort);
                              if (v == null || v.isEmpty) return;
                              _apply({'sort': v, 'dir': v == p.sort ? (p.dir == 'asc' ? 'desc' : 'asc') : 'asc'});
                            },
                          ),
                        ),
                      if (widget.sorts.isNotEmpty)
                        SizedBox(
                          width: w,
                          child: SelectBox(
                            compact: true,
                            icon: p.dir == 'asc' ? FA.arrowUpWideShort : FA.arrowDownWideShort,
                            text: p.dir == 'asc' ? 'Ascending' : 'Descending',
                            onTap: () => _apply({'dir': p.dir == 'asc' ? 'desc' : 'asc'}),
                          ),
                        ),
                      SizedBox(
                        width: w,
                        child: SelectBox(
                          compact: true,
                          icon: FA.tableList,
                          text: 'Show ${p.perPage}',
                          onTap: () async {
                            final v = await pickOption(context, title: 'Rows per page', options: {for (final n in kPerPage) '$n': '$n rows'}, selected: '${p.perPage}');
                            if (v == null || v.isEmpty) return;
                            _apply({'per_page': v == '10' ? '' : v});
                          },
                        ),
                      ),
                      if (p.hasFilters)
                        SizedBox(
                          width: w,
                          child: Btn('Reset', icon: FA.rotateLeft, variant: 'light', size: 'sm', block: true, onPressed: () {
                            _c.clear();
                            P.go(p.path, {...widget.keep}, true);
                          }),
                        ),
                    ]);
                  }),
                ),
        ),
      ]),
    );
  }
}

/// .pagination-bar — "Showing 1–10 of 23 records" + page numbers.
class PaginationBar extends StatelessWidget {
  final PageResult p;
  const PaginationBar(this.p, {super.key});

  @override
  Widget build(BuildContext context) {
    final info = p.total > 0 ? 'Showing ${fmtNum(p.from)}–${fmtNum(p.to)} of ${fmtNum(p.total)} record${p.total == 1 ? '' : 's'}' : 'No records';
    return Container(
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 12),
      decoration: const BoxDecoration(border: Border(top: BorderSide(color: C.border))),
      child: Wrap(alignment: WrapAlignment.spaceBetween, crossAxisAlignment: WrapCrossAlignment.center, runSpacing: 8, spacing: 8, children: [
        Text(info, style: T.small),
        if (p.pages > 1)
          Row(mainAxisSize: MainAxisSize.min, children: [
            _pageBtn(icon: FA.chevronLeft, onTap: p.page > 1 ? () => p.goPage(p.page - 1) : null),
            for (final n in p.window())
              n == 0 ? Padding(padding: const EdgeInsets.symmetric(horizontal: 4), child: Text('…', style: T.small)) : _pageBtn(text: '$n', active: n == p.page, onTap: n == p.page ? null : () => p.goPage(n)),
            _pageBtn(icon: FA.chevronRight, onTap: p.page < p.pages ? () => p.goPage(p.page + 1) : null),
          ]),
      ]),
    );
  }

  Widget _pageBtn({String? text, IconData? icon, bool active = false, VoidCallback? onTap}) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 2),
      child: Material(
        color: active ? C.navy700 : Colors.white,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8), side: BorderSide(color: active ? C.navy700 : C.border)),
        child: InkWell(
          borderRadius: BorderRadius.circular(8),
          onTap: onTap,
          child: SizedBox(
            width: 34,
            height: 34,
            child: Center(
              child: icon != null
                  ? Icon(icon, size: 11, color: onTap == null ? C.borderStrong : C.slate600)
                  : Text(text ?? '', style: T.s(13, weight: FontWeight.w600, color: active ? Colors.white : C.text)),
            ),
          ),
        ),
      ),
    );
  }
}

/// A list "table" on a phone: one card per row with label / value fields and action buttons.
class RecordCard extends StatelessWidget {
  final Widget title;
  final Widget? subtitle;
  final Widget? trailing;
  final Widget? leading;
  final List<(String, Widget)> fields;
  final List<Widget> actions;
  final String? tone; // success | danger (row-success / row-danger)
  final VoidCallback? onTap;
  const RecordCard({super.key, required this.title, this.subtitle, this.trailing, this.leading, this.fields = const [], this.actions = const [], this.tone, this.onTap});

  @override
  Widget build(BuildContext context) {
    final Color? tint = tone == 'success'
        ? const Color(0xFFF3FBF6)
        : tone == 'danger'
            ? const Color(0xFFFEF5F5)
            : null;
    final Color edge = tone == 'success'
        ? C.green600
        : tone == 'danger'
            ? C.red600
            : Colors.transparent;
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 10, 12, 0),
      decoration: BoxDecoration(
        color: tint ?? Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: C.border),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(11),
        child: Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            child: Container(
              decoration: BoxDecoration(border: Border(left: BorderSide(color: edge, width: 3))),
              padding: const EdgeInsets.fromLTRB(12, 12, 12, 12),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (leading != null) Padding(padding: const EdgeInsets.only(right: 10), child: leading!),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Expanded(child: title),
                      if (trailing != null) Padding(padding: const EdgeInsets.only(left: 8), child: trailing!),
                    ]),
                    if (subtitle != null) Padding(padding: const EdgeInsets.only(top: 3), child: subtitle!),
                    if (fields.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 10),
                        child: LayoutBuilder(builder: (context, c) {
                          final w = (c.maxWidth - 12) / 2;
                          return Wrap(spacing: 12, runSpacing: 10, children: [
                            for (final f in fields)
                              SizedBox(
                                width: w,
                                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                                  Text(f.$1.toUpperCase(), style: T.s(10.5, weight: FontWeight.w600, color: C.muted, spacing: .3)),
                                  const SizedBox(height: 3),
                                  f.$2,
                                ]),
                              ),
                          ]);
                        }),
                      ),
                    if (actions.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Wrap(spacing: 6, runSpacing: 6, children: actions)),
                  ]),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// A list card: header, toolbar, the rows (or an empty state) and the pagination bar.
class ListCard extends StatelessWidget {
  final Widget? toolbar;
  final List<Widget> rows;
  final Widget empty;
  final PageResult? pager;
  final Widget? header;
  const ListCard({super.key, this.toolbar, required this.rows, required this.empty, this.pager, this.header});

  @override
  Widget build(BuildContext context) {
    return AppCard(
      padding: EdgeInsets.zero,
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (toolbar != null) toolbar!,
        if (header != null) header!,
        if (rows.isEmpty) empty else ...rows,
        if (rows.isNotEmpty) const SizedBox(height: 12),
        if (pager != null) PaginationBar(pager!),
      ]),
    );
  }
}

/// empty_state(): different text when filters are active.
Widget listEmpty(bool filtered, String message) => EmptyState(
      icon: filtered ? FA.magnifyingGlass : FA.folderOpen,
      title: filtered ? 'No matching records found' : message,
      text: filtered ? 'Try a different search term or reset the filters.' : 'Records you add will appear here.',
    );

/// Text with a small muted line under it (.cell-sub).
Widget cellSub(Object? main, Object? sub, {bool strong = false, bool mono = false}) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      val(main, strong: strong, mono: mono),
      if (sub != null && str(sub).isNotEmpty) Text(str(sub), style: T.s(12, color: C.muted, height: 1.3)),
    ]);
