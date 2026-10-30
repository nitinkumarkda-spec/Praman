// PRAMAN — Scheme Master (js/pages/dataentry.js): list + create / edit with sectors and role–user mapping.
// Same screens under /data-entry/schemes (Data Entry) and /admin/masters/schemes (Super Admin).
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
import 'masters.dart' show masterCrumbs;

class SchemeListPage extends StatelessWidget {
  final RouteCtx ctx;
  final String base;
  final bool crumbs;
  const SchemeListPage(this.ctx, this.base, {super.key, this.crumbs = false});

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final q = ctx.query;
    var rows = db.all('schemes').map((s) {
      final secs = db.schemeSectors(s['scheme_id']).where((x) => truthy(x['is_active'])).toList();
      final maps = db.where('scheme_role_users', {'scheme_id': s['scheme_id'], 'is_active': 1});
      return <String, dynamic>{
        ...s,
        'area': db.areaName(s['area_id']),
        'sector_count': secs.length,
        'sector_names': secs.map((x) => x['sector_name']).join(', '),
        'mapped_roles': maps.map((m) => m['role_id']).toSet().length,
        'mapped_users': maps.length,
        'property_count': db.count('properties', {'scheme_id': s['scheme_id'], 'is_active': 1}),
      };
    }).toList();
    if (ctx.q('type').isNotEmpty) rows = rows.where((s) => s['scheme_type'] == ctx.q('type')).toList();
    if (q['status'] == 'active') rows = rows.where((s) => truthy(s['is_active'])).toList();
    if (q['status'] == 'inactive') rows = rows.where((s) => !truthy(s['is_active'])).toList();
    final p = paginate<Rec>(rows, ctx,
        sort: 'name',
        filters: const ['type', 'status'],
        search: (s) => [s['scheme_name'], s['scheme_code'], s['area']],
        sortable: {
          'code': (s) => s['scheme_code'],
          'name': (s) => s['scheme_name'],
          'area': (s) => s['area'],
          'type': (s) => s['scheme_type'],
          'year': (s) => toNum(s['year_of_scheme']),
          'sectors': (s) => toNum(s['sector_count']),
          'properties': (s) => toNum(s['property_count']),
        });
    return PageBody(children: [
      if (crumbs) masterCrumbs('Scheme Master'),
      PageHeader(
        icon: FA.map,
        title: 'Scheme Master',
        subtitle: 'Schemes with their sectors and the role users who will verify properties in them.',
        actions: [Btn('Create Scheme', icon: FA.plus, onPressed: () => P.go('$base/create'))],
      ),
      ListCard(
        toolbar: ListToolbar(
          p: p,
          placeholder: 'Search scheme name, code or area…',
          filters: const [FilterDef('type', 'Scheme type', kSchemeTypes, 'All types'), FilterDef('status', 'Status', kActiveOpts, 'All status')],
          sorts: const [('code', 'Code'), ('name', 'Scheme'), ('area', 'Area'), ('type', 'Type'), ('year', 'Year'), ('sectors', 'Sectors'), ('properties', 'Properties')],
        ),
        rows: [
          for (final s in p.items)
            RecordCard(
              title: Text(str(s['scheme_name']), style: T.s(14.5, weight: FontWeight.w700)),
              subtitle: Text(str(s['scheme_code']), style: T.mono.copyWith(fontSize: 12)),
              trailing: activePill(s['is_active']),
              fields: [
                ('Area', val(s['area'])),
                ('Type', val(kSchemeTypes[s['scheme_type']] ?? s['scheme_type'])),
                ('Year', val(s['year_of_scheme'])),
                (
                  'Sectors',
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Pill('${s['sector_count']}', tone: Tone.info),
                    const SizedBox(height: 3),
                    Text(str(s['sector_names']).isEmpty ? '—' : str(s['sector_names']), maxLines: 2, overflow: TextOverflow.ellipsis, style: T.s(12, color: C.muted)),
                  ]),
                ),
                ('Role mapping', cellSub('${s['mapped_roles']} roles', '${s['mapped_users']} users')),
                ('Properties', val(s['property_count'])),
              ],
              actions: [
                IconBtn(FA.pen, tooltip: 'Edit scheme', onPressed: () => P.go('$base/${s['scheme_id']}/edit')),
                _toggle(s),
              ],
            ),
        ],
        empty: listEmpty(p.hasFilters, 'No schemes yet — create the first one'),
        pager: p,
      ),
    ]);
  }

  Widget _toggle(Rec s) {
    final on = s['is_active'] == 1;
    return IconBtn(on ? FA.toggleOff : FA.toggleOn,
        variant: on ? 'light-danger' : 'success',
        tooltip: on ? 'Deactivate' : 'Activate',
        onPressed: () => confirmAction(
              confirm: on ? 'Deactivate scheme "${s['scheme_name']}"? No new property can be added in it.' : 'Activate scheme "${s['scheme_name']}"?',
              variant: on ? 'danger' : 'success',
              action: () {
                final db = P.db;
                final active = on ? 0 : 1;
                db.update('schemes', s['scheme_id'], {'is_active': active, 'updated_by': P.uid});
                db.audit('SCHEME', s['scheme_id'], active == 1 ? 'ACTIVATED' : 'DEACTIVATED', {'is_active': 1 - active}, {'is_active': active});
                P.flash('success', 'Scheme "${s['scheme_name']}" ${active == 1 ? 'activated.' : 'deactivated. No new property can be added in it.'}');
                P.render();
              },
            ));
  }
}

class SchemeFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final String base;
  final Rec? scheme;
  final bool crumbs;
  const SchemeFormPage(this.ctx, this.base, this.scheme, {super.key, this.crumbs = false});
  @override
  State<SchemeFormPage> createState() => _SchemeFormPageState();
}

class _SchemeFormPageState extends State<SchemeFormPage> {
  late final FormModel _f;
  late final List<Rec> _existing; // existing sectors (edit)
  final List<int> _newRows = []; // keys of the "new sector" inputs
  int _nextRow = 0;
  final Map<int, Set<int>> _picked = {}; // role_id => user ids
  final Map<int, String> _mapErr = {};
  late final List<(Rec, List<Rec>)> _options;

  @override
  void initState() {
    super.initState();
    final s = widget.scheme;
    final db = P.db;
    _existing = s == null ? <Rec>[] : db.schemeSectors(s['scheme_id']);
    final init = <String, Object?>{};
    if (s != null) {
      init.addAll({'scheme_name': s['scheme_name'], 'scheme_code': s['scheme_code'], 'scheme_type': s['scheme_type'], 'year_of_scheme': s['year_of_scheme']});
      for (final x in _existing) {
        init['sec_name_${x['sector_id']}'] = x['sector_name'];
        init['sec_active_${x['sector_id']}'] = truthy(x['is_active']) ? '1' : '';
      }
      db.schemeMapping(s['scheme_id']).forEach((rid, uids) => _picked[rid] = uids.toSet());
    } else {
      _newRows.add(_nextRow++);
    }
    _f = FormModel(init);
    _options = db.roleUserOptions();
  }

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  String get _base => widget.base;

  List<String> _newNames() => [for (final k in _newRows) _f.trimmed('sector_new_$k')];

  int _sectorCount() {
    var n = 0;
    for (final x in _existing) {
      if (_f.trimmed('sec_name_${x['sector_id']}').isEmpty) continue;
      if (_f.isOn('sec_active_${x['sector_id']}')) n++;
    }
    return n + _newNames().where((x) => x.isNotEmpty).length;
  }

  (String?, String)? _sectorCheck() {
    final seen = <String>{};
    String? dupField;
    String? dupName;
    void check(String field, String v) {
      final k = v.trim().toLowerCase();
      if (k.isEmpty) return;
      if (seen.contains(k) && dupField == null) {
        dupField = field;
        dupName = v.trim();
      }
      seen.add(k);
    }

    for (final x in _existing) {
      check('sec_name_${x['sector_id']}', _f.value('sec_name_${x['sector_id']}'));
    }
    for (final k in _newRows) {
      check('sector_new_$k', _f.value('sector_new_$k'));
    }
    if (dupField != null) return (dupField, 'Sector name "$dupName" is repeated. Each sector must be unique.');
    if (_sectorCount() < 1) {
      return (_newRows.isNotEmpty ? 'sector_new_${_newRows.first}' : null, 'Add at least one sector. A scheme cannot be saved without sectors.');
    }
    return null;
  }

  (String?, String)? _mappingCheck() {
    _mapErr.clear();
    (String?, String)? first;
    for (final o in _options) {
      final r = o.$1;
      final rid = toInt(r['role_id'])!;
      if (!truthy(r['is_mandatory'])) continue;
      if ((_picked[rid] ?? <int>{}).isNotEmpty) continue;
      final msg = 'Select at least one user for "${r['role_name']}" (mandatory role).';
      _mapErr[rid] = msg;
      first ??= ('map_$rid', msg);
    }
    return first;
  }

  Future<void> _save() async {
    final isEdit = widget.scheme != null;
    await submitForm(
      _f,
      checks: [_sectorCheck, _mappingCheck],
      confirm: isEdit ? 'Save changes to this scheme?' : 'Create this scheme with the sectors and role mapping shown?',
      action: _doSave,
    );
  }

  void _doSave() {
    final db = P.db;
    final scheme = widget.scheme;
    final id = scheme == null ? null : toInt(scheme['scheme_id']);
    final areaId = toInt(_f.value('area_id'));
    final area = db.find('master_areas', areaId);
    if (area == null || (id == null && !truthy(area['is_active']))) {
      _f.fail('area_id', 'Please select an active area.');
      return;
    }
    final data = <String, Object?>{
      'scheme_name': _f.trimmed('scheme_name'),
      'scheme_type': _f.value('scheme_type'),
      'area_id': areaId,
      'year_of_scheme': toInt(_f.trimmed('year_of_scheme')),
    };
    if (db.valueExists('schemes', 'scheme_name', data['scheme_name'], id)) {
      _f.fail('scheme_name', 'A scheme with this name already exists.');
      return;
    }
    if (id == null) {
      data['scheme_code'] = _f.trimmed('scheme_code').toUpperCase();
      if (db.valueExists('schemes', 'scheme_code', data['scheme_code'])) {
        _f.fail('scheme_code', 'This scheme code is already in use.');
        return;
      }
    }
    // sectors
    final existing = <(Rec, String, int)>[for (final x in _existing) (x, _f.trimmed('sec_name_${x['sector_id']}'), _f.isOn('sec_active_${x['sector_id']}') ? 1 : 0)];
    final fresh = _newNames().where((x) => x.isNotEmpty).toList();
    final names = [...existing.map((e) => e.$2), ...fresh];
    final seen = <String>{};
    for (final n in names) {
      if (n.isEmpty) {
        _f.fail(null, 'Sector name is required (max 80 characters).');
        return;
      }
      if (n.contains('<') || n.contains('>')) {
        _f.fail(null, 'Sector "${n.length > 30 ? n.substring(0, 30) : n}": < and > are not allowed.');
        return;
      }
      final k = n.toLowerCase();
      if (seen.contains(k)) {
        _f.fail(null, 'Sector name "$n" is repeated. Each sector name must be unique in a scheme.');
        return;
      }
      seen.add(k);
    }
    final activeCount = existing.where((e) => e.$3 == 1).length + fresh.length;
    if (activeCount < 1) {
      _f.fail(null, 'Add at least one (active) sector. A scheme cannot be saved without sectors.');
      return;
    }
    // mapping
    final map = <int, List<int>>{};
    for (final o in _options) {
      final r = o.$1;
      final rid = toInt(r['role_id'])!;
      final ids = (_picked[rid] ?? <int>{}).toList();
      if (ids.isNotEmpty) {
        map[rid] = ids;
      } else if (truthy(r['is_mandatory'])) {
        _f.fail(null, o.$2.isNotEmpty ? 'Select at least one user for "${r['role_name']}" (mandatory role).' : '"${r['role_name']}" is mandatory but has no active user. Ask the Super Admin to add one.');
        return;
      }
    }
    final by = P.uid;
    int sid;
    if (id == null) {
      final sc = db.insert('schemes', {...data, 'is_active': 1, 'created_by': by});
      sid = toInt(sc['scheme_id'])!;
    } else {
      sid = id;
      db.update('schemes', id, {...data, 'updated_by': by});
      for (final e in existing) {
        db.update('scheme_sectors', e.$1['sector_id'], {'sector_name': e.$2, 'is_active': e.$3, 'updated_by': by});
      }
    }
    var order = db.schemeSectors(sid).fold<int>(0, (m, s) => (toInt(s['sort_order']) ?? 0) > m ? toInt(s['sort_order'])! : m);
    for (final n in fresh) {
      order++;
      db.insert('scheme_sectors', {'scheme_id': sid, 'sector_name': n, 'sort_order': order, 'is_active': 1, 'created_by': by});
    }
    // replace mapping
    for (final m in db.where('scheme_role_users', {'scheme_id': sid, 'is_active': 1})) {
      if (!(map[toInt(m['role_id'])] ?? const <int>[]).contains(toInt(m['user_id']))) {
        db.update('scheme_role_users', m['map_id'], {'is_active': 0});
      }
    }
    map.forEach((rid, uids) {
      for (final uid in uids) {
        if (!db.exists('scheme_role_users', {'scheme_id': sid, 'role_id': rid, 'user_id': uid, 'is_active': 1})) {
          db.insert('scheme_role_users', {'scheme_id': sid, 'role_id': rid, 'user_id': uid, 'is_active': 1, 'created_by': by});
        }
      }
    });
    final mapJson = {for (final e in map.entries) '${e.key}': e.value};
    if (id == null) {
      db.audit('SCHEME', sid, 'CREATED', null, {...data, 'sectors': fresh, 'mapping': mapJson});
      P.flash('success', 'Scheme "${data['scheme_name']}" created with ${fresh.length} sector(s).');
    } else {
      db.audit('SCHEME', sid, 'UPDATED', {'scheme_name': scheme!['scheme_name'], 'area_id': scheme['area_id']}, {...data, 'new_sectors': fresh, 'mapping': mapJson});
      P.flash('success', 'Scheme "${data['scheme_name']}" updated. Changes apply to new properties only.');
    }
    P.go(_base, null, true);
  }

  @override
  Widget build(BuildContext context) {
    final scheme = widget.scheme;
    final isEdit = scheme != null;
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) {
        var all = 0, on = 0;
        for (final o in _options) {
          all += o.$2.length;
          on += o.$2.where((u) => (_picked[toInt(o.$1['role_id'])] ?? <int>{}).contains(toInt(u['user_id']))).length;
        }
        return PageBody(children: [
          if (widget.crumbs) masterCrumbs('Scheme Master', isEdit ? 'Edit' : 'Add'),
          PageHeader(
            icon: FA.map,
            title: isEdit ? 'Edit Scheme' : 'Create Scheme',
            subtitle: isEdit
                ? 'Changes to sectors and role mapping apply to NEW properties only — existing properties keep their snapshot.'
                : 'Fill scheme details, add all its sectors, tick "Done" and map a user for every role.',
            actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go(_base, null, true))],
          ),
          // 1. details
          AppCard(
            titleWidget: Row(children: [const StepNo('1'), Text('Scheme details', style: T.cardTitle)]),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              FText(form: _f, name: 'scheme_name', label: 'Scheme name', rules: 'required|min:3|max:150|text', placeholder: 'e.g. Ravindra Nagar Awasiya Yojana', maxLength: 150),
              FText(
                form: _f,
                name: 'scheme_code',
                label: 'Scheme code',
                rules: isEdit ? '' : 'required|ucode',
                requiredMark: true,
                readOnly: isEdit,
                upper: true,
                placeholder: 'e.g. RNY-2019',
                maxLength: 30,
                hint: isEdit ? 'Code cannot be changed.' : 'CAPITAL letters, digits, / _ - . Cannot be changed later.',
              ),
              FSelect(form: _f, name: 'scheme_type', label: 'Scheme type', options: kSchemeTypes, rules: 'required', placeholder: 'Select type'),
              FSelect(form: _f, name: 'area_id', label: 'Area', options: areasMap(scheme?['area_id']), rules: 'required', placeholder: 'Select area'),
              FText(form: _f, name: 'year_of_scheme', label: 'Year of scheme', rules: 'required|year', placeholder: 'e.g. 2019', keyboard: TextInputType.number, maxLength: 4),
            ]),
          ),
          // 2. sectors
          AppCard(
            titleWidget: Row(children: [
              const StepNo('2'),
              Text('Sectors', style: T.cardTitle),
              Text(' *', style: T.s(14, weight: FontWeight.w700, color: C.red600)),
            ]),
            trailing: Pill('${_sectorCount()} active', tone: Tone.info),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              if (isEdit && _existing.isNotEmpty) ...[
                _formSection(FA.list, 'Existing sectors — rename or deactivate'),
                for (final x in _existing) _existingRow(x),
                Padding(padding: const EdgeInsets.only(bottom: 12), child: Text('Deactivated sectors keep their existing properties but cannot be used for new ones.', style: T.tiny)),
              ],
              _formSection(FA.plus, isEdit ? 'Add new sectors' : 'Add sectors'),
              for (final k in _newRows) _newRow(k),
              Align(
                alignment: Alignment.centerLeft,
                child: Btn('Add another sector', icon: FA.plus, variant: 'outline', size: 'sm', onPressed: () => setState(() => _newRows.add(_nextRow++))),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.fromLTRB(12, 10, 12, 4),
                decoration: BoxDecoration(color: C.gold100, borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFF0DFA6))),
                child: FCheck(
                  form: _f,
                  name: 'sectors_done',
                  title: 'All sectors added — Done *',
                  subtitle: 'Tick after adding all sectors. The scheme is saved only with at least one sector and this box ticked.',
                  rules: 'required',
                  label: 'the “All sectors added — Done” checkbox',
                ),
              ),
            ]),
          ),
          // 3. mapping
          AppCard(
            titleWidget: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [const StepNo('3'), Expanded(child: Text('Role – user mapping', style: T.cardTitle))]),
              Padding(padding: const EdgeInsets.only(top: 4), child: Text('These users will verify every property created in this scheme.', style: T.tiny)),
            ]),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Row(children: [
                Expanded(child: Text('$on of $all users selected', style: T.s(12.5, weight: FontWeight.w600, color: C.navy700))),
                SizedBox(
                  width: 170,
                  child: CheckRow(
                    dense: true,
                    value: all > 0 && on == all,
                    tristate: on > 0 && on < all,
                    title: 'Select all roles',
                    onChanged: (v) => setState(() {
                      for (final o in _options) {
                        final rid = toInt(o.$1['role_id'])!;
                        _picked[rid] = v ? o.$2.map((u) => toInt(u['user_id'])!).toSet() : <int>{};
                      }
                      if (v) _mapErr.clear();
                    }),
                  ),
                ),
              ]),
              const SizedBox(height: 8),
              if (_options.isEmpty) const AlertBox(type: 'warning', text: 'No active roles exist. Ask the Super Admin to create roles.'),
              for (final o in _options) _mappingCard(o.$1, o.$2),
            ]),
          ),
          Wrap(spacing: 8, runSpacing: 8, children: [
            Btn(isEdit ? 'Update Scheme' : 'Create Scheme', icon: FA.floppyDisk, size: 'lg', loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
            Btn('Cancel', icon: FA.xmark, variant: 'light', size: 'lg', onPressed: () => P.go(_base, null, true)),
          ]),
          const SizedBox(height: 8),
        ]);
      },
    );
  }

  Widget _formSection(IconData icon, String text) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Row(children: [
          Icon(icon, size: 12, color: C.gold600),
          const SizedBox(width: 8),
          Text(text, style: T.s(13, weight: FontWeight.w700, color: C.navy800)),
        ]),
      );

  Widget _existingRow(Rec x) {
    final id = x['sector_id'];
    final props = P.db.count('properties', {'sector_id': id, 'is_active': 1});
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Expanded(child: FText(form: _f, name: 'sec_name_$id', label: 'Sector name', showLabel: false, maxLength: 80)),
        const SizedBox(width: 8),
        Padding(
          padding: const EdgeInsets.only(top: 8),
          child: SizedBox(
            width: 92,
            child: CheckRow(value: _f.isOn('sec_active_$id'), dense: true, title: 'Active', onChanged: (v) => _f.set('sec_active_$id', v ? '1' : '')),
          ),
        ),
        Padding(padding: const EdgeInsets.only(top: 12), child: Pill('$props', icon: FA.building)),
      ]),
    );
  }

  Widget _newRow(int k) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Expanded(
        child: FText(
          form: _f,
          name: 'sector_new_$k',
          label: 'New sector name',
          showLabel: false,
          placeholder: 'Sector name, e.g. Sector-4',
          maxLength: 80,
          action: TextInputAction.done,
          onSubmitted: (_) => setState(() => _newRows.add(_nextRow++)),
        ),
      ),
      const SizedBox(width: 8),
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: IconBtn(FA.trashCan,
            variant: 'light-danger',
            size: 44,
            tooltip: 'Remove sector',
            onPressed: _newRows.length == 1 && _existing.isEmpty
                ? () => _f.set('sector_new_$k', '')
                : () => setState(() {
                      _newRows.remove(k);
                      _f.set('sector_new_$k', '', silent: true);
                    })),
      ),
    ]);
  }

  Widget _mappingCard(Rec r, List<Rec> users) {
    final rid = toInt(r['role_id'])!;
    final picked = _picked[rid] ?? <int>{};
    final n = users.where((u) => picked.contains(toInt(u['user_id']))).length;
    final err = _mapErr[rid];
    return Container(
      key: _f.keyOf('map_$rid'),
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
      decoration: BoxDecoration(
        color: n > 0 ? const Color(0xFFF7FAFF) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: err != null ? C.red600 : (n > 0 ? const Color(0xFFC7D6EA) : C.border), width: err != null ? 1.5 : 1),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          const Icon(FA.userTie, size: 13, color: C.navy700),
          const SizedBox(width: 8),
          Expanded(
            child: Text.rich(TextSpan(children: [
              TextSpan(text: str(r['role_name']), style: T.s(13.5, weight: FontWeight.w700, color: C.navy800)),
              if (users.isNotEmpty) TextSpan(text: '  ($n/${users.length})', style: T.s(12, color: C.muted)),
              if (truthy(r['is_mandatory'])) TextSpan(text: '  *', style: T.s(13, weight: FontWeight.w700, color: C.red600)),
            ])),
          ),
          if (users.isNotEmpty)
            SizedBox(
              width: 64,
              child: CheckRow(
                dense: true,
                value: n == users.length,
                tristate: n > 0 && n < users.length,
                title: 'All',
                onChanged: (v) => setState(() {
                  _picked[rid] = v ? users.map((u) => toInt(u['user_id'])!).toSet() : <int>{};
                  if (v) _mapErr.remove(rid);
                }),
              ),
            ),
        ]),
        const SizedBox(height: 4),
        if (users.isEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Row(children: [const Icon(FA.circleInfo, size: 11, color: C.muted), const SizedBox(width: 6), Text('No active user in this role.', style: T.small)]),
          ),
        for (final u in users)
          CheckRow(
            value: picked.contains(toInt(u['user_id'])),
            title: str(u['name']),
            subtitle: '${u['designation']} · ${u['emp_code']}',
            onChanged: (v) => setState(() {
              final set = _picked.putIfAbsent(rid, () => <int>{});
              if (v) {
                set.add(toInt(u['user_id'])!);
                _mapErr.remove(rid);
              } else {
                set.remove(toInt(u['user_id']));
              }
            }),
          ),
        if (err != null) FieldError(err),
      ]),
    );
  }
}
