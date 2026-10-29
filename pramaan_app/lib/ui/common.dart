// PRAMAN — shared widgets (cards, page header, badges, stat cards, buttons, alerts …).
// They mirror the web stylesheet classes (.card, .page-header, .badge-*, .stat-card, .btn-*, .alert-*).
import 'dart:async';

import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/store.dart';
import '../app/theme.dart';

const List<BoxShadow> kShadowSm = [BoxShadow(color: Color(0x0F0F2B52), blurRadius: 2, offset: Offset(0, 1))];
const List<BoxShadow> kShadow = [BoxShadow(color: Color(0x140F2B52), blurRadius: 8, offset: Offset(0, 2))];
const List<BoxShadow> kShadowLg = [BoxShadow(color: Color(0x290F2B52), blurRadius: 32, offset: Offset(0, 12))];

/* ======================= layout ======================= */

/// Scrollable page content: flash messages on top, footer at the bottom.
class PageBody extends StatelessWidget {
  final List<Widget> children;
  final ScrollController? controller;
  const PageBody({super.key, required this.children, this.controller});

  @override
  Widget build(BuildContext context) {
    // SingleChildScrollView (not a lazy ListView) so every form field is built — validation can scroll to any of them.
    return SingleChildScrollView(
      controller: controller,
      padding: const EdgeInsets.fromLTRB(14, 16, 14, 24),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const FlashList(),
        ...children,
        const SizedBox(height: 8),
        const AppFooter(),
      ]),
    );
  }
}

class AppFooter extends StatelessWidget {
  const AppFooter({super.key});
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Column(children: [
        const Divider(color: C.border, height: 20),
        Text('© ${DateTime.now().year} ${App.org} · ${App.name} (App demo)', textAlign: TextAlign.center, style: T.tiny),
        const SizedBox(height: 2),
        Text('Internal use only', textAlign: TextAlign.center, style: T.tiny),
      ]),
    );
  }
}

/// .page-header — gold icon + title, subtitle, action buttons.
class PageHeader extends StatelessWidget {
  final IconData? icon;
  final String title;
  final Widget? titleTrailing;
  final String? subtitle;
  final Widget? subtitleWidget;
  final List<Widget> actions;
  const PageHeader({super.key, this.icon, required this.title, this.titleTrailing, this.subtitle, this.subtitleWidget, this.actions = const []});

  @override
  Widget build(BuildContext context) {
    return FadeSlideIn(
      child: Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 10, runSpacing: 6, children: [
            if (icon != null) Icon(icon, size: 19, color: C.gold500),
            Text(title, style: T.pageTitle),
            if (titleTrailing != null) titleTrailing!,
          ]),
          if (subtitleWidget != null) Padding(padding: const EdgeInsets.only(top: 4), child: subtitleWidget!),
          if (subtitle != null && subtitleWidget == null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(subtitle!, style: T.s(13.5, color: C.muted, height: 1.45))),
          if (actions.isNotEmpty) Padding(padding: const EdgeInsets.only(top: 12), child: Wrap(spacing: 8, runSpacing: 8, children: actions)),
        ]),
      ),
    );
  }
}

/// Breadcrumb line (.ins-crumbs).
class Crumbs extends StatelessWidget {
  final List<(String, String?)> items; // label, location (null = current)
  const Crumbs(this.items, {super.key});
  @override
  Widget build(BuildContext context) {
    final children = <Widget>[];
    for (var i = 0; i < items.length; i++) {
      if (i > 0) children.add(Padding(padding: const EdgeInsets.symmetric(horizontal: 6), child: Text('/', style: T.tiny)));
      final it = items[i];
      children.add(it.$2 == null
          ? Text(it.$1, style: T.s(12, weight: FontWeight.w600, color: C.navy800))
          : InkWell(onTap: () => P.go(it.$2!), child: Text(it.$1, style: T.s(12, color: C.blue600))));
    }
    return Padding(padding: const EdgeInsets.only(bottom: 10), child: Wrap(crossAxisAlignment: WrapCrossAlignment.center, children: children));
  }
}

/// .card with optional header (gold icon + title + trailing).
class AppCard extends StatelessWidget {
  final String? title;
  final IconData? icon;
  final Widget? titleWidget;
  final Widget? trailing;
  final String? sub;
  final Widget? child;
  final EdgeInsetsGeometry padding;
  final Widget? footer;
  final Color? color;
  final Color? borderColor;
  final double marginBottom;
  const AppCard({super.key, this.title, this.icon, this.titleWidget, this.trailing, this.sub, this.child, this.padding = const EdgeInsets.all(16), this.footer, this.color, this.borderColor, this.marginBottom = 16});

  @override
  Widget build(BuildContext context) {
    final hasHeader = title != null || titleWidget != null || trailing != null;
    return Container(
      margin: EdgeInsets.only(bottom: marginBottom),
      decoration: BoxDecoration(
        color: color ?? Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: borderColor ?? C.border),
        boxShadow: kShadowSm,
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(13),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          if (hasHeader)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 12, 12),
              decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: C.border))),
              child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    titleWidget ??
                        Row(children: [
                          if (icon != null) Padding(padding: const EdgeInsets.only(right: 8), child: Icon(icon, size: 15, color: C.gold500)),
                          Expanded(child: Text(title ?? '', style: T.cardTitle)),
                        ]),
                    if (sub != null) Padding(padding: const EdgeInsets.only(top: 2), child: Text(sub!, style: T.tiny)),
                  ]),
                ),
                if (trailing != null) Padding(padding: const EdgeInsets.only(left: 8), child: trailing!),
              ]),
            ),
          if (child != null) Padding(padding: padding, child: child!),
          if (footer != null)
            Container(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
              decoration: const BoxDecoration(color: C.navy50, border: Border(top: BorderSide(color: C.border))),
              child: footer!,
            ),
        ]),
      ),
    );
  }
}

/// Small numbered circle (.step-no).
class StepNo extends StatelessWidget {
  final String n;
  const StepNo(this.n, {super.key});
  @override
  Widget build(BuildContext context) => Container(
        width: 22,
        height: 22,
        margin: const EdgeInsets.only(right: 8),
        decoration: const BoxDecoration(color: C.navy700, shape: BoxShape.circle),
        alignment: Alignment.center,
        child: Text(n, style: T.s(11.5, weight: FontWeight.w700, color: Colors.white)),
      );
}

/* ======================= badges ======================= */

/// .badge — rounded pill with tone colours.
class Pill extends StatelessWidget {
  final String text;
  final Tone tone;
  final IconData? icon;
  final VoidCallback? onTap;
  final double size;
  const Pill(this.text, {super.key, this.tone = Tone.muted, this.icon, this.onTap, this.size = 12});

  @override
  Widget build(BuildContext context) {
    final body = Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 3),
      decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(999), border: Border.all(color: tone.border)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) Padding(padding: const EdgeInsets.only(right: 5), child: Icon(icon, size: size - 2, color: tone.fg)),
        Text(text, style: T.s(size, weight: FontWeight.w600, color: tone.fg)),
      ]),
    );
    if (onTap == null) return body;
    return InkWell(onTap: onTap, borderRadius: BorderRadius.circular(999), child: body);
  }
}

const Map<String, (String, String)> _badges = {
  'LISTED': ('info', 'fa-list'), 'UNDER_VERIFICATION': ('warning', 'fa-hourglass-half'),
  'AUCTION_READY': ('success', 'fa-circle-check'), 'NOT_READY': ('danger', 'fa-circle-xmark'),
  'SENT_FOR_AUCTION': ('primary', 'fa-gavel'), 'SOLD': ('dark', 'fa-handshake'), 'WITHHELD': ('muted', 'fa-pause'),
  'ARCHIVED': ('muted', 'fa-box-archive'), 'OPEN': ('warning', 'fa-folder-open'), 'CLOSED': ('dark', 'fa-lock'),
  'SUPERSEDED': ('muted', 'fa-clock-rotate-left'), 'CANCELLED': ('muted', 'fa-ban'), 'PENDING': ('warning', 'fa-clock'),
  'APPROVED': ('success', 'fa-check'), 'REJECTED': ('danger', 'fa-xmark'), 'NO_RESPONSE': ('danger', 'fa-bell-slash'),
  'NOT_REQUIRED': ('muted', 'fa-minus'), 'OVERDUE': ('danger', 'fa-stopwatch'), 'READY': ('success', 'fa-circle-check'),
  'ACTIVE': ('success', 'fa-circle'), 'INACTIVE': ('muted', 'fa-circle'),
};

/// status_badge(): property statuses come from the Status Master (name / colour / icon).
Widget statusPill(Object? code) {
  final c = str(code);
  final ms = P.db.first('master_statuses', {'code': c});
  if (ms != null) return Pill(str(ms['name']), tone: Tone.of(str(ms['color'])), icon: fa(str(ms['icon'])));
  final b = _badges[c] ?? ('muted', 'fa-circle-info');
  return Pill(label(c), tone: Tone.of(b.$1), icon: fa(b.$2));
}

Widget activePill(Object? v) => statusPill(toInt(v) == 1 ? 'ACTIVE' : 'INACTIVE');

/// .chip
class ChipTag extends StatelessWidget {
  final String? strong;
  final String text;
  const ChipTag(this.text, {super.key, this.strong});
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        decoration: BoxDecoration(color: C.navy50, borderRadius: BorderRadius.circular(999), border: Border.all(color: C.border)),
        child: Text.rich(TextSpan(children: [
          if (strong != null) TextSpan(text: '$strong ', style: T.s(12.5, weight: FontWeight.w600, color: C.navy800)),
          TextSpan(text: text, style: T.s(12.5, color: C.text)),
        ])),
      );
}

/// Small coloured info tag (.ins-mini).
class MiniTag extends StatelessWidget {
  final String text;
  final IconData? icon;
  final String tone; // ok | warn | bad | ''
  const MiniTag(this.text, {super.key, this.icon, this.tone = ''});
  @override
  Widget build(BuildContext context) {
    final t = tone == 'ok'
        ? Tone.success
        : tone == 'warn'
            ? Tone.warning
            : tone == 'bad'
                ? Tone.danger
                : Tone.muted;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
      decoration: BoxDecoration(color: t.bg, borderRadius: BorderRadius.circular(6)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon != null) Padding(padding: const EdgeInsets.only(right: 4), child: Icon(icon, size: 10, color: t.fg)),
        Text(text, style: T.s(11.5, weight: FontWeight.w500, color: t.fg)),
      ]),
    );
  }
}

/* ======================= stat cards ======================= */

(Color, Color) statColors(String color) {
  switch (color) {
    case 'gold':
      return (C.gold600, C.gold100);
    case 'green':
      return (C.green600, C.green100);
    case 'red':
      return (C.red600, C.red100);
    case 'blue':
      return (C.blue600, C.blue100);
    case 'amber':
      return (C.amber600, C.amber100);
    default:
      return (C.navy700, C.navy100);
  }
}

/// .stat-card — accent bar, icon box, big number, label, sub text. Tappable when [onTap] is set.
class StatCard extends StatelessWidget {
  final String color;
  final IconData icon;
  final String value;
  final String label;
  final String? sub;
  final VoidCallback? onTap;
  final bool shake;
  const StatCard({super.key, required this.color, required this.icon, required this.value, required this.label, this.sub, this.onTap, this.shake = false});

  @override
  Widget build(BuildContext context) {
    final cs = statColors(color);
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: C.border), boxShadow: kShadowSm),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(13),
            child: IntrinsicHeight(
              child: Row(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Container(width: 4, color: cs.$1),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(12, 14, 10, 14),
                    child: Row(children: [
                      Container(
                        width: 42,
                        height: 42,
                        decoration: BoxDecoration(color: cs.$2, borderRadius: BorderRadius.circular(11)),
                        alignment: Alignment.center,
                        child: shake ? Wobble(child: Icon(icon, size: 17, color: cs.$1)) : Icon(icon, size: 17, color: cs.$1),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(22, weight: FontWeight.w700, color: C.navy800, height: 1.1)),
                          const SizedBox(height: 2),
                          Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: T.s(12.5, weight: FontWeight.w500, color: C.muted, height: 1.25)),
                          if (sub != null) Text(sub!, maxLines: 2, overflow: TextOverflow.ellipsis, style: T.s(11, color: C.muted, height: 1.3)),
                        ]),
                      ),
                    ]),
                  ),
                ),
              ]),
            ),
          ),
        ),
      ),
    );
  }
}

/// Responsive grid of equal-width tiles (2 columns on phones, 3 on wide screens) with a fade-up stagger.
class TileGrid extends StatelessWidget {
  final List<Widget> children;
  final int phoneColumns;
  final double gap;
  const TileGrid({super.key, required this.children, this.phoneColumns = 2, this.gap = 10});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final cols = c.maxWidth >= 900 ? 3 : (c.maxWidth >= 360 ? phoneColumns : 1);
      final w = (c.maxWidth - gap * (cols - 1)) / cols;
      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Wrap(spacing: gap, runSpacing: gap, children: [
          for (var i = 0; i < children.length; i++) SizedBox(width: w, child: FadeSlideIn(delay: Duration(milliseconds: 50 * i), child: children[i])),
        ]),
      );
    });
  }
}

/* ======================= buttons ======================= */

class _BtnStyle {
  final Color bg;
  final Color fg;
  final Color border;
  const _BtnStyle(this.bg, this.fg, this.border);
}

_BtnStyle _btnStyle(String v) {
  switch (v) {
    case 'gold':
      return const _BtnStyle(C.gold500, C.navy900, C.gold500);
    case 'success':
      return const _BtnStyle(C.green600, Colors.white, C.green600);
    case 'danger':
      return const _BtnStyle(C.red600, Colors.white, C.red600);
    case 'warning':
      return const _BtnStyle(C.amber600, Colors.white, C.amber600);
    case 'light':
      return const _BtnStyle(Colors.white, C.text, C.borderStrong);
    case 'light-danger':
      return const _BtnStyle(Colors.white, C.red600, C.borderStrong);
    case 'outline':
      return const _BtnStyle(Colors.transparent, C.navy700, C.navy700);
    default:
      return const _BtnStyle(C.navy700, Colors.white, C.navy700);
  }
}

/// .btn — variant: primary | light | gold | success | danger | warning | outline | light-danger; size: sm | md | lg.
class Btn extends StatelessWidget {
  final String label;
  final IconData? icon;
  final String variant;
  final String size;
  final VoidCallback? onPressed;
  final bool loading;
  final bool block;
  final String? loadingText;
  const Btn(this.label, {super.key, this.icon, this.variant = 'primary', this.size = 'md', this.onPressed, this.loading = false, this.block = false, this.loadingText});

  @override
  Widget build(BuildContext context) {
    final s = _btnStyle(variant);
    final enabled = onPressed != null && !loading;
    final double fs = size == 'sm' ? 12.5 : (size == 'lg' ? 15 : 13.5);
    final pad = size == 'sm'
        ? const EdgeInsets.symmetric(horizontal: 11, vertical: 7)
        : size == 'lg'
            ? const EdgeInsets.symmetric(horizontal: 20, vertical: 13)
            : const EdgeInsets.symmetric(horizontal: 14, vertical: 10);
    final content = Row(mainAxisSize: block ? MainAxisSize.max : MainAxisSize.min, mainAxisAlignment: MainAxisAlignment.center, children: [
      if (loading)
        Padding(padding: const EdgeInsets.only(right: 8), child: SizedBox(width: fs, height: fs, child: CircularProgressIndicator(strokeWidth: 2, color: s.fg)))
      else if (icon != null)
        Padding(padding: EdgeInsets.only(right: label.isEmpty ? 0 : 7), child: Icon(icon, size: fs, color: s.fg)),
      if (label.isNotEmpty) Text(loading && loadingText != null ? loadingText! : label, style: T.s(fs, weight: FontWeight.w600, color: s.fg)),
    ]);
    return Opacity(
      opacity: enabled || loading ? 1 : .55,
      child: Material(
        color: s.bg,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10), side: BorderSide(color: s.border)),
        child: InkWell(
          onTap: enabled ? onPressed : null,
          borderRadius: BorderRadius.circular(10),
          child: Padding(padding: pad, child: content),
        ),
      ),
    );
  }
}

/// Square icon-only button (.btn-icon-only).
class IconBtn extends StatelessWidget {
  final IconData icon;
  final String variant;
  final String? tooltip;
  final VoidCallback? onPressed;
  final double size;
  const IconBtn(this.icon, {super.key, this.variant = 'light', this.tooltip, this.onPressed, this.size = 36});

  @override
  Widget build(BuildContext context) {
    final s = _btnStyle(variant);
    final btn = Opacity(
      opacity: onPressed == null ? .5 : 1,
      child: Material(
        color: s.bg,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9), side: BorderSide(color: s.border)),
        child: InkWell(
          onTap: onPressed,
          borderRadius: BorderRadius.circular(9),
          child: SizedBox(width: size, height: size, child: Icon(icon, size: size * .4, color: s.fg)),
        ),
      ),
    );
    return tooltip == null ? btn : Tooltip(message: tooltip!, child: btn);
  }
}

/// Plain text link (.small links "View all →").
class LinkText extends StatelessWidget {
  final String text;
  final VoidCallback onTap;
  final IconData? icon;
  const LinkText(this.text, {super.key, required this.onTap, this.icon});
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4, horizontal: 2),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Text(text, style: T.s(12.5, weight: FontWeight.w600, color: C.blue600)),
            if (icon != null) Padding(padding: const EdgeInsets.only(left: 5), child: Icon(icon, size: 11, color: C.blue600)),
          ]),
        ),
      );
}

/* ======================= alerts / empty ======================= */

class AlertBox extends StatelessWidget {
  final String type; // success | error | warning | info
  final String? text;
  final Widget? child;
  final VoidCallback? onClose;
  final IconData? icon;
  const AlertBox({super.key, required this.type, this.text, this.child, this.onClose, this.icon});

  @override
  Widget build(BuildContext context) {
    final tone = Tone.of(type == 'error' ? 'danger' : type);
    final Color fg = type == 'success'
        ? const Color(0xFF14532D)
        : type == 'error'
            ? const Color(0xFF7F1D1D)
            : type == 'warning'
                ? const Color(0xFF78350F)
                : const Color(0xFF1E3A8A);
    final Color border = type == 'success'
        ? const Color(0xFF86EFAC)
        : type == 'error'
            ? const Color(0xFFFCA5A5)
            : type == 'warning'
                ? const Color(0xFFFCD34D)
                : const Color(0xFF93C5FD);
    final IconData ic = icon ??
        (type == 'success'
            ? FA.circleCheck
            : type == 'error'
                ? FA.circleExclamation
                : type == 'warning'
                    ? FA.triangleExclamation
                    : FA.circleInfo);
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
      decoration: BoxDecoration(color: tone.bg, borderRadius: BorderRadius.circular(10), border: Border.all(color: border)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.only(top: 2), child: Icon(ic, size: 15, color: fg)),
        const SizedBox(width: 10),
        Expanded(child: child ?? Text(text ?? '', style: T.s(13.5, weight: FontWeight.w500, color: fg, height: 1.45))),
        if (onClose != null)
          InkWell(onTap: onClose, child: Padding(padding: const EdgeInsets.only(left: 6), child: Icon(FA.xmark, size: 14, color: fg.withAlpha(150)))),
      ]),
    );
  }
}

/// Flash messages of the current page (renderFlash()).
class FlashList extends StatelessWidget {
  const FlashList({super.key});
  @override
  Widget build(BuildContext context) {
    final list = P.flashes;
    if (list.isEmpty) return const SizedBox.shrink();
    return Column(children: [
      for (final f in list) FadeSlideIn(offsetY: -10, child: AlertBox(type: f.type, text: f.message, onClose: () => P.dismissFlash(f))),
    ]);
  }
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? text;
  final Widget? action;
  final bool compact;
  const EmptyState({super.key, this.icon = FA.folderOpen, required this.title, this.text, this.action, this.compact = false});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: compact ? 16 : 28, horizontal: 12),
      child: Column(children: [
        Bounce(child: Icon(icon, size: compact ? 26 : 34, color: C.borderStrong)),
        const SizedBox(height: 10),
        Text(title, textAlign: TextAlign.center, style: T.s(15, weight: FontWeight.w600, color: C.navy800)),
        if (text != null) Padding(padding: const EdgeInsets.only(top: 4), child: Text(text!, textAlign: TextAlign.center, style: T.small)),
        if (action != null) Padding(padding: const EdgeInsets.only(top: 14), child: action!),
      ]),
    );
  }
}

/* ======================= detail list ======================= */

/// .detail-list — label / value rows.
class DetailList extends StatelessWidget {
  final List<(String, Widget)> items;
  const DetailList(this.items, {super.key});

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(builder: (context, c) {
      final wide = c.maxWidth >= 360;
      return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        for (var i = 0; i < items.length; i++)
          Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            decoration: BoxDecoration(border: i == items.length - 1 ? null : const Border(bottom: BorderSide(color: Color(0xFFEEF2F7)))),
            child: wide
                ? Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    SizedBox(width: 128, child: Text(items[i].$1, style: T.s(12.5, weight: FontWeight.w600, color: C.muted))),
                    Expanded(child: items[i].$2),
                  ])
                : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(items[i].$1, style: T.s(12, weight: FontWeight.w600, color: C.muted)),
                    const SizedBox(height: 2),
                    items[i].$2,
                  ]),
          ),
      ]);
    });
  }
}

/// Value text used inside DetailList / record cards.
Widget val(Object? v, {bool strong = false, bool mono = false, Color? color}) => Text(
      v == null || str(v).isEmpty ? '—' : str(v),
      style: mono
          ? T.mono.copyWith(color: color)
          : T.s(13.5, weight: strong ? FontWeight.w600 : FontWeight.w400, color: color ?? C.text, height: 1.4),
    );

/* ======================= bars ======================= */

/// Animated horizontal progress bar (.bar / .bar-fill).
class ProgressBar extends StatelessWidget {
  final double pct; // 0..100
  final Color color;
  final double height;
  const ProgressBar(this.pct, {super.key, this.color = C.navy700, this.height = 8});

  @override
  Widget build(BuildContext context) {
    final p = pct.isNaN ? 0.0 : pct.clamp(0, 100).toDouble();
    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: Container(
        height: height,
        color: const Color(0xFFEEF2F7),
        alignment: Alignment.centerLeft,
        child: TweenAnimationBuilder<double>(
          tween: Tween(begin: 0, end: p / 100),
          duration: const Duration(milliseconds: 800),
          curve: Curves.easeOutCubic,
          builder: (context, v, _) => FractionallySizedBox(widthFactor: v, heightFactor: 1, child: Container(decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(999)))),
        ),
      ),
    );
  }
}

/// One stacked bar made of coloured segments (.so-bar / .vf-bar).
class SegBar extends StatelessWidget {
  final List<(num, Color)> parts;
  final double height;
  final double gap;
  const SegBar(this.parts, {super.key, this.height = 10, this.gap = 2});

  @override
  Widget build(BuildContext context) {
    final total = parts.fold<num>(0, (s, p) => s + p.$1);
    return ClipRRect(
      borderRadius: BorderRadius.circular(999),
      child: Container(
        height: height,
        color: const Color(0xFFEEF2F7),
        child: total <= 0
            ? const SizedBox.shrink()
            : Row(children: [
                for (var i = 0; i < parts.length; i++)
                  if (parts[i].$1 > 0)
                    Expanded(
                      flex: (parts[i].$1 * 1000 / total).round().clamp(1, 1000000).toInt(),
                      child: Container(margin: EdgeInsets.only(left: i == 0 ? 0 : gap), color: parts[i].$2),
                    ),
              ]),
      ),
    );
  }
}

/* ======================= small helpers ======================= */

class Avatar extends StatelessWidget {
  final String name;
  final double size;
  const Avatar(this.name, {super.key, this.size = 36});
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: const BoxDecoration(color: C.navy700, shape: BoxShape.circle),
        alignment: Alignment.center,
        child: Text(initials(name), style: T.s(size * .36, weight: FontWeight.w700, color: C.gold400)),
      );
}

/// KDA emblem on a white circle.
class Emblem extends StatelessWidget {
  final double size;
  final bool ring;
  const Emblem({super.key, this.size = 56, this.ring = true});
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          color: Colors.white,
          shape: BoxShape.circle,
          border: ring ? Border.all(color: C.lpLine) : null,
          boxShadow: const [BoxShadow(color: Color(0x330F2B52), blurRadius: 14, offset: Offset(0, 6))],
        ),
        padding: EdgeInsets.all(size * .07),
        child: ClipOval(child: Image.asset('assets/images/kda_emblem.png', fit: BoxFit.contain)),
      );
}

class Gap extends StatelessWidget {
  final double h;
  const Gap(this.h, {super.key});
  @override
  Widget build(BuildContext context) => SizedBox(height: h, width: h);
}

/* ======================= animations ======================= */

/// Fade + slide entrance (anim-fade-up).
class FadeSlideIn extends StatefulWidget {
  final Widget child;
  final Duration delay;
  final double offsetY;
  final Duration duration;
  const FadeSlideIn({super.key, required this.child, this.delay = Duration.zero, this.offsetY = 14, this.duration = const Duration(milliseconds: 450)});
  @override
  State<FadeSlideIn> createState() => _FadeSlideInState();
}

class _FadeSlideInState extends State<FadeSlideIn> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: widget.duration);
  Timer? _t;

  @override
  void initState() {
    super.initState();
    if (widget.delay == Duration.zero) {
      _c.forward();
    } else {
      _t = Timer(widget.delay, () {
        if (mounted) _c.forward();
      });
    }
  }

  @override
  void dispose() {
    _t?.cancel();
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final a = CurvedAnimation(parent: _c, curve: const Cubic(.2, .8, .2, 1));
    return AnimatedBuilder(
      animation: a,
      child: widget.child,
      builder: (context, child) => Opacity(
        opacity: a.value,
        child: Transform.translate(offset: Offset(0, widget.offsetY * (1 - a.value)), child: child),
      ),
    );
  }
}

/// Gentle bounce (fa-bounce on empty-state icons).
class Bounce extends StatefulWidget {
  final Widget child;
  const Bounce({super.key, required this.child});
  @override
  State<Bounce> createState() => _BounceState();
}

class _BounceState extends State<Bounce> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1600))..repeat();
  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: _c,
        child: widget.child,
        builder: (context, child) {
          final t = _c.value;
          final y = t < .5 ? -8 * Curves.easeOut.transform(t * 2) * (1 - t * 2) * 2 : 0.0;
          return Transform.translate(offset: Offset(0, y), child: child);
        },
      );
}

/// Small shake (fa-shake on the overdue stopwatch).
class Wobble extends StatefulWidget {
  final Widget child;
  const Wobble({super.key, required this.child});
  @override
  State<Wobble> createState() => _WobbleState();
}

class _WobbleState extends State<Wobble> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1400))..repeat();
  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: _c,
        child: widget.child,
        builder: (context, child) {
          final t = _c.value;
          final a = t < .4 ? 0.22 * (t * 10 % 2 < 1 ? 1 : -1) * (1 - t / .4) : 0.0;
          return Transform.rotate(angle: a, child: child);
        },
      );
}
