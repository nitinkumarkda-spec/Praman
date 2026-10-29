// PRAMAN — helpers with the same names / output as core.js (fmt_date, fmt_datetime, fmt_num, label …).

/// One demo-database record (same shape as a row object in the web app's localStorage DB).
typedef Rec = Map<String, dynamic>;

/// Loose conversions — demo rows come from JSON, so numbers may be int or double.
int? toInt(Object? v) {
  if (v == null) return null;
  if (v is int) return v;
  if (v is num) return v.toInt();
  return int.tryParse(v.toString().trim());
}

num toNum(Object? v) {
  if (v is num) return v;
  if (v == null) return 0;
  return num.tryParse(v.toString().trim()) ?? 0;
}

String str(Object? v) => v == null ? '' : v.toString();

/// JS truthiness for 0/1 flags and optional values.
bool truthy(Object? v) => v != null && v != 0 && v != false && v != '' && v != 0.0;

String p2(int n) => n.toString().padLeft(2, '0');

/// 'YYYY-MM-DD HH:MM:SS' (local time, MySQL-like) — DB.now() format.
String sqlDateTime(DateTime d) =>
    '${d.year}-${p2(d.month)}-${p2(d.day)} ${p2(d.hour)}:${p2(d.minute)}:${p2(d.second)}';

String sqlDate(DateTime d) => '${d.year}-${p2(d.month)}-${p2(d.day)}';

final RegExp _dateRe = RegExp(r'^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?');

DateTime? parseDate(Object? v) {
  if (v == null) return null;
  final m = _dateRe.firstMatch(v.toString());
  if (m == null) return null;
  return DateTime(int.parse(m.group(1)!), int.parse(m.group(2)!), int.parse(m.group(3)!),
      int.parse(m.group(4) ?? '0'), int.parse(m.group(5) ?? '0'), int.parse(m.group(6) ?? '0'));
}

const List<String> kMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/// 23-10-2026
String fmtDate(Object? v) {
  final d = parseDate(v);
  return d == null ? '—' : '${p2(d.day)}-${p2(d.month)}-${d.year}';
}

/// 23-10-2026 09:05 AM
String fmtDateTime(Object? v) {
  final d = parseDate(v);
  if (d == null) return '—';
  var h = d.hour;
  final ap = h >= 12 ? 'PM' : 'AM';
  h = h % 12 == 0 ? 12 : h % 12;
  return '${fmtDate(v)} ${p2(h)}:${p2(d.minute)} $ap';
}

/// 23 Oct 2026
String fmtDay(Object? v) {
  final d = parseDate(v);
  return d == null ? '—' : '${p2(d.day)} ${kMonths[d.month - 1]} ${d.year}';
}

/// 09:05 AM
String fmtTime(Object? v) {
  final s = fmtDateTime(v);
  return s == '—' ? '—' : s.substring(11);
}

/// 1,234.50 (en-US grouping, fixed decimals) — fmt_num.
String fmtNum(Object? v, [int dec = 0]) {
  final n = toNum(v);
  final neg = n < 0;
  final fixed = n.abs().toStringAsFixed(dec);
  final parts = fixed.split('.');
  final intPart = parts[0];
  final buf = StringBuffer();
  for (var i = 0; i < intPart.length; i++) {
    if (i > 0 && (intPart.length - i) % 3 == 0) buf.write(',');
    buf.write(intPart[i]);
  }
  final out = parts.length > 1 ? '$buf.${parts[1]}' : buf.toString();
  return neg ? '-$out' : out;
}

/// AUCTION_READY → Auction Ready (P.label).
String label(Object? code) {
  if (code == null) return '—';
  final s = code.toString().toLowerCase().replaceAll('_', ' ');
  return s.split(' ').map((w) => w.isEmpty ? w : w[0].toUpperCase() + w.substring(1)).join(' ');
}

String initials(Object? name) {
  final parts = str(name).trim().split(RegExp(r'\s+')).where((p) => p.isNotEmpty).take(2);
  return parts.map((p) => p[0].toUpperCase()).join();
}

/// File size like files.js fmtSize.
String fmtSize(Object? v) {
  final n = toNum(v);
  if (n < 1024) return '${n.toInt()} B';
  if (n < 1048576) return '${(n / 1024).toStringAsFixed(0)} KB';
  return '${(n / 1048576).toStringAsFixed(1)} MB';
}

/// "5 min ago" (notifications.js ago).
String ago(Object? v) {
  final d = parseDate(v);
  if (d == null) return '—';
  var s = DateTime.now().difference(d).inSeconds;
  if (s < 0) s = 0;
  if (s < 45) return 'just now';
  if (s < 3600) return '${(s / 60).round()} min ago';
  if (s < 86400) return '${(s / 3600).round()} hr ago';
  if (s < 172800) return 'Yesterday';
  if (s < 604800) return '${(s / 86400).round()} days ago';
  return fmtDate(v);
}

/// Compare like core.js `by()` (numbers numerically, strings case-insensitive with numeric parts).
int compareLoose(Object? x, Object? y) {
  if (x == null && y == null) return 0;
  if (x == null) return -1;
  if (y == null) return 1;
  if (x is num && y is num) return x.compareTo(y);
  return _natural(x.toString().toLowerCase(), y.toString().toLowerCase());
}

int _natural(String a, String b) {
  final re = RegExp(r'(\d+|\D+)');
  final pa = re.allMatches(a).map((m) => m.group(0)!).toList();
  final pb = re.allMatches(b).map((m) => m.group(0)!).toList();
  for (var i = 0; i < pa.length && i < pb.length; i++) {
    final x = pa[i], y = pb[i];
    final nx = int.tryParse(x), ny = int.tryParse(y);
    int c;
    if (nx != null && ny != null) {
      c = nx.compareTo(ny);
    } else {
      c = x.compareTo(y);
    }
    if (c != 0) return c;
  }
  return pa.length.compareTo(pb.length);
}

/// Sort a list in place by a key function (stable enough for UI lists).
void sortBy<E>(List<E> list, Object? Function(E e) key, {bool desc = false}) {
  list.sort((a, b) => desc ? compareLoose(key(b), key(a)) : compareLoose(key(a), key(b)));
}
