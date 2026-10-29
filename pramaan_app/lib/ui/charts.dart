// PRAMAN — charts drawn with CustomPainter (the web demo uses Chart.js): donut / pie, grouped / stacked /
// horizontal bars and multi-series lines — all animated, with legends and tap-to-filter.
// Also the dashboard cards built on them: section outcomes, status overview, recent activity.
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';

class ChartSeries {
  final String label;
  final List<num> data;
  final Color color;
  final List<Color>? colors; // one colour per category (single-series bars)
  const ChartSeries(this.label, this.data, this.color, {this.colors});
}

/* ======================= helpers ======================= */

TextPainter _tp(String text, double size, Color color, {FontWeight weight = FontWeight.w400, double? maxWidth, TextAlign align = TextAlign.left}) {
  final tp = TextPainter(
    text: TextSpan(text: text, style: TextStyle(fontFamily: T.inter, fontSize: size, color: color, fontWeight: weight)),
    textDirection: TextDirection.ltr,
    textAlign: align,
    maxLines: 1,
    ellipsis: '…',
  );
  tp.layout(maxWidth: maxWidth ?? double.infinity);
  return tp;
}

double _niceStep(double raw) {
  if (raw <= 0) return 1;
  final e = math.pow(10, (math.log(raw) / math.ln10).floor()).toDouble();
  final f = raw / e;
  final double n = f <= 1 ? 1 : (f <= 2 ? 2 : (f <= 5 ? 5 : 10));
  final s = n * e;
  return s < 1 ? 1 : s;
}

String _axisNum(double v) {
  if (v >= 1000000) return '${(v / 1000000).toStringAsFixed(v % 1000000 == 0 ? 0 : 1)}M';
  if (v >= 1000) return '${(v / 1000).toStringAsFixed(v % 1000 == 0 ? 0 : 1)}k';
  return v == v.roundToDouble() ? v.toInt().toString() : v.toStringAsFixed(1);
}

/// Card wrapping a chart (P.chartCard) with an empty state when there is no data.
class ChartCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final String? sub;
  final VoidCallback? onView;
  final bool empty;
  final Widget child;
  const ChartCard({super.key, required this.title, required this.icon, this.sub, this.onView, this.empty = false, required this.child});

  @override
  Widget build(BuildContext context) {
    return FadeSlideIn(
      child: AppCard(
        title: title,
        icon: icon,
        sub: sub,
        trailing: onView == null ? null : LinkText('View', icon: FA.arrowRight, onTap: onView!),
        child: empty
            ? Padding(
                padding: const EdgeInsets.symmetric(vertical: 34),
                child: Column(children: [
                  const Icon(FA.chartPie, size: 26, color: C.borderStrong),
                  const SizedBox(height: 8),
                  Text('No data yet', style: T.small),
                ]),
              )
            : child,
      ),
    );
  }
}

class _Legend extends StatelessWidget {
  final List<(String, Color, String?)> items;
  final ValueChanged<int>? onTap;
  final int? selected;
  const _Legend(this.items, {this.onTap, this.selected});
  @override
  Widget build(BuildContext context) {
    return Wrap(spacing: 12, runSpacing: 8, children: [
      for (var i = 0; i < items.length; i++)
        InkWell(
          onTap: onTap == null ? null : () => onTap!(i),
          borderRadius: BorderRadius.circular(6),
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 2, horizontal: 2),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 9, height: 9, decoration: BoxDecoration(color: items[i].$2, shape: BoxShape.circle)),
              const SizedBox(width: 6),
              Text(items[i].$1, style: T.s(12, weight: selected == i ? FontWeight.w700 : FontWeight.w500, color: C.slate600)),
              if (items[i].$3 != null) Text('  ${items[i].$3}', style: T.s(12, weight: FontWeight.w700, color: C.navy800)),
            ]),
          ),
        ),
    ]);
  }
}

/* ======================= donut / pie ======================= */

class DonutChart extends StatefulWidget {
  final List<String> labels;
  final List<num> values;
  final List<Color> colors;
  final bool doughnut;
  final ValueChanged<int>? onTap;
  final double size;
  const DonutChart({super.key, required this.labels, required this.values, required this.colors, this.doughnut = true, this.onTap, this.size = 180});

  static bool isEmpty(List<num> values) => values.fold<num>(0, (s, v) => s + v) <= 0;

  @override
  State<DonutChart> createState() => _DonutChartState();
}

class _DonutChartState extends State<DonutChart> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..forward();
  int? _sel;

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  Color _color(int i) => widget.colors.isEmpty ? C.series[i % C.series.length] : widget.colors[i % widget.colors.length];

  int? _hit(Offset p, double size) {
    final c = Offset(size / 2, size / 2);
    final d = p - c;
    final r = d.distance;
    final outer = size / 2 - 4;
    final inner = widget.doughnut ? outer * .62 : 0.0;
    if (r > outer + 6 || r < inner - 4) return null;
    var a = math.atan2(d.dy, d.dx) + math.pi / 2;
    if (a < 0) a += 2 * math.pi;
    final total = widget.values.fold<num>(0, (s, v) => s + v).toDouble();
    if (total <= 0) return null;
    var acc = 0.0;
    for (var i = 0; i < widget.values.length; i++) {
      acc += widget.values[i] / total * 2 * math.pi;
      if (a <= acc) return i;
    }
    return null;
  }

  void _pick(int i) {
    setState(() => _sel = i);
    if (widget.onTap != null) widget.onTap!(i);
  }

  @override
  Widget build(BuildContext context) {
    final total = widget.values.fold<num>(0, (s, v) => s + v);
    final size = widget.size;
    final legend = _Legend(
      [for (var i = 0; i < widget.labels.length; i++) (widget.labels[i], _color(i), fmtNum(widget.values[i]))],
      onTap: _pick,
      selected: _sel,
    );
    final chart = GestureDetector(
      onTapUp: (d) {
        final i = _hit(d.localPosition, size);
        if (i != null) _pick(i);
      },
      child: SizedBox(
        width: size,
        height: size,
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) => CustomPaint(
            painter: _DonutPainter(widget.values, [for (var i = 0; i < widget.values.length; i++) _color(i)], Curves.easeOutCubic.transform(_c.value), _sel, widget.doughnut),
            child: widget.doughnut
                ? Center(
                    child: Column(mainAxisSize: MainAxisSize.min, children: [
                      Text(_sel == null ? fmtNum(total) : fmtNum(widget.values[_sel!]), style: T.s(22, weight: FontWeight.w700, color: C.navy800)),
                      Text(
                        _sel == null ? 'total' : '${total > 0 ? (widget.values[_sel!] * 100 / total).round() : 0}%',
                        style: T.s(11.5, color: C.muted),
                      ),
                    ]),
                  )
                : null,
          ),
        ),
      ),
    );
    return LayoutBuilder(builder: (context, c) {
      if (c.maxWidth >= 420) {
        return Row(children: [chart, const SizedBox(width: 20), Expanded(child: legend)]);
      }
      return Column(children: [Center(child: chart), const SizedBox(height: 14), legend]);
    });
  }
}

class _DonutPainter extends CustomPainter {
  final List<num> values;
  final List<Color> colors;
  final double progress;
  final int? selected;
  final bool doughnut;
  _DonutPainter(this.values, this.colors, this.progress, this.selected, this.doughnut);

  @override
  void paint(Canvas canvas, Size size) {
    final total = values.fold<num>(0, (s, v) => s + v).toDouble();
    if (total <= 0) return;
    final c = Offset(size.width / 2, size.height / 2);
    final outer = size.width / 2 - 6;
    final inner = doughnut ? outer * .62 : 0.0;
    var start = -math.pi / 2;
    for (var i = 0; i < values.length; i++) {
      final sweep = values[i] / total * 2 * math.pi * progress;
      if (sweep <= 0) continue;
      final sel = selected == i;
      if (doughnut) {
        final w = outer - inner + (sel ? 6 : 0);
        final r = (outer + inner) / 2 + (sel ? 1.5 : 0);
        final p = Paint()
          ..color = colors[i]
          ..style = PaintingStyle.stroke
          ..strokeWidth = w;
        canvas.drawArc(Rect.fromCircle(center: c, radius: r), start, math.max(0.0, sweep - .025), false, p);
      } else {
        final off = sel ? Offset(math.cos(start + sweep / 2) * 6, math.sin(start + sweep / 2) * 6) : Offset.zero;
        final p = Paint()..color = colors[i];
        canvas.drawArc(Rect.fromCircle(center: c + off, radius: outer), start, sweep, true, p);
        final sep = Paint()
          ..color = Colors.white
          ..style = PaintingStyle.stroke
          ..strokeWidth = 2.5;
        canvas.drawArc(Rect.fromCircle(center: c + off, radius: outer), start, sweep, true, sep);
      }
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutPainter old) => old.progress != progress || old.selected != selected || old.values != values;
}

/* ======================= bars ======================= */

class BarChart extends StatefulWidget {
  final List<String> labels;
  final List<ChartSeries> series;
  final bool stacked;
  final bool horizontal;
  final ValueChanged<int>? onTap;
  final double height;
  const BarChart({super.key, required this.labels, required this.series, this.stacked = false, this.horizontal = false, this.onTap, this.height = 230});

  static bool isEmpty(List<ChartSeries> s) => s.every((d) => d.data.every((v) => v == 0));

  @override
  State<BarChart> createState() => _BarChartState();
}

class _BarChartState extends State<BarChart> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final n = widget.labels.length;
    final h = widget.horizontal ? math.max(widget.height, n * 34.0 + 30) : widget.height;
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      LayoutBuilder(builder: (context, c) {
        final size = Size(c.maxWidth, h);
        return GestureDetector(
          onTapUp: widget.onTap == null
              ? null
              : (d) {
                  final i = _BarPainter.hitIndex(d.localPosition, size, n, widget.horizontal);
                  if (i != null) widget.onTap!(i);
                },
          child: AnimatedBuilder(
            animation: _c,
            builder: (context, _) => CustomPaint(
              size: size,
              painter: _BarPainter(widget.labels, widget.series, widget.stacked, widget.horizontal, Curves.easeOutCubic.transform(_c.value)),
            ),
          ),
        );
      }),
      if (widget.series.length > 1)
        Padding(padding: const EdgeInsets.only(top: 10), child: _Legend([for (final s in widget.series) (s.label, s.color, null)])),
    ]);
  }
}

class _BarPainter extends CustomPainter {
  final List<String> labels;
  final List<ChartSeries> series;
  final bool stacked;
  final bool horizontal;
  final double progress;
  _BarPainter(this.labels, this.series, this.stacked, this.horizontal, this.progress);

  static const double padL = 36, padB = 24, padT = 8, padR = 8, labelW = 92;

  static int? hitIndex(Offset p, Size size, int n, bool horizontal) {
    if (n == 0) return null;
    if (horizontal) {
      final plotH = size.height - padT - padB;
      final i = ((p.dy - padT) / (plotH / n)).floor();
      return i >= 0 && i < n ? i : null;
    }
    final plotW = size.width - padL - padR;
    final i = ((p.dx - padL) / (plotW / n)).floor();
    return i >= 0 && i < n ? i : null;
  }

  double _max() {
    var m = 0.0;
    for (var i = 0; i < labels.length; i++) {
      if (stacked) {
        var s = 0.0;
        for (final d in series) {
          if (i < d.data.length) s += d.data[i].toDouble();
        }
        m = math.max(m, s);
      } else {
        for (final d in series) {
          if (i < d.data.length) m = math.max(m, d.data[i].toDouble());
        }
      }
    }
    return m;
  }

  @override
  void paint(Canvas canvas, Size size) {
    final n = labels.length;
    if (n == 0) return;
    final maxV = _max();
    final step = _niceStep(maxV / 4);
    final ticks = math.max(1, (maxV / step).ceil());
    final axisMax = step * ticks;
    final grid = Paint()
      ..color = const Color(0xFFEEF2F7)
      ..strokeWidth = 1;

    if (!horizontal) {
      final plotW = size.width - padL - padR;
      final plotH = size.height - padT - padB;
      for (var t = 0; t <= ticks; t++) {
        final y = padT + plotH - plotH * t / ticks;
        canvas.drawLine(Offset(padL, y), Offset(size.width - padR, y), grid);
        final tp = _tp(_axisNum(step * t), 10.5, C.muted, maxWidth: padL - 6, align: TextAlign.right);
        tp.paint(canvas, Offset(padL - 6 - tp.width, y - tp.height / 2));
      }
      final band = plotW / n;
      final groups = stacked ? 1 : series.length;
      final barW = math.min(band * .7 / groups, 28.0);
      final groupW = barW * groups;
      for (var i = 0; i < n; i++) {
        final x0 = padL + band * i + (band - groupW) / 2;
        var acc = 0.0;
        for (var s = 0; s < series.length; s++) {
          final d = series[s];
          final v = i < d.data.length ? d.data[i].toDouble() : 0.0;
          if (v <= 0) continue;
          final hgt = plotH * v / axisMax * progress;
          final x = stacked ? x0 : x0 + barW * s;
          final yTop = padT + plotH - (stacked ? (plotH * acc / axisMax * progress) : 0) - hgt;
          final color = d.colors != null && i < d.colors!.length ? d.colors![i] : d.color;
          final r = RRect.fromRectAndCorners(Rect.fromLTWH(x, yTop, barW, hgt), topLeft: const Radius.circular(4), topRight: const Radius.circular(4));
          canvas.drawRRect(r, Paint()..color = color);
          if (stacked) {
            canvas.drawLine(Offset(x, yTop + hgt), Offset(x + barW, yTop + hgt), Paint()
              ..color = Colors.white
              ..strokeWidth = 1.2);
          }
          acc += v;
        }
        final tp = _tp(labels[i], 10.5, C.slate600, maxWidth: band - 2, align: TextAlign.center);
        tp.paint(canvas, Offset(padL + band * i + (band - tp.width) / 2, size.height - padB + 6));
      }
    } else {
      final plotW = size.width - labelW - padR;
      final plotH = size.height - padT - padB;
      for (var t = 0; t <= ticks; t++) {
        final x = labelW + plotW * t / ticks;
        canvas.drawLine(Offset(x, padT), Offset(x, padT + plotH), grid);
        final tp = _tp(_axisNum(step * t), 10.5, C.muted);
        tp.paint(canvas, Offset(x - tp.width / 2, size.height - padB + 6));
      }
      final band = plotH / n;
      final groups = stacked ? 1 : series.length;
      final barH = math.min(band * .7 / groups, 22.0);
      final groupH = barH * groups;
      for (var i = 0; i < n; i++) {
        final y0 = padT + band * i + (band - groupH) / 2;
        var acc = 0.0;
        for (var s = 0; s < series.length; s++) {
          final d = series[s];
          final v = i < d.data.length ? d.data[i].toDouble() : 0.0;
          if (v <= 0) continue;
          final w = plotW * v / axisMax * progress;
          final y = stacked ? y0 : y0 + barH * s;
          final x = labelW + (stacked ? plotW * acc / axisMax * progress : 0);
          final color = d.colors != null && i < d.colors!.length ? d.colors![i] : d.color;
          final r = RRect.fromRectAndCorners(Rect.fromLTWH(x, y, w, barH), topRight: const Radius.circular(4), bottomRight: const Radius.circular(4));
          canvas.drawRRect(r, Paint()..color = color);
          acc += v;
        }
        final tp = _tp(labels[i], 11, C.slate600, maxWidth: labelW - 8);
        tp.paint(canvas, Offset(labelW - 8 - tp.width, padT + band * i + (band - tp.height) / 2));
      }
    }
  }

  @override
  bool shouldRepaint(covariant _BarPainter old) => old.progress != progress || old.series != series || old.labels != labels;
}

/* ======================= lines ======================= */

class LineChart extends StatefulWidget {
  final List<String> labels;
  final List<ChartSeries> series;
  final double height;
  const LineChart({super.key, required this.labels, required this.series, this.height = 220});

  static bool isEmpty(List<ChartSeries> s) => s.every((d) => d.data.every((v) => v == 0));

  @override
  State<LineChart> createState() => _LineChartState();
}

class _LineChartState extends State<LineChart> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      LayoutBuilder(builder: (context, c) {
        final size = Size(c.maxWidth, widget.height);
        return AnimatedBuilder(
          animation: _c,
          builder: (context, _) => CustomPaint(size: size, painter: _LinePainter(widget.labels, widget.series, Curves.easeOutCubic.transform(_c.value))),
        );
      }),
      Padding(padding: const EdgeInsets.only(top: 10), child: _Legend([for (final s in widget.series) (s.label, s.color, null)])),
    ]);
  }
}

class _LinePainter extends CustomPainter {
  final List<String> labels;
  final List<ChartSeries> series;
  final double progress;
  _LinePainter(this.labels, this.series, this.progress);

  @override
  void paint(Canvas canvas, Size size) {
    const padL = 30.0, padR = 10.0, padT = 8.0, padB = 24.0;
    final n = labels.length;
    if (n < 2) return;
    var maxV = 0.0;
    for (final s in series) {
      for (final v in s.data) {
        maxV = math.max(maxV, v.toDouble());
      }
    }
    final step = _niceStep(maxV / 4);
    final ticks = math.max(1, (maxV / step).ceil());
    final axisMax = step * ticks;
    final plotW = size.width - padL - padR;
    final plotH = size.height - padT - padB;
    final grid = Paint()
      ..color = const Color(0xFFEEF2F7)
      ..strokeWidth = 1;
    for (var t = 0; t <= ticks; t++) {
      final y = padT + plotH - plotH * t / ticks;
      canvas.drawLine(Offset(padL, y), Offset(size.width - padR, y), grid);
      final tp = _tp(_axisNum(step * t), 10.5, C.muted);
      tp.paint(canvas, Offset(padL - 6 - tp.width, y - tp.height / 2));
    }
    final dx = plotW / (n - 1);
    final every = plotW / n < 38 ? (n > 10 ? 3 : 2) : 1;
    for (var i = 0; i < n; i++) {
      if (i % every != 0 && i != n - 1) continue;
      final tp = _tp(labels[i], 10, C.slate600);
      final lx = math.max(0.0, math.min(padL + dx * i - tp.width / 2, size.width - tp.width));
      tp.paint(canvas, Offset(lx, size.height - padB + 6));
    }
    canvas.save();
    canvas.clipRect(Rect.fromLTWH(0, 0, padL + plotW * progress + 4, size.height));
    for (final s in series) {
      final pts = <Offset>[];
      for (var i = 0; i < n; i++) {
        final v = i < s.data.length ? s.data[i].toDouble() : 0.0;
        pts.add(Offset(padL + dx * i, padT + plotH - plotH * v / axisMax));
      }
      final line = Path()..moveTo(pts.first.dx, pts.first.dy);
      for (var i = 1; i < pts.length; i++) {
        final p0 = pts[i - 1], p1 = pts[i];
        final cx = (p0.dx + p1.dx) / 2;
        line.cubicTo(cx, p0.dy, cx, p1.dy, p1.dx, p1.dy);
      }
      final fill = Path.from(line)
        ..lineTo(pts.last.dx, padT + plotH)
        ..lineTo(pts.first.dx, padT + plotH)
        ..close();
      canvas.drawPath(fill, Paint()..color = s.color.withAlpha(30));
      canvas.drawPath(line, Paint()
        ..color = s.color
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2);
      for (final p in pts) {
        canvas.drawCircle(p, 2.4, Paint()..color = s.color);
      }
    }
    canvas.restore();
  }

  @override
  bool shouldRepaint(covariant _LinePainter old) => old.progress != progress || old.series != series;
}

/* ======================= day helpers (P.lastDays / P.countByDay) ======================= */

/// Last [n] days as keys 'YYYY-MM-DD' and labels '09 Oct'.
(List<String>, List<String>) lastDays(int n) {
  final keys = <String>[];
  final labels = <String>[];
  for (var i = n - 1; i >= 0; i--) {
    final k = P.db.today(-i);
    keys.add(k);
    final d = parseDate(k)!;
    labels.add('${p2(d.day)} ${kMonths[d.month - 1]}');
  }
  return (keys, labels);
}

List<num> countByDay(List<Rec> rows, String field, List<String> keys, [bool Function(Rec r)? test]) {
  final m = <String, int>{};
  for (final r in rows) {
    if (test != null && !test(r)) continue;
    final s = str(r[field]);
    final k = s.length >= 10 ? s.substring(0, 10) : s;
    m[k] = (m[k] ?? 0) + 1;
  }
  return [for (final k in keys) m[k] ?? 0];
}

/* ======================= dashboard cards ======================= */

/// Section outcomes as summary tiles + one 100 %-stacked bar per section (sectionOutcomeCard).
class SectionOutcomeCard extends StatelessWidget {
  final List<Rec> rows; // {name, approved, pending, overdue, rejected}
  final String title;
  final String? sub;
  const SectionOutcomeCard({super.key, required this.rows, required this.title, this.sub});

  static const List<(String, String, Color)> keys = [
    ('approved', 'Approved', C.chGreen),
    ('pending', 'Pending', C.chAmber),
    ('overdue', 'Overdue', C.chPurple),
    ('rejected', 'Rejected', C.chRed),
  ];

  @override
  Widget build(BuildContext context) {
    int sum(String k) => rows.fold<int>(0, (t, r) => t + (toInt(r[k]) ?? 0));
    return FadeSlideIn(
      child: AppCard(
        title: title,
        icon: FA.listCheck,
        sub: sub,
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          LayoutBuilder(builder: (context, c) {
            final w = (c.maxWidth - 8 * 3) / 4;
            final ww = w < 70 ? (c.maxWidth - 8) / 2 : w;
            return Wrap(spacing: 8, runSpacing: 8, children: [
              for (final k in keys)
                Container(
                  width: ww,
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                  decoration: BoxDecoration(color: const Color(0xFFFAFBFD), borderRadius: BorderRadius.circular(8), border: Border.all(color: C.border)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(fmtNum(sum(k.$1)), style: T.s(19, weight: FontWeight.w700, color: C.text)),
                    Row(children: [
                      Container(width: 8, height: 8, decoration: BoxDecoration(color: k.$3, shape: BoxShape.circle)),
                      const SizedBox(width: 6),
                      Flexible(child: Text(k.$2, overflow: TextOverflow.ellipsis, style: T.s(11.5, color: C.muted))),
                    ]),
                  ]),
                ),
            ]);
          }),
          const SizedBox(height: 16),
          for (final r in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 11),
              child: Row(children: [
                SizedBox(width: 110, child: Text(str(r['name']), maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(12.5, color: const Color(0xFF334155)))),
                const SizedBox(width: 8),
                Expanded(child: SegBar([for (final k in keys) (toInt(r[k.$1]) ?? 0, k.$3)])),
                SizedBox(
                  width: 34,
                  child: Text('${keys.fold<int>(0, (t, k) => t + (toInt(r[k.$1]) ?? 0))}', textAlign: TextAlign.right, style: T.s(12.5, color: C.muted)),
                ),
              ]),
            ),
        ]),
      ),
    );
  }
}

/// Property status distribution as labelled bars (status_overview).
class StatusOverviewCard extends StatelessWidget {
  final Map<String, int> counts;
  final String title;
  const StatusOverviewCard({super.key, required this.counts, this.title = 'Property status'});

  static Color barColor(String s) {
    switch (s) {
      case 'LISTED':
        return C.blue600;
      case 'UNDER_VERIFICATION':
        return C.amber600;
      case 'AUCTION_READY':
        return C.green600;
      case 'NOT_READY':
        return C.red600;
      case 'SENT_FOR_AUCTION':
        return C.navy700;
      case 'SOLD':
        return const Color(0xFF0F172A);
      default:
        return C.slate400;
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = counts.values.fold<int>(0, (s, v) => s + v);
    return FadeSlideIn(
      child: AppCard(
        title: title,
        icon: FA.chartSimple,
        trailing: Pill('${fmtNum(total)} total'),
        child: total == 0
            ? const EmptyState(icon: FA.buildingCircleExclamation, title: 'No properties yet', text: 'Status distribution will appear once properties are registered.', compact: true)
            : Column(children: [
                for (final e in counts.entries)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Row(children: [
                        Expanded(child: Text(label(e.key), style: T.s(13))),
                        Text.rich(TextSpan(children: [
                          TextSpan(text: fmtNum(e.value), style: T.s(13, weight: FontWeight.w700)),
                          TextSpan(text: ' (${(e.value * 1000 / total).round() / 10}%)', style: T.s(11.5, color: C.muted)),
                        ])),
                      ]),
                      const SizedBox(height: 4),
                      ProgressBar(e.value * 100 / total, color: barColor(e.key)),
                    ]),
                  ),
              ]),
      ),
    );
  }
}

const Map<String, IconData> _actIcons = {
  'LOGIN': FA.rightToBracket, 'LOGOUT': FA.rightFromBracket, 'PASSWORD_CHANGED': FA.key, 'ACCOUNT_LOCKED': FA.userLock,
  'ACCESS_DENIED': FA.hand, 'CREATED': FA.plus, 'UPDATED': FA.pen, 'DEACTIVATED': FA.toggleOff, 'ACTIVATED': FA.toggleOn,
};

/// Recent audit rows (P.activity).
class ActivityCard extends StatelessWidget {
  final List<Rec> rows;
  final String title;
  const ActivityCard({super.key, required this.rows, this.title = 'Recent activity'});

  @override
  Widget build(BuildContext context) {
    return FadeSlideIn(
      child: AppCard(
        title: title,
        icon: FA.clockRotateLeft,
        trailing: Text('Latest ${rows.length}', style: T.tiny),
        padding: EdgeInsets.zero,
        child: rows.isEmpty
            ? const EmptyState(icon: FA.inbox, title: 'No activity yet', compact: true)
            : Column(children: [
                for (var i = 0; i < rows.length; i++)
                  Container(
                    padding: const EdgeInsets.fromLTRB(14, 11, 14, 11),
                    decoration: BoxDecoration(border: i == rows.length - 1 ? null : const Border(bottom: BorderSide(color: Color(0xFFEEF2F7)))),
                    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Container(
                        width: 32,
                        height: 32,
                        decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(9)),
                        alignment: Alignment.center,
                        child: Icon(_actIcons[rows[i]['action']] ?? FA.circleDot, size: 13, color: C.navy700),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(label(rows[i]['action']), style: T.s(13.5, weight: FontWeight.w600)),
                          Text('${label(rows[i]['entity_type'])}${rows[i]['entity_id'] != null ? ' #${rows[i]['entity_id']}' : ''}', style: T.s(12.5, color: C.muted)),
                          Text('${rows[i]['performed_by_name'] ?? 'System'} · ${fmtDateTime(rows[i]['performed_at'])} · ${rows[i]['ip_address'] ?? ''}', style: T.tiny),
                        ]),
                      ),
                    ]),
                  ),
              ]),
      ),
    );
  }
}

/// P.recentActivity(limit, userId)
List<Rec> recentActivity([int limit = 8, int? userId]) {
  final rows = userId == null ? P.db.all('audit_logs') : P.db.where('audit_logs', {'performed_by': userId});
  rows.sort((a, b) => (toInt(b['log_id']) ?? 0).compareTo(toInt(a['log_id']) ?? 0));
  return rows.take(limit).toList();
}

/// P.propertyStatusCounts(createdBy) — every status from the Status Master, in order.
Map<String, int> propertyStatusCounts([int? createdBy]) {
  final statuses = P.db.all('master_statuses');
  statuses.sort((a, b) => toNum(a['sort_order']).compareTo(toNum(b['sort_order'])));
  final out = <String, int>{for (final s in statuses) str(s['code']): 0};
  for (final p in P.db.filter('properties', (p) => p['is_active'] == 1 && (createdBy == null || toInt(p['created_by']) == createdBy))) {
    final k = str(p['current_status']);
    out[k] = (out[k] ?? 0) + 1;
  }
  return out;
}
