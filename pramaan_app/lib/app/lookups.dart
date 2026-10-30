// PRAMAN — lookups (P.Lookups in core.js): scheme types, area units and the master-table option maps.
import 'format.dart';
import 'store.dart';

const Map<String, String> kSchemeTypes = {'RESIDENTIAL': 'Residential', 'COMMERCIAL': 'Commercial', 'INDUSTRIAL': 'Industrial', 'MIXED': 'Mixed', 'FARMHOUSE': 'Farmhouse'};
const Map<String, String> kAreaUnits = {'SQM': 'Square Meter', 'SQFT': 'Square Feet'};
const Map<String, String> kActiveOpts = {'active': 'Active', 'inactive': 'Inactive'};
const double kSqftToSqm = 0.092903;

/// Area in square metres, rounded to 4 decimals (L.toSqm).
num toSqm(num v, String unit) => ((unit == 'SQFT' ? v * kSqftToSqm : v) * 10000).round() / 10000;

String unitShort(Object? u) => u == 'SQFT' ? 'sq ft' : 'sq m';

/// All rows of a master table → key: name (sorted by sort_order).
Map<String, String> masterMap(String table, String key, String name) {
  final rows = P.db.all(table)..sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
  return {for (final r in rows) str(r[key]): str(r[name])};
}

Map<String, String> areasMap([Object? current]) => activeMap('master_areas', 'area_id', 'area_name', current);
Map<String, String> propertyTypes() => masterMap('master_plot_types', 'code', 'name');
Map<String, String> propertyStatuses() => masterMap('master_statuses', 'code', 'name');

/// Only active rows (+ the current value, so old records still show it) — for entry forms.
Map<String, String> activeMap(String table, String key, String name, [Object? current]) {
  final rows = P.db.all(table)
    ..sort((a, b) {
      final c = toNum(a['sort_order']).compareTo(toNum(b['sort_order']));
      return c != 0 ? c : str(a[name]).compareTo(str(b[name]));
    });
  final m = <String, String>{};
  for (final r in rows) {
    if (truthy(r['is_active']) || str(r[key]) == str(current)) {
      m[str(r[key])] = '${r[name]}${truthy(r['is_active']) ? '' : ' (inactive)'}';
    }
  }
  return m;
}

/// Schemes as "Name (CODE)" sorted by name.
Map<String, String> schemeOptions({bool markInactive = false}) {
  final rows = P.db.all('schemes')..sort((a, b) => str(a['scheme_name']).compareTo(str(b['scheme_name'])));
  return {
    for (final s in rows) str(s['scheme_id']): '${s['scheme_name']} (${s['scheme_code']})${markInactive && !truthy(s['is_active']) ? ' — inactive' : ''}',
  };
}
