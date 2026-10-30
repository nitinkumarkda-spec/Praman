// PRAMAN — Property Register of the Data Entry panel (js/pages/dataentry.js) + shared property helpers
// (P.propertyRows / propertyFilters / filterProperties) used by the OIC panel too.
import 'dart:async';

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
import '../ui/timeline.dart';

/* ======================= shared helpers ======================= */

List<Rec> propertyRows() {
  final db = P.db;
  return db.where('properties', {'is_active': 1}).map((p) {
    final sc = db.find('schemes', p['scheme_id']) ?? <String, dynamic>{};
    final se = db.find('scheme_sectors', p['sector_id']) ?? <String, dynamic>{};
    final ru = db.propertyRoleUsers(p['property_id']);
    final cu = db.find('users', p['created_by']);
    final round = p['current_round_id'] != null ? db.find('verification_rounds', p['current_round_id']) : null;
    final tasks = round != null ? db.where('verification_tasks', {'round_id': round['round_id']}) : <Rec>[];
    return <String, dynamic>{
      ...p,
      'scheme_name': sc['scheme_name'], 'scheme_code': sc['scheme_code'], 'sector_name': se['sector_name'],
      'area': db.areaName(sc['area_id']),
      'role_count': ru.map((x) => x['role_id']).toSet().length,
      'role_users': ru.map((x) => '${x['role_name']}: ${x['name']}').join(', '),
      'created_by_name': cu?['name'] ?? '', 'round_no': round?['round_no'],
      'tasks_total': tasks.length, 'tasks_approved': tasks.where((t) => t['task_status'] == 'APPROVED').length,
    };
  }).toList();
}

List<FilterDef> propertyFilters(Map<String, String> q) {
  final sectors = <String, String>{};
  if ((q['scheme_id'] ?? '').isNotEmpty) {
    for (final s in P.db.schemeSectors(q['scheme_id'])) {
      sectors[str(s['sector_id'])] = str(s['sector_name']);
    }
  }
  return [
    FilterDef('scheme_id', 'Scheme', schemeOptions(), 'All schemes', resetKey: 'sector_id'),
    FilterDef('sector_id', 'Sector', sectors, sectors.isEmpty ? 'Select scheme first' : 'All sectors', enabled: sectors.isNotEmpty),
    FilterDef('type', 'Plot type', propertyTypes(), 'All types'),
    FilterDef('status', 'Status', propertyStatuses(), 'All status'),
  ];
}

const List<String> kPropertyFilterKeys = ['scheme_id', 'sector_id', 'type', 'status'];

List<Rec> filterProperties(List<Rec> rows, Map<String, String> q) {
  var r = rows;
  String g(String k) => q[k] ?? '';
  if (g('scheme_id').isNotEmpty) r = r.where((x) => str(x['scheme_id']) == g('scheme_id')).toList();
  if (g('sector_id').isNotEmpty) r = r.where((x) => str(x['sector_id']) == g('sector_id')).toList();
  if (g('type').isNotEmpty) r = r.where((x) => x['property_type'] == g('type')).toList();
  if (g('status').isNotEmpty) r = r.where((x) => x['current_status'] == g('status')).toList();
  return r;
}

final Map<String, Object? Function(Rec r)> kPropertySortable = {
  'code': (r) => r['property_code'],
  'scheme': (r) => r['scheme_name'],
  'sector': (r) => r['sector_name'],
  'plot': (r) => r['plot_no'],
  'type': (r) => r['property_type'],
  'area': (r) => toNum(r['area_sqm']),
  'status': (r) => r['current_status'],
  'created': (r) => toNum(r['property_id']),
};
const List<(String, String)> kPropertySorts = [('created', 'Created'), ('code', 'Code'), ('scheme', 'Scheme'), ('sector', 'Sector'), ('plot', 'Plot no.'), ('type', 'Type'), ('area', 'Area'), ('status', 'Status')];

List<Object?> propertySearch(Rec r) => [r['plot_no'], r['property_code'], r['scheme_name'], r['sector_name']];

Widget areaCell(Rec r) => cellSub(
      '${fmtNum(r['area_value'], 2)} ${unitShort(r['area_unit'])}',
      r['area_unit'] == 'SQFT' ? '= ${fmtNum(r['area_sqm'], 2)} sq m' : null,
    );

String typeName(Object? code) => propertyTypes()[str(code)] ?? str(code);

/// Property details card (DE / OIC detail pages).
Widget propertyDetails(Rec prop) => AppCard(
      title: 'Property details',
      icon: FA.building,
      child: DetailList([
        ('Property code', val(prop['property_code'], mono: true)),
        ('Scheme', val('${prop['scheme_name']} (${prop['scheme_code']})')),
        ('Sector', val(prop['sector_name'])),
        ('Plot no.', val(prop['plot_no'], strong: true)),
        ('Plot type', val(typeName(prop['property_type']))),
        ('Area', val('${fmtNum(prop['area_value'], 2)} ${kAreaUnits[prop['area_unit']] ?? ''}${prop['area_unit'] == 'SQFT' ? ' (= ${fmtNum(prop['area_sqm'], 2)} sq m)' : ''}')),
        ('Created', val('${fmtDateTime(prop['created_at'])} · ${prop['created_by_name']}')),
        if (prop['remarks'] != null && str(prop['remarks']).isNotEmpty) ('Remarks', val(prop['remarks'])),
      ]),
    );

/* ======================= Property Register (list) ======================= */

class PropertyListPage extends StatelessWidget {
  final RouteCtx ctx;
  const PropertyListPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final q = ctx.query;
    final p = paginate<Rec>(filterProperties(propertyRows(), q), ctx, sort: 'created', dir: 'desc', filters: kPropertyFilterKeys, search: propertySearch, sortable: kPropertySortable);
    return PageBody(children: [
      PageHeader(
        icon: FA.building,
        title: 'Property Register',
        subtitleWidget: Text.rich(TextSpan(children: [
          TextSpan(text: 'Vacant properties. Editable only while status is ', style: T.s(13.5, color: C.muted)),
          TextSpan(text: 'Listed', style: T.s(13.5, weight: FontWeight.w700, color: C.muted)),
          TextSpan(text: '.', style: T.s(13.5, color: C.muted)),
        ])),
        actions: [Btn('Add Property', icon: FA.plus, onPressed: () => P.go('/data-entry/properties/create'))],
      ),
      ListCard(
        toolbar: ListToolbar(p: p, placeholder: 'Search plot no., property code, scheme, sector…', filters: propertyFilters(q), sorts: kPropertySorts),
        rows: [
          for (final r in p.items)
            RecordCard(
              onTap: () => P.go('/data-entry/properties/${r['property_id']}'),
              title: Text('Plot ${r['plot_no']}', style: T.s(15, weight: FontWeight.w700)),
              subtitle: Text(str(r['property_code']), style: T.mono.copyWith(fontSize: 12, color: C.blue600)),
              trailing: statusPill(r['current_status']),
              fields: [
                ('Scheme', cellSub(r['scheme_name'], r['scheme_code'])),
                ('Sector', val(r['sector_name'])),
                ('Type', val(typeName(r['property_type']))),
                ('Area', areaCell(r)),
                ('Role users', Align(alignment: Alignment.centerLeft, child: Pill('${r['role_count']} roles', icon: FA.userTie))),
                ('Created', cellSub(fmtDate(r['created_at']), r['created_by_name'])),
              ],
              actions: [
                IconBtn(FA.timeline, tooltip: 'View details & timeline', onPressed: () => P.go('/data-entry/properties/${r['property_id']}')),
                if (r['current_status'] == 'LISTED')
                  IconBtn(FA.pen, tooltip: 'Edit', onPressed: () => P.go('/data-entry/properties/${r['property_id']}/edit'))
                else
                  const IconBtn(FA.lock, tooltip: 'Locked — only Listed properties can be edited'),
              ],
            ),
        ],
        empty: listEmpty(p.hasFilters, 'No properties yet'),
        pager: p,
      ),
    ]);
  }
}

/* ======================= Property detail ======================= */

class PropertyDetailPage extends StatelessWidget {
  final RouteCtx ctx;
  const PropertyDetailPage(this.ctx, {super.key});

  @override
  Widget build(BuildContext context) {
    final rows = propertyRows().where((r) => toInt(r['property_id']) == ctx.id).toList();
    if (rows.isEmpty) return const _NotFound('Property not found.');
    final prop = rows.first;
    final ru = P.db.propertyRoleUsers(prop['property_id']);
    return PageBody(children: [
      PageHeader(
        icon: FA.building,
        title: 'Plot ${prop['plot_no']}',
        titleTrailing: statusPill(prop['current_status']),
        subtitle: '${prop['property_code']} · ${prop['scheme_name']} · ${prop['sector_name']}',
        actions: [
          Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () {
            if (!P.back()) P.go('/data-entry/properties');
          }),
          if (prop['current_status'] == 'LISTED') Btn('Edit', icon: FA.pen, onPressed: () => P.go('/data-entry/properties/${prop['property_id']}/edit')),
        ],
      ),
      propertyDetails(prop),
      AppCard(
        title: 'Assigned role users',
        icon: FA.userTie,
        child: ru.isEmpty
            ? Text('None', style: T.small)
            : Wrap(spacing: 8, runSpacing: 8, children: [for (final x in ru) ChipTag(str(x['name']), strong: '${x['role_name']}:')]),
      ),
      PropertyTimeline(toInt(prop['property_id'])!),
    ]);
  }
}

class _NotFound extends StatelessWidget {
  final String message;
  const _NotFound(this.message);
  @override
  Widget build(BuildContext context) => PageBody(children: [
        AppCard(child: EmptyState(icon: FA.mapLocationDot, title: 'Page not found', text: message, action: Btn('Go to Dashboard', icon: FA.house, onPressed: () => P.go(P.homeOf(P.user()))))),
      ]);
}

/* ======================= Add / Edit property ======================= */

class PropertyFormPage extends StatefulWidget {
  final RouteCtx ctx;
  final Rec? prop;
  const PropertyFormPage(this.ctx, this.prop, {super.key});
  @override
  State<PropertyFormPage> createState() => _PropertyFormPageState();
}

class _PropertyFormPageState extends State<PropertyFormPage> {
  late final FormModel _f;
  bool _loadingSectors = false;
  String _sectorHint = 'Sectors load from the selected scheme.';
  Map<String, String> _sectorOpts = {};
  Timer? _t;

  Rec? get _prop => widget.prop;
  bool get _isEdit => widget.prop != null;

  @override
  void initState() {
    super.initState();
    final p = _prop;
    _f = FormModel(p == null
        ? {'area_unit': 'SQM'}
        : {
            'scheme_id': p['scheme_id'], 'sector_id': p['sector_id'], 'plot_no': p['plot_no'],
            'property_type': p['property_type'], 'area_value': p['area_value'], 'area_unit': p['area_unit'], 'remarks': p['remarks'],
          });
    if (p != null) {
      _sectorOpts = {for (final s in P.db.schemeSectors(p['scheme_id'], activeOnly: true)) str(s['sector_id']): str(s['sector_name'])};
      _sectorHint = '${_sectorOpts.length} sector(s) in this scheme.';
    }
  }

  @override
  void dispose() {
    _t?.cancel();
    _f.dispose();
    super.dispose();
  }

  Rec? get _scheme => P.db.find('schemes', _f.value('scheme_id'));

  /// scheme → sectors (with the short "Loading sectors…" step of the web form)
  void _onScheme(String id) {
    _f.set('sector_id', '');
    _f.set('confirm_location', '');
    _t?.cancel();
    if (id.isEmpty) {
      setState(() {
        _sectorOpts = {};
        _sectorHint = 'Sectors load from the selected scheme.';
      });
      return;
    }
    setState(() {
      _loadingSectors = true;
      _sectorHint = 'Loading sectors…';
    });
    _t = Timer(const Duration(milliseconds: 150), () {
      if (!mounted) return;
      final eligible = P.db.schemeEligible().any((s) => str(s['scheme_id']) == id);
      setState(() {
        _loadingSectors = false;
        if (!eligible) {
          _sectorOpts = {};
          _sectorHint = 'This scheme is not available for property entry (inactive or no sectors).';
        } else {
          _sectorOpts = {for (final s in P.db.schemeSectors(id, activeOnly: true)) str(s['sector_id']): str(s['sector_name'])};
          _sectorHint = '${_sectorOpts.length} sector(s) in this scheme.';
        }
      });
      if (!eligible) P.toast('error', _sectorHint);
    });
  }


  String _areaPreview() {
    final v = double.tryParse(_f.trimmed('area_value'));
    if (v == null || v <= 0) return '1 sq ft = 0.092903 sq m';
    return _f.value('area_unit') == 'SQFT' ? '= ${(v * kSqftToSqm).toStringAsFixed(2)} sq m (stored)' : '= ${(v / kSqftToSqm).toStringAsFixed(2)} sq ft';
  }

  Future<void> _save({bool andNew = false}) async {
    await submitForm(_f, checks: [
      () => _f.value('confirm_location') == 'NO' ? (null, 'You answered "No" — change the scheme / sector, then answer "Yes" to save.') : null,
    ], action: () {
      final db = P.db;
      final prop = _prop;
      final id = prop == null ? null : toInt(prop['property_id']);
      if (_f.value('confirm_location') != 'YES') {
        _f.fail(null, 'Please confirm the scheme and sector (answer "Yes").');
        return;
      }
      final schemeId = id != null ? toInt(prop!['scheme_id'])! : (toInt(_f.value('scheme_id')) ?? 0);
      final sectorId = toInt(_f.value('sector_id')) ?? 0;
      final sector = db.find('scheme_sectors', sectorId);
      if (sector == null || toInt(sector['scheme_id']) != schemeId || !truthy(sector['is_active'])) {
        _f.fail('sector_id', 'Please select an active sector of the selected scheme.');
        return;
      }
      final plot = _f.trimmed('plot_no').toUpperCase();
      if (db.any('properties', (p) => truthy(p['is_active']) && toInt(p['sector_id']) == sectorId && str(p['plot_no']).toLowerCase() == plot.toLowerCase() && toInt(p['property_id']) != id)) {
        _f.fail('plot_no', 'Plot no. $plot already exists in this sector.');
        return;
      }
      final pt = db.first('master_plot_types', {'code': _f.value('property_type')});
      if (pt == null || (!truthy(pt['is_active']) && (prop == null || prop['property_type'] != pt['code']))) {
        _f.fail('property_type', 'Please select an active plot type.');
        return;
      }
      final area = ((double.tryParse(_f.trimmed('area_value')) ?? 0) * 100).round() / 100;
      final unit = _f.value('area_unit');
      final data = <String, Object?>{
        'scheme_id': schemeId, 'sector_id': sectorId, 'plot_no': plot,
        'property_type': _f.value('property_type'), 'area_value': area, 'area_unit': unit, 'area_sqm': toSqm(area, unit),
        'remarks': _f.trimmed('remarks').isEmpty ? null : _f.trimmed('remarks'),
      };
      if (id == null) {
        final missing = db.missingMandatoryRoles(schemeId);
        if (missing.isNotEmpty) {
          _f.fail('scheme_id', "Cannot create property: no active user is mapped in this scheme for mandatory role(s): ${missing.join(', ')}. Update the scheme's role mapping first.");
          return;
        }
        final (p, snap) = db.propertyCreate(data, P.uid);
        db.audit('PROPERTY', p['property_id'], 'CREATED', null, data, p['property_id']);
        P.flash('success', 'Property plot $plot created (status: Listed). $snap role user(s) assigned from the scheme mapping.');
        if (andNew) {
          P.go('/data-entry/properties/create', null, true);
          return;
        }
      } else {
        final live = db.find('properties', id)!;
        if (live['current_status'] != 'LISTED') {
          P.flash('error', 'This property was sent for verification meanwhile and can no longer be edited.');
          P.go('/data-entry/properties', null, true);
          return;
        }
        db.update('properties', id, {...data, 'updated_by': P.uid});
        db.audit('PROPERTY', id, 'UPDATED', {'plot_no': prop!['plot_no'], 'area_value': prop['area_value'], 'sector_id': prop['sector_id']}, data, id);
        P.flash('success', 'Property plot $plot updated.');
      }
      P.go('/data-entry/properties', null, true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final db = P.db;
    final prop = _prop;
    final schemes = _isEdit ? <Rec>[] : db.schemeEligible();
    final sc = _isEdit ? db.find('schemes', prop!['scheme_id']) : null;
    if (!_isEdit && schemes.isEmpty) {
      return PageBody(children: [
        PageHeader(
          icon: FA.buildingCircleCheck,
          title: 'Add Property',
          subtitle: 'Step 1 select scheme → Step 2 select sector → Step 3 confirm → plot details.',
          actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('/data-entry/properties'))],
        ),
        AppCard(
          child: EmptyState(
            icon: FA.mapLocationDot,
            title: 'No scheme is ready for property entry',
            text: 'A scheme appears here only when it is active and has at least one active sector.',
            action: Btn('Create Scheme', icon: FA.plus, onPressed: () => P.go('/data-entry/schemes/create')),
          ),
        ),
      ]);
    }
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) {
        final scheme = _scheme;
        final schemeText = _isEdit ? str(sc?['scheme_name']) : (scheme != null ? '${scheme['scheme_name']} (${scheme['scheme_code']})' : '—');
        final sectorText = _sectorOpts[_f.value('sector_id')] ?? '—';
        final ready = _f.value('sector_id').isNotEmpty && (_isEdit || _f.value('scheme_id').isNotEmpty);
        final yes = _f.value('confirm_location') == 'YES';
        final canSave = yes && ready;
        final roleUsers = _isEdit ? db.propertyRoleUsers(prop!['property_id']) : <Rec>[];
        return PageBody(children: [
          PageHeader(
            icon: FA.buildingCircleCheck,
            title: _isEdit ? 'Edit Property' : 'Add Property',
            subtitle: _isEdit ? '${prop!['property_code']} · ${sc?['scheme_name']} · status Listed' : 'Step 1 select scheme → Step 2 select sector → Step 3 confirm → plot details.',
            actions: [Btn('Back to list', icon: FA.arrowLeft, variant: 'light', onPressed: () => P.go('/data-entry/properties', null, true))],
          ),
          AppCard(
            title: 'Location',
            icon: FA.locationDot,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              if (_isEdit)
                Padding(
                  padding: const EdgeInsets.only(bottom: 14),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                    const FieldLabel('Scheme', required: true, leading: StepNo('1')),
                    SelectBox(text: '${sc?['scheme_name']} (${sc?['scheme_code']})', enabled: false, onTap: () {}),
                    const FieldHint('Scheme cannot be changed after creation (role users were assigned from it).'),
                  ]),
                )
              else
                FSelect(
                  form: _f,
                  name: 'scheme_id',
                  label: 'Scheme',
                  labelLeading: const StepNo('1'),
                  options: {for (final s in schemes) str(s['scheme_id']): '${s['scheme_name']} (${s['scheme_code']})'},
                  rules: 'required',
                  placeholder: 'Select scheme',
                  hint: 'Only schemes with at least one active sector are listed.',
                  onChanged: _onScheme,
                ),
              FSelect(
                form: _f,
                name: 'sector_id',
                label: 'Sector',
                labelLeading: const StepNo('2'),
                options: _sectorOpts,
                rules: 'required',
                enabled: !_loadingSectors && _sectorOpts.isNotEmpty,
                placeholder: _loadingSectors ? 'Loading sectors…' : (_f.value('scheme_id').isEmpty && !_isEdit ? 'Select scheme first' : (_sectorOpts.isEmpty ? 'No active sector' : 'Select sector')),
                hint: _sectorHint,
                onChanged: (_) => _f.set('confirm_location', ''),
              ),
              Container(
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
                decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFC7D6EA))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const StepNo('3'),
                    Expanded(
                      child: Text.rich(TextSpan(children: [
                        TextSpan(text: 'Should this property be created in Scheme ', style: T.s(13.5)),
                        TextSpan(text: '[$schemeText]', style: T.s(13.5, weight: FontWeight.w700, color: C.navy800)),
                        TextSpan(text: ', Sector ', style: T.s(13.5)),
                        TextSpan(text: '[$sectorText]', style: T.s(13.5, weight: FontWeight.w700, color: C.navy800)),
                        TextSpan(text: '?', style: T.s(13.5)),
                      ])),
                    ),
                  ]),
                  const SizedBox(height: 10),
                  FChoice(
                    form: _f,
                    name: 'confirm_location',
                    options: const [('YES', 'Yes, that is correct'), ('NO', 'No, I need to change it')],
                    rules: 'required',
                    ruleLabel: 'the confirmation (Yes / No)',
                    showLabel: false,
                  ),
                  if (_f.value('confirm_location') == 'NO')
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: Text('Please change the scheme / sector above. Save stays disabled until you answer Yes.', style: T.s(12.5, color: C.amber600)),
                    ),
                ]),
              ),
            ]),
          ),
          AppCard(
            title: 'Plot details',
            icon: FA.rulerCombined,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              FText(form: _f, name: 'plot_no', label: 'Plot no.', rules: 'required|plotno', placeholder: 'e.g. 112-A', upper: true, maxLength: 30),
              FSelect(form: _f, name: 'property_type', label: 'Plot type', options: activeMap('master_plot_types', 'code', 'name', _f.value('property_type')), rules: 'required', placeholder: 'Select type'),
              FText(form: _f, name: 'area_value', label: 'Area', rules: 'required|decimal:2|positive', placeholder: 'e.g. 150.50', keyboard: const TextInputType.numberWithOptions(decimal: true), maxLength: 13),
              FSelect(form: _f, name: 'area_unit', label: 'Area unit', options: kAreaUnits, rules: 'required', placeholder: 'Select unit', hint: _areaPreview()),
              FText(form: _f, name: 'remarks', label: 'Remarks', rules: 'max:500|text', placeholder: 'Optional notes', maxLines: 2, maxLength: 500),
            ]),
          ),
          if (roleUsers.isNotEmpty)
            AppCard(
              title: 'Assigned role users (snapshot)',
              icon: FA.userTie,
              child: Wrap(spacing: 8, runSpacing: 8, children: [for (final ru in roleUsers) ChipTag(str(ru['name']), strong: '${ru['role_name']}:')]),
            ),
          Wrap(spacing: 8, runSpacing: 8, children: [
            Btn(_isEdit ? 'Update Property' : 'Save Property', icon: FA.floppyDisk, size: 'lg', loading: _f.submitting, loadingText: 'Saving…', onPressed: canSave ? () => _save() : null),
            if (!_isEdit) Btn('Save & add another', icon: FA.plus, variant: 'gold', size: 'lg', onPressed: canSave && !_f.submitting ? () => _save(andNew: true) : null),
            Btn('Cancel', icon: FA.xmark, variant: 'light', size: 'lg', onPressed: () => P.go('/data-entry/properties', null, true)),
          ]),
          const SizedBox(height: 8),
        ]);
      },
    );
  }
}
