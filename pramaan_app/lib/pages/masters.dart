// PRAMAN — Super Admin masters (js/pages/masters.js). One generic engine gives every simple master
// (zones, wards, assemblies, plot types, statuses) the same list / search / filter / add / edit /
// activate / delete screens. Sector Master is custom.
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/lookups.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../ui/common.dart';
import '../ui/forms.dart';
import '../ui/lists.dart';

const String kMastersBase = '/admin/masters';

const Map<String, String> kColorOpts = {'info': 'Blue', 'warning': 'Amber', 'success': 'Green', 'danger': 'Red', 'primary': 'Navy', 'gold': 'Gold', 'dark': 'Dark', 'muted': 'Grey'};
const Map<String, String> kIconOpts = {
  'fa-list': 'List', 'fa-hourglass-half': 'Hourglass', 'fa-circle-check': 'Check', 'fa-circle-xmark': 'Cross', 'fa-gavel': 'Gavel',
  'fa-handshake': 'Handshake', 'fa-pause': 'Pause', 'fa-box-archive': 'Archive', 'fa-flag': 'Flag', 'fa-clock': 'Clock', 'fa-ban': 'Ban', 'fa-star': 'Star',
};

class MField {
  final String name;
  final String label;
  final String rules;
  final String type; // text | number | select
  final bool unique;
  final bool upper;
  final bool lockOnEdit;
  final bool number;
  final bool preview;
  final String? placeholder;
  final String? hint;
  final Map<String, String> Function(Object? cur)? options;
  final Object? Function()? def;
  const MField(this.name, this.label, {this.rules = '', this.type = 'text', this.unique = false, this.upper = false, this.lockOnEdit = false, this.number = false, this.preview = false, this.placeholder, this.hint, this.options, this.def});
}

class MColumn {
  final String key;
  final String label;
  final Object? Function(Rec r) sort;
  final Widget Function(Rec r) cell;
  const MColumn(this.key, this.label, this.sort, this.cell);
}

class MasterDef {
  final String table;
  final String pk;
  final String title;
  final String one;
  final IconData icon;
  final String entity;
  final String sub;
  final String Function(Rec r) name;
  final String sort;
  final List<MField> fields;
  final List<MColumn> columns;
  final (int, String) Function(Rec r) usage;
  final List<Object?> Function(Rec r) search;
  final List<FilterDef> Function(Map<String, String> q)? filters;
  final List<String> filterKeys;
  final List<Rec> Function(List<Rec> rows, Map<String, String> q)? applyFilter;
  final bool Function(Rec r)? locked;
  const MasterDef({
    required this.table,
    required this.pk,
    required this.title,
    required this.one,
    required this.icon,
    required this.entity,
    required this.sub,
    required this.name,
    required this.sort,
    required this.fields,
    required this.columns,
    required this.usage,
    required this.search,
    this.filters,
    this.filterKeys = const [],
    this.applyFilter,
    this.locked,
  });
}

Map<String, String> zoneOptions(Object? cur) {
  final rows = P.db.all('master_zones')..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
  return {
    for (final z in rows)
      if (truthy(z['is_active']) || toInt(z['zone_id']) == toInt(cur)) str(z['zone_id']): '${z['zone_name']}${truthy(z['is_active']) ? '' : ' (inactive)'}',
  };
}

(int, String) _props(int n) => (n, '$n propert${n == 1 ? 'y' : 'ies'}');

Widget _sub(Object? main, Object? sub, {bool strong = true}) => cellSub(main, sub, strong: strong);

final Map<String, MasterDef> kMasters = {
  'zones': MasterDef(
    table: 'master_zones', pk: 'zone_id', title: 'Zone Master', one: 'Zone', icon: FA.mapLocationDot, entity: 'ZONE',
    sub: 'Administrative zones of KDA. Every scheme belongs to one zone; wards are grouped under zones.',
    name: (r) => str(r['zone_name']), sort: 'order',
    fields: [
      const MField('zone_code', 'Zone code', rules: 'required|ucode', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. ZONE-5', hint: 'CAPITAL letters, digits, / _ - . Cannot be changed later.'),
      const MField('zone_name', 'Zone name', rules: 'required|min:2|max:60|text', unique: true, placeholder: 'e.g. Zone 5'),
      const MField('name_hi', 'Name (Hindi)', rules: 'max:60|text', placeholder: 'e.g. ज़ोन 5'),
      const MField('area', 'Area / description', rules: 'max:150|text', placeholder: 'e.g. North Kota'),
      MField('sort_order', 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: () => P.db.count('master_zones') + 1),
    ],
    columns: [
      MColumn('order', '#', (r) => toNum(r['sort_order']), (r) => val(r['sort_order'])),
      MColumn('code', 'Code', (r) => r['zone_code'], (r) => val(r['zone_code'], mono: true)),
      MColumn('name', 'Zone', (r) => r['zone_name'], (r) => _sub(r['zone_name'], r['name_hi'])),
      MColumn('area', 'Area', (r) => r['area'], (r) => val(r['area'])),
    ],
    usage: (r) {
      final s = P.db.count('schemes', {'zone_id': r['zone_id']});
      final w = P.db.count('master_wards', {'zone_id': r['zone_id']});
      return (s + w, '$s scheme(s) · $w ward(s)');
    },
    search: (r) => [r['zone_code'], r['zone_name'], r['name_hi'], r['area']],
  ),
  'wards': MasterDef(
    table: 'master_wards', pk: 'ward_id', title: 'Ward Master', one: 'Ward', icon: FA.locationCrosshairs, entity: 'WARD',
    sub: "Municipal wards. A ward belongs to one zone; a property is tagged with a ward of its scheme's zone.",
    name: (r) => 'Ward ${r['ward_no']} — ${r['ward_name']}', sort: 'no',
    fields: [
      const MField('ward_no', 'Ward no.', type: 'number', rules: 'required|integer|between:1,999', unique: true, placeholder: 'e.g. 17'),
      const MField('ward_name', 'Ward name / locality', rules: 'required|min:2|max:80|text', placeholder: 'e.g. Talwandi'),
      const MField('zone_id', 'Zone', type: 'select', rules: 'required', options: zoneOptions, number: true, hint: 'Only active zones are listed.'),
      const MField('councillor', 'Councillor (optional)', rules: 'max:80|text', placeholder: 'Name of ward councillor'),
    ],
    columns: [
      MColumn('no', 'Ward no.', (r) => toNum(r['ward_no']), (r) => val(r['ward_no'], strong: true)),
      MColumn('name', 'Ward name', (r) => r['ward_name'], (r) => _sub(r['ward_name'], r['councillor'], strong: false)),
      MColumn('zone', 'Zone', (r) => P.db.zoneName(r['zone_id']), (r) => Align(alignment: Alignment.centerLeft, child: Pill(P.db.zoneName(r['zone_id']), tone: Tone.primary))),
    ],
    filters: (q) => [FilterDef('zone_id', 'Zone', zoneOptions(q['zone_id']), 'All zones')],
    filterKeys: const ['zone_id'],
    applyFilter: (rows, q) => (q['zone_id'] ?? '').isEmpty ? rows : rows.where((r) => str(r['zone_id']) == q['zone_id']).toList(),
    usage: (r) => _props(P.db.count('properties', {'ward_id': r['ward_id'], 'is_active': 1})),
    search: (r) => [r['ward_no'], r['ward_name'], r['councillor'], P.db.zoneName(r['zone_id'])],
  ),
  'assemblies': MasterDef(
    table: 'master_assemblies', pk: 'assembly_id', title: 'Assembly Master', one: 'Assembly constituency', icon: FA.landmark, entity: 'ASSEMBLY',
    sub: 'Vidhan Sabha (assembly) constituencies. Every property is tagged with its constituency.',
    name: (r) => '${r['ac_no']} — ${r['ac_name']}', sort: 'no',
    fields: [
      const MField('ac_no', 'AC no.', type: 'number', rules: 'required|integer|between:1,300', unique: true, placeholder: 'e.g. 189'),
      const MField('ac_name', 'Constituency name', rules: 'required|min:2|max:80|text', unique: true, placeholder: 'e.g. Kota North'),
      const MField('name_hi', 'Name (Hindi)', rules: 'max:80|text', placeholder: 'e.g. कोटा उत्तर'),
      MField('district', 'District', rules: 'required|min:2|max:60|text', def: () => 'Kota'),
      const MField('mla_name', 'MLA (optional)', rules: 'max:80|text'),
    ],
    columns: [
      MColumn('no', 'AC no.', (r) => toNum(r['ac_no']), (r) => val(r['ac_no'], strong: true)),
      MColumn('name', 'Constituency', (r) => r['ac_name'], (r) => _sub(r['ac_name'], r['name_hi'])),
      MColumn('district', 'District', (r) => r['district'], (r) => _sub(r['district'], r['mla_name'] != null ? 'MLA: ${r['mla_name']}' : null, strong: false)),
    ],
    usage: (r) => _props(P.db.count('properties', {'assembly_id': r['assembly_id'], 'is_active': 1})),
    search: (r) => [r['ac_no'], r['ac_name'], r['name_hi'], r['district'], r['mla_name']],
  ),
  'plot-types': MasterDef(
    table: 'master_plot_types', pk: 'plot_type_id', title: 'Plot Type Master', one: 'Plot type', icon: FA.shapes, entity: 'PLOT_TYPE',
    sub: 'Types of property / plot. Inactive types are hidden from new property entry but kept on existing records.',
    name: (r) => str(r['name']), sort: 'order',
    fields: [
      const MField('code', 'Code', rules: 'required|code', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. GODOWN', hint: 'CAPITAL letters, digits, underscore. Stored on properties — cannot be changed later.'),
      const MField('name', 'Name', rules: 'required|min:2|max:60|text', unique: true, placeholder: 'e.g. Godown'),
      const MField('name_hi', 'Name (Hindi)', rules: 'max:60|text'),
      const MField('description', 'Description', rules: 'max:150|text'),
      MField('sort_order', 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: () => P.db.count('master_plot_types') + 1),
    ],
    columns: [
      MColumn('order', '#', (r) => toNum(r['sort_order']), (r) => val(r['sort_order'])),
      MColumn('code', 'Code', (r) => r['code'], (r) => val(r['code'], mono: true)),
      MColumn('name', 'Plot type', (r) => r['name'], (r) => _sub(r['name'], r['name_hi'])),
      MColumn('desc', 'Description', (r) => r['description'], (r) => val(r['description'])),
    ],
    usage: (r) => _props(P.db.count('properties', {'property_type': r['code'], 'is_active': 1})),
    search: (r) => [r['code'], r['name'], r['name_hi'], r['description']],
  ),
  'statuses': MasterDef(
    table: 'master_statuses', pk: 'status_id', title: 'Status Master', one: 'Property status', icon: FA.flag, entity: 'STATUS',
    sub: 'Property status names, colours and icons. System statuses drive the verification workflow — their code cannot change and they cannot be removed.',
    name: (r) => str(r['name']), sort: 'order',
    fields: [
      const MField('code', 'Status code', rules: 'required|code', unique: true, upper: true, lockOnEdit: true, placeholder: 'e.g. ON_HOLD', hint: 'CAPITAL letters, digits, underscore. Cannot be changed later.'),
      const MField('name', 'Display name', rules: 'required|min:2|max:40|text', unique: true, placeholder: 'e.g. On Hold'),
      const MField('name_hi', 'Name (Hindi)', rules: 'max:40|text'),
      MField('color', 'Badge colour', type: 'select', rules: 'required', options: (_) => kColorOpts, def: () => 'info', preview: true),
      MField('icon', 'Icon', type: 'select', rules: 'required', options: (_) => kIconOpts, def: () => 'fa-flag', preview: true),
      const MField('description', 'Meaning', rules: 'max:150|text'),
      MField('sort_order', 'Display order', type: 'number', rules: 'required|integer|between:0,999', def: () => P.db.count('master_statuses') + 1),
    ],
    columns: [
      MColumn('order', '#', (r) => toNum(r['sort_order']), (r) => val(r['sort_order'])),
      MColumn('code', 'Code', (r) => r['code'], (r) => cellSub(r['code'], truthy(r['is_system']) ? 'system' : 'custom', mono: true)),
      MColumn('name', 'Badge', (r) => r['name'], (r) => Align(alignment: Alignment.centerLeft, child: statusPill(r['code']))),
      MColumn('desc', 'Meaning', (r) => r['description'], (r) => val(r['description'])),
    ],
    locked: (r) => truthy(r['is_system']),
    usage: (r) => _props(P.db.count('properties', {'current_status': r['code'], 'is_active': 1})),
    search: (r) => [r['code'], r['name'], r['name_hi'], r['description']],
  ),
};

Widget masterCrumbs(String title, [String? last]) {
  String? listLoc;
  if (last != null) {
    for (final e in kMasters.entries) {
      if (e.value.title == title) listLoc = '$kMastersBase/${e.key}';
    }
    if (title == 'Sector Master') listLoc = '$kMastersBase/sectors';
    if (title == 'Scheme Master') listLoc = '$kMastersBase/schemes';
  }
  return Crumbs([
    ('Home', '/admin/dashboard'),
    if (last != null) (title, listLoc) else (title, null),
    if (last != null) (last, null),
  ]);
}

class _MiniStats extends StatelessWidget {
  final int total;
  final int active;
  const _MiniStats(this.total, this.active);
  @override
  Widget build(BuildContext context) {
    Widget item(IconData i, String n, String t, Color c) => Container(
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 6),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(999), border: Border.all(color: C.border)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(i, size: 11, color: c),
            const SizedBox(width: 6),
            Text(n, style: T.s(13, weight: FontWeight.w700, color: c)),
            Text(' $t', style: T.s(12.5, color: C.slate600)),
          ]),
        );
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Wrap(spacing: 8, runSpacing: 8, children: [
        item(FA.database, '$total', 'total', C.navy700),
        item(FA.circleCheck, '$active', 'active', C.green600),
        item(FA.circlePause, '${total - active}', 'inactive', C.slate600),
      ]),
    );
  }
}

/* ======================= generic list ======================= */

class MasterListPage extends StatelessWidget {
  final RouteCtx ctx;
  final String mkey;
  const MasterListPage(this.ctx, this.mkey, {super.key});

  @override
  Widget build(BuildContext context) {
    final m = kMasters[mkey]!;
    final db = P.db;
    final q = ctx.query;
    var rows = db.all(m.table).map((r) {
      final u = m.usage(r);
      return <String, dynamic>{...r, '_usage_n': u.$1, '_usage_text': u.$2};
    }).toList();
    if (q['status'] == 'active') rows = rows.where((r) => truthy(r['is_active'])).toList();
    if (q['status'] == 'inactive') rows = rows.where((r) => !truthy(r['is_active'])).toList();
    if (m.applyFilter != null) rows = m.applyFilter!(rows, q);
    final sortable = <String, Object? Function(Rec r)>{'usage': (r) => toNum(r['_usage_n'])};
    for (final c in m.columns) {
      sortable[c.key] = c.sort;
    }
    final p = paginate<Rec>(rows, ctx, sort: m.sort, filters: ['status', ...m.filterKeys], search: m.search, sortable: sortable);
    final total = db.count(m.table);
    final active = db.count(m.table, {'is_active': 1});
    return PageBody(children: [
      masterCrumbs(m.title),
      PageHeader(icon: m.icon, title: m.title, subtitle: m.sub, actions: [Btn('Add ${m.one}', icon: FA.plus, onPressed: () => P.go('$kMastersBase/$mkey/create'))]),
      _MiniStats(total, active),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search ${m.one.toLowerCase()}…',
          filters: [...(m.filters == null ? const <FilterDef>[] : m.filters!(q)), const FilterDef('status', 'Status', kActiveOpts, 'All status')],
          sorts: [for (final c in m.columns) (c.key, c.label), ('usage', 'Used in')],
        ),
        rows: [
          for (final r in p.items) _row(m, r),
        ],
        empty: listEmpty(p.hasFilters, 'No records yet'),
        pager: p,
      ),
    ]);
  }

  Widget _row(MasterDef m, Rec r) {
    final id = toInt(r[m.pk]);
    final on = r['is_active'] == 1;
    final locked = m.locked != null && m.locked!(r);
    final n = toInt(r['_usage_n']) ?? 0;
    return RecordCard(
      title: Text(m.name(r), style: T.s(14.5, weight: FontWeight.w700)),
      trailing: activePill(r['is_active']),
      fields: [
        for (final c in m.columns) (c.label, c.cell(r)),
        (
          'Used in',
          Row(children: [
            Pill('$n', tone: n > 0 ? Tone.info : Tone.muted),
            const SizedBox(width: 6),
            Expanded(child: Text(str(r['_usage_text']), style: T.s(12, color: C.muted))),
          ]),
        ),
      ],
      actions: [
        IconBtn(FA.pen, tooltip: 'Edit ${m.name(r)}', onPressed: () => P.go('$kMastersBase/$mkey/$id/edit')),
        if (locked)
          const IconBtn(FA.lock, tooltip: 'System status — always active')
        else
          IconBtn(on ? FA.toggleOff : FA.toggleOn,
              variant: on ? 'light-danger' : 'success',
              tooltip: on ? 'Deactivate' : 'Activate',
              onPressed: () => confirmAction(
                    confirm: on
                        ? 'Deactivate ${m.one.toLowerCase()} "${m.name(r)}"? It will be hidden from new entries; existing records keep it.'
                        : 'Activate ${m.one.toLowerCase()} "${m.name(r)}"?',
                    variant: on ? 'danger' : 'success',
                    action: () => _toggle(m, mkey, r),
                  )),
        if (!locked && n == 0)
          IconBtn(FA.trashCan,
              variant: 'light-danger',
              tooltip: 'Delete',
              onPressed: () => confirmAction(
                    confirm: 'Delete ${m.one.toLowerCase()} "${m.name(r)}" permanently? It is not used anywhere.',
                    variant: 'danger',
                    okText: 'Yes, delete',
                    action: () => _delete(m, r),
                  )),
      ],
    );
  }
}

String? _blockDeactivate(String key, Rec r) {
  final db = P.db;
  if (key == 'zones') {
    final n = db.count('schemes', {'zone_id': r['zone_id'], 'is_active': 1});
    if (n > 0) return 'Zone "${r['zone_name']}" is used by $n active scheme(s). Move or deactivate those schemes first.';
  }
  if (key == 'plot-types' && db.count('master_plot_types', {'is_active': 1}) <= 1) return 'At least one plot type must remain active.';
  if (key == 'assemblies' && db.count('master_assemblies', {'is_active': 1}) <= 1) return 'At least one assembly constituency must remain active.';
  if (key == 'statuses' && truthy(r['is_system'])) return 'System statuses cannot be deactivated.';
  return null;
}

void _toggle(MasterDef m, String key, Rec r) {
  final db = P.db;
  final row = db.find(m.table, r[m.pk])!;
  final active = truthy(row['is_active']) ? 0 : 1;
  if (active == 0) {
    final block = _blockDeactivate(key, row);
    if (block != null) {
      P.toast('error', block);
      return;
    }
  }
  db.update(m.table, row[m.pk], {'is_active': active, 'updated_by': P.uid});
  db.audit(m.entity, row[m.pk], active == 1 ? 'ACTIVATED' : 'DEACTIVATED', {'is_active': 1 - active}, {'is_active': active});
  P.flash('success', '${m.one} "${m.name(row)}" ${active == 1 ? 'activated.' : 'deactivated. It is hidden from new entries.'}');
  P.render();
}

void _delete(MasterDef m, Rec r) {
  final db = P.db;
  final row = db.find(m.table, r[m.pk]);
  if (row == null) {
    P.toast('error', 'Record not found.');
    return;
  }
  if ((m.locked != null && m.locked!(row)) || m.usage(row).$1 > 0) {
    P.toast('error', 'This ${m.one.toLowerCase()} is in use and cannot be deleted. Deactivate it instead.');
    return;
  }
  db.removeWhere(m.table, (x) => toInt(x[m.pk]) == toInt(row[m.pk]));
  db.audit(m.entity, row[m.pk], 'DELETED', {for (final f in m.fields) f.name: row[f.name]}, null);
  P.flash('success', '${m.one} "${m.name(row)}" deleted.');
  P.render();
}

/* ======================= generic form ======================= */

class MasterFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final String mkey;
  final Rec? row;
  const MasterFormPage(this.ctx, this.mkey, this.row, {super.key});
  @override
  State<MasterFormPage> createState() => _MasterFormPageState();
}

class _MasterFormPageState extends State<MasterFormPage> {
  late final FormModel _f;

  MasterDef get m => kMasters[widget.mkey]!;

  @override
  void initState() {
    super.initState();
    final row = widget.row;
    final init = <String, Object?>{};
    for (final f in m.fields) {
      init[f.name] = row != null ? row[f.name] : (f.def != null ? f.def!() : '');
    }
    init['is_active'] = row == null || truthy(row['is_active']) ? '1' : '';
    _f = FormModel(init);
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    await submitForm(_f, action: () {
      final db = P.db;
      final row = widget.row;
      final id = row == null ? null : toInt(row[m.pk]);
      final old = row == null ? null : Map<String, Object?>.of(row);
      final locked = old != null && m.locked != null && m.locked!(old);
      final data = <String, Object?>{};
      for (final f in m.fields) {
        if (id != null && f.lockOnEdit) continue;
        var v = _f.trimmed(f.name);
        if (f.upper) v = v.toUpperCase();
        data[f.name] = (f.type == 'number' || f.number) ? (v.isEmpty ? null : (int.tryParse(v) ?? num.tryParse(v))) : (v.isEmpty ? null : v);
        if (f.unique && v.isNotEmpty && db.any(m.table, (r) => str(r[f.name]).toLowerCase() == str(data[f.name]).toLowerCase() && toInt(r[m.pk]) != id)) {
          _f.fail(f.name, '${f.label} "$v" already exists.');
          return;
        }
      }
      data['is_active'] = locked ? 1 : (_f.isOn('is_active') ? 1 : 0);
      if (id != null && truthy(old!['is_active']) && data['is_active'] == 0) {
        final block = _blockDeactivate(widget.mkey, row!);
        if (block != null) {
          _f.fail(null, block);
          return;
        }
      }
      if (id != null) {
        db.update(m.table, id, {...data, 'updated_by': P.uid});
        db.audit(m.entity, id, 'UPDATED', {for (final k in data.keys) k: old![k]}, data);
        P.flash('success', '${m.one} "${m.name(db.find(m.table, id)!)}" updated successfully.');
      } else {
        final r = db.insert(m.table, {...data, 'created_by': P.uid, 'is_system': 0});
        db.audit(m.entity, r[m.pk], 'CREATED', null, data);
        P.flash('success', '${m.one} "${m.name(r)}" created successfully.');
      }
      P.go('$kMastersBase/${widget.mkey}', null, true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final row = widget.row;
    final isEdit = row != null;
    final locked = isEdit && m.locked != null && m.locked!(row);
    final usage = isEdit ? m.usage(row) : null;
    final hasPreview = m.fields.any((f) => f.preview);
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        masterCrumbs(m.title, isEdit ? 'Edit' : 'Add'),
        PageHeader(
          icon: m.icon,
          title: '${isEdit ? 'Edit' : 'Add'} ${m.one}',
          subtitle: isEdit ? m.name(row) : m.sub,
          actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('$kMastersBase/${widget.mkey}', null, true))],
        ),
        if (usage != null && usage.$1 > 0) AlertBox(type: 'info', text: 'This ${m.one.toLowerCase()} is used in ${usage.$2}. Changes show everywhere it is used.'),
        if (locked) AlertBox(type: 'warning', icon: FA.lock, text: 'System status — the workflow depends on code ${row['code']}. You can change its name, colour, icon and order.'),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            for (final f in m.fields) _field(f, isEdit),
            if (hasPreview)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const FieldLabel('Preview'),
                  Pill(
                    _f.trimmed('name').isEmpty ? 'Status name' : _f.trimmed('name'),
                    tone: Tone.of(_f.value('color').isEmpty ? 'muted' : _f.value('color')),
                    icon: fa(_f.value('icon').isEmpty ? 'fa-flag' : _f.value('icon')),
                  ),
                ]),
              ),
            if (!locked) FCheck(form: _f, name: 'is_active', title: 'Active', subtitle: 'Inactive records are hidden from new entries.'),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              Btn('${isEdit ? 'Update' : 'Create'} ${m.one}', icon: FA.floppyDisk, loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
              Btn('Cancel', icon: FA.xmark, variant: 'light', onPressed: () => P.go('$kMastersBase/${widget.mkey}', null, true)),
            ]),
          ]),
        ),
      ]),
    );
  }

  Widget _field(MField f, bool isEdit) {
    final ro = isEdit && f.lockOnEdit;
    if (f.type == 'select') {
      return FSelect(
        form: _f,
        name: f.name,
        label: f.label,
        options: f.options!(_f.value(f.name)),
        rules: f.rules,
        placeholder: 'Select ${f.label.toLowerCase()}',
        hint: f.hint,
      );
    }
    return FText(
      form: _f,
      name: f.name,
      label: f.label,
      rules: ro ? '' : f.rules,
      requiredMark: f.rules.contains('required'),
      readOnly: ro,
      upper: f.upper,
      placeholder: f.placeholder,
      keyboard: f.type == 'number' ? TextInputType.number : null,
      hint: ro ? 'Cannot be changed after creation.' : f.hint,
    );
  }
}

/* ======================= Sector Master (all schemes) ======================= */

class SectorListPage extends StatelessWidget {
  final RouteCtx ctx;
  const SectorListPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final q = ctx.query;
    var rows = db.all('scheme_sectors').map((s) {
      final sc = db.find('schemes', s['scheme_id']) ?? <String, dynamic>{};
      return <String, dynamic>{
        ...s, 'scheme_name': sc['scheme_name'], 'scheme_code': sc['scheme_code'], 'scheme_active': sc['is_active'],
        'zone': db.zoneName(sc['zone_id']), 'props': db.count('properties', {'sector_id': s['sector_id'], 'is_active': 1}),
      };
    }).toList();
    if (ctx.q('scheme_id').isNotEmpty) rows = rows.where((r) => str(r['scheme_id']) == ctx.q('scheme_id')).toList();
    if (q['status'] == 'active') rows = rows.where((r) => truthy(r['is_active'])).toList();
    if (q['status'] == 'inactive') rows = rows.where((r) => !truthy(r['is_active'])).toList();
    final p = paginate<Rec>(rows, ctx,
        sort: 'scheme',
        filters: const ['scheme_id', 'status'],
        search: (r) => [r['sector_name'], r['scheme_name'], r['scheme_code'], r['zone']],
        sortable: {
          'scheme': (r) => '${r['scheme_name']} ${str(r['sort_order']).padLeft(3, '0')}',
          'name': (r) => r['sector_name'],
          'order': (r) => toNum(r['sort_order']),
          'props': (r) => toNum(r['props']),
        });
    final total = db.count('scheme_sectors');
    final active = db.count('scheme_sectors', {'is_active': 1});
    return PageBody(children: [
      masterCrumbs('Sector Master'),
      PageHeader(
        icon: FA.tableCells,
        title: 'Sector Master',
        subtitle: 'Sectors of all schemes. Properties are registered inside an active sector.',
        actions: [Btn('Add Sector', icon: FA.plus, onPressed: () => P.go('$kMastersBase/sectors/create', ctx.q('scheme_id').isNotEmpty ? {'scheme_id': ctx.q('scheme_id')} : null))],
      ),
      _MiniStats(total, active),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search sector, scheme or zone…',
          filters: [FilterDef('scheme_id', 'Scheme', schemeOptions(), 'All schemes'), const FilterDef('status', 'Status', kActiveOpts, 'All status')],
          sorts: const [('scheme', 'Scheme'), ('name', 'Sector'), ('order', 'Order'), ('props', 'Properties')],
        ),
        rows: [
          for (final r in p.items)
            RecordCard(
              title: Text(str(r['sector_name']), style: T.s(14.5, weight: FontWeight.w700)),
              subtitle: Text('${r['scheme_name']} · ${r['scheme_code']} · ${r['zone']}${truthy(r['scheme_active']) ? '' : ' · scheme inactive'}', style: T.small),
              trailing: activePill(r['is_active']),
              fields: [
                ('Order', val(r['sort_order'])),
                ('Properties', Align(alignment: Alignment.centerLeft, child: Pill('${r['props']}', tone: (toInt(r['props']) ?? 0) > 0 ? Tone.info : Tone.muted))),
              ],
              actions: [
                IconBtn(FA.pen, tooltip: 'Edit sector', onPressed: () => P.go('$kMastersBase/sectors/${r['sector_id']}/edit')),
                _sectorToggle(r),
                if ((toInt(r['props']) ?? 0) == 0)
                  IconBtn(FA.trashCan,
                      variant: 'light-danger',
                      tooltip: 'Delete',
                      onPressed: () => confirmAction(
                            confirm: 'Delete sector "${r['sector_name']}" permanently?',
                            variant: 'danger',
                            okText: 'Yes, delete',
                            action: () {
                              final s = db.find('scheme_sectors', r['sector_id'])!;
                              if (db.count('properties', {'sector_id': s['sector_id']}) > 0) {
                                P.toast('error', 'Sector has properties and cannot be deleted. Deactivate it instead.');
                                return;
                              }
                              if (_lastActiveSector(s)) {
                                P.toast('error', 'This is the last active sector of the scheme and cannot be deleted.');
                                return;
                              }
                              db.removeWhere('scheme_sectors', (x) => x['sector_id'] == s['sector_id']);
                              db.audit('SECTOR', s['sector_id'], 'DELETED', {'sector_name': s['sector_name']}, null);
                              P.flash('success', 'Sector "${s['sector_name']}" deleted.');
                              P.render();
                            },
                          )),
              ],
            ),
        ],
        empty: listEmpty(p.hasFilters, 'No sectors yet'),
        pager: p,
      ),
    ]);
  }

  Widget _sectorToggle(Rec r) {
    final on = r['is_active'] == 1;
    return IconBtn(on ? FA.toggleOff : FA.toggleOn,
        variant: on ? 'light-danger' : 'success',
        tooltip: on ? 'Deactivate' : 'Activate',
        onPressed: () => confirmAction(
              confirm: on ? 'Deactivate sector "${r['sector_name']}"? Existing properties stay; no new property can be added in it.' : 'Activate sector "${r['sector_name']}"?',
              variant: on ? 'danger' : 'success',
              action: () {
                final db = P.db;
                final s = db.find('scheme_sectors', r['sector_id'])!;
                final active = truthy(s['is_active']) ? 0 : 1;
                if (active == 0 && _lastActiveSector(s)) {
                  P.toast('error', 'This is the last active sector of the scheme. A scheme needs at least one active sector.');
                  return;
                }
                db.update('scheme_sectors', s['sector_id'], {'is_active': active, 'updated_by': P.uid});
                db.audit('SECTOR', s['sector_id'], active == 1 ? 'ACTIVATED' : 'DEACTIVATED', {'is_active': 1 - active}, {'is_active': active});
                P.flash('success', 'Sector "${s['sector_name']}" ${active == 1 ? 'activated.' : 'deactivated.'}');
                P.render();
              },
            ));
  }
}

bool _lastActiveSector(Rec r) => truthy(r['is_active']) && P.db.count('scheme_sectors', {'scheme_id': r['scheme_id'], 'is_active': 1}) <= 1;

class SectorFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final Rec? row;
  const SectorFormPage(this.ctx, this.row, {super.key});
  @override
  State<SectorFormPage> createState() => _SectorFormPageState();
}

class _SectorFormPageState extends State<SectorFormPage> {
  late final FormModel _f;

  @override
  void initState() {
    super.initState();
    final r = widget.row;
    _f = FormModel(r == null
        ? {'scheme_id': widget.ctx.q('scheme_id')}
        : {'sector_name': r['sector_name'], 'sort_order': r['sort_order'], 'is_active': truthy(r['is_active']) ? '1' : ''});
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    await submitForm(_f, action: () {
      final db = P.db;
      final row = widget.row;
      if (row != null) {
        final id = toInt(row['sector_id'])!;
        final name = _f.trimmed('sector_name');
        if (db.any('scheme_sectors', (x) => x['scheme_id'] == row['scheme_id'] && toInt(x['sector_id']) != id && str(x['sector_name']).toLowerCase() == name.toLowerCase())) {
          _f.fail('sector_name', 'Sector "$name" already exists in this scheme.');
          return;
        }
        final active = _f.isOn('is_active') ? 1 : 0;
        if (active == 0 && _lastActiveSector(row)) {
          _f.fail(null, 'This is the last active sector of the scheme. A scheme needs at least one active sector.');
          return;
        }
        final order = int.parse(_f.trimmed('sort_order'));
        db.update('scheme_sectors', id, {'sector_name': name, 'sort_order': order, 'is_active': active, 'updated_by': P.uid});
        db.audit('SECTOR', id, 'UPDATED', {'sector_name': row['sector_name'], 'sort_order': row['sort_order'], 'is_active': row['is_active']}, {'sector_name': name, 'sort_order': order, 'is_active': active});
        P.flash('success', 'Sector "$name" updated.');
      } else {
        final sid = toInt(_f.value('scheme_id'))!;
        final names = _f.value('sector_name').split(',').map((x) => x.trim()).where((x) => x.isNotEmpty).toList();
        final seen = <String>{};
        for (final n in names) {
          final k = n.toLowerCase();
          if (n.length > 80) {
            _f.fail('sector_name', 'Sector "${n.substring(0, 30)}…" is longer than 80 characters.');
            return;
          }
          if (seen.contains(k) || db.any('scheme_sectors', (x) => toInt(x['scheme_id']) == sid && str(x['sector_name']).toLowerCase() == k)) {
            _f.fail('sector_name', 'Sector "$n" already exists in this scheme.');
            return;
          }
          seen.add(k);
        }
        var order = db.schemeSectors(sid).fold<int>(0, (m, s) => (toInt(s['sort_order']) ?? 0) > m ? toInt(s['sort_order'])! : m);
        final schemeName = db.find('schemes', sid)?['scheme_name'];
        for (final n in names) {
          order++;
          final s = db.insert('scheme_sectors', {'scheme_id': sid, 'sector_name': n, 'sort_order': order, 'is_active': 1, 'created_by': P.uid});
          db.audit('SECTOR', s['sector_id'], 'CREATED', null, {'scheme': schemeName, 'sector_name': n});
        }
        P.flash('success', '${names.length} sector(s) added to $schemeName.');
      }
      P.go('$kMastersBase/sectors', null, true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final row = widget.row;
    final isEdit = row != null;
    final db = P.db;
    final sc = isEdit ? db.find('schemes', row['scheme_id']) : null;
    final props = isEdit ? db.count('properties', {'sector_id': row['sector_id'], 'is_active': 1}) : 0;
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) => PageBody(children: [
        masterCrumbs('Sector Master', isEdit ? 'Edit' : 'Add'),
        PageHeader(
          icon: FA.tableCells,
          title: isEdit ? 'Edit Sector' : 'Add Sector',
          subtitle: isEdit ? '${row['sector_name']} · ${sc?['scheme_name']}' : 'Add one or more sectors to a scheme. Each name must be unique inside its scheme.',
          actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('$kMastersBase/sectors', null, true))],
        ),
        if (props > 0) AlertBox(type: 'info', text: '$props propert${props == 1 ? 'y is' : 'ies are'} registered in this sector. Renaming shows the new name on them too.'),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            if (isEdit)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const FieldLabel('Scheme', required: true),
                  SelectBox(text: '${sc?['scheme_name']} (${sc?['scheme_code']})', enabled: false, onTap: () {}),
                  const FieldHint('A sector cannot move to another scheme.'),
                ]),
              )
            else
              FSelect(form: _f, name: 'scheme_id', label: 'Scheme', options: schemeOptions(markInactive: true), rules: 'required', placeholder: 'Select scheme'),
            if (isEdit) ...[
              FText(form: _f, name: 'sector_name', label: 'Sector name', rules: 'required|max:80|text', maxLength: 80),
              FText(form: _f, name: 'sort_order', label: 'Display order', rules: 'required|integer|between:0,999', keyboard: TextInputType.number, maxLength: 3),
              FCheck(form: _f, name: 'is_active', title: 'Active', subtitle: 'Inactive sectors cannot receive new properties.'),
            ] else
              FText(form: _f, name: 'sector_name', label: 'Sector name(s)', rules: 'required|max:400|text', placeholder: 'e.g. Sector-5, Sector-6, Block-C', maxLength: 400, hint: 'Separate several sectors with commas.'),
            const SizedBox(height: 8),
            Wrap(spacing: 8, runSpacing: 8, children: [
              Btn(isEdit ? 'Update Sector' : 'Add Sector(s)', icon: FA.floppyDisk, loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
              Btn('Cancel', icon: FA.xmark, variant: 'light', onPressed: () => P.go('$kMastersBase/sectors', null, true)),
            ]),
          ]),
        ),
      ]),
    );
  }
}
