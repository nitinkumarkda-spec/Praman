// PRAMAN — colours and text styles (same tokens as css/style.css :root of the web app).
import 'package:flutter/material.dart';

/// Colour tokens (`--navy-700`, `--gold-500`, … in the web stylesheet).
class C {
  C._();
  static const Color navy900 = Color(0xFF0A1F3C);
  static const Color navy800 = Color(0xFF0F2B52);
  static const Color navy700 = Color(0xFF163A6B);
  static const Color navy600 = Color(0xFF1F4C87);
  static const Color navy100 = Color(0xFFE6EDF7);
  static const Color navy50 = Color(0xFFF2F6FB);

  static const Color gold600 = Color(0xFFA8841B);
  static const Color gold500 = Color(0xFFC9A227);
  static const Color gold400 = Color(0xFFD9B84A);
  static const Color gold100 = Color(0xFFFAF3DC);

  static const Color green600 = Color(0xFF15803D);
  static const Color green100 = Color(0xFFDCFCE7);
  static const Color red600 = Color(0xFFB91C1C);
  static const Color red100 = Color(0xFFFEE2E2);
  static const Color amber600 = Color(0xFFB45309);
  static const Color amber100 = Color(0xFFFEF3C7);
  static const Color blue600 = Color(0xFF1D4ED8);
  static const Color blue100 = Color(0xFFDBEAFE);
  static const Color slate600 = Color(0xFF475569);
  static const Color slate100 = Color(0xFFF1F5F9);
  static const Color slate400 = Color(0xFF94A3B8);

  static const Color bg = Color(0xFFF4F6FA);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color text = Color(0xFF1E293B);
  static const Color muted = Color(0xFF64748B);
  static const Color border = Color(0xFFE2E8F0);
  static const Color borderStrong = Color(0xFFCBD5E1);

  // sidebar (demo.css: linear-gradient(180deg, #23518c, #1b4272 55%, #173a66))
  static const Color side1 = Color(0xFF23518C);
  static const Color side2 = Color(0xFF1B4272);
  static const Color side3 = Color(0xFF173A66);

  // landing page (lp- variables)
  static const Color lpInk = Color(0xFF0F2B52);
  static const Color lpMuted = Color(0xFF5B6B82);
  static const Color lpBlue = Color(0xFF1D4F91);
  static const Color lpGold = Color(0xFFE9A91C);
  static const Color lpLine = Color(0xFFE3E9F2);
  static const Color btnTop = Color(0xFF245CA8);
  static const Color btnBottom = Color(0xFF163F7A);

  // chart palette (P.COLORS in charts.js)
  static const Color chNavy = Color(0xFF4A72B0);
  static const Color chGold = Color(0xFFE3B55B);
  static const Color chGreen = Color(0xFF4FAE8A);
  static const Color chRed = Color(0xFFE07A7A);
  static const Color chAmber = Color(0xFFF0A85C);
  static const Color chBlue = Color(0xFF6B9BD8);
  static const Color chSlate = Color(0xFFB8C2D1);
  static const Color chPurple = Color(0xFF9B8AD6);
  static const Color chTeal = Color(0xFF5BB3B0);
  static const Color chPink = Color(0xFFE49AB8);
  static const Color chDark = Color(0xFF7D8AA0);
  static const Color chSky = Color(0xFF7CC4E4);

  static const List<Color> series = [chNavy, chGold, chGreen, chBlue, chPurple, chTeal, chRed, chAmber, chPink, chSky, chSlate, chDark];

  /// Chart colour per property status (P.STATUS_COLORS).
  static Color statusColor(String code) {
    switch (code) {
      case 'LISTED':
        return chBlue;
      case 'UNDER_VERIFICATION':
        return chAmber;
      case 'AUCTION_READY':
        return chGreen;
      case 'NOT_READY':
        return chRed;
      case 'SENT_FOR_AUCTION':
        return chNavy;
      case 'SOLD':
        return chPurple;
      case 'WITHHELD':
        return chSlate;
      case 'ARCHIVED':
        return const Color(0xFFDDE3EA);
      default:
        return chSlate;
    }
  }
}

/// Badge / alert / button tone → colours.
class Tone {
  final Color fg;
  final Color bg;
  final Color border;
  const Tone(this.fg, this.bg, this.border);

  static const Tone success = Tone(C.green600, C.green100, Color(0xFFBBF7D0));
  static const Tone danger = Tone(C.red600, C.red100, Color(0xFFFECACA));
  static const Tone warning = Tone(C.amber600, C.amber100, Color(0xFFFDE68A));
  static const Tone info = Tone(C.blue600, C.blue100, Color(0xFFBFDBFE));
  static const Tone primary = Tone(C.navy700, C.navy100, Color(0xFFC7D6EA));
  static const Tone gold = Tone(C.gold600, C.gold100, Color(0xFFF0DFA6));
  static const Tone dark = Tone(Color(0xFF0F172A), Color(0xFFE2E8F0), Color(0xFFCBD5E1));
  static const Tone muted = Tone(C.slate600, C.slate100, C.border);

  /// `badge-success`, `success`, `error` … → tone.
  static Tone of(String? name) {
    final n = (name ?? '').replaceFirst('badge-', '').replaceFirst('alert-', '');
    switch (n) {
      case 'success':
      case 'ok':
        return success;
      case 'danger':
      case 'error':
      case 'bad':
        return danger;
      case 'warning':
      case 'warn':
        return warning;
      case 'info':
        return info;
      case 'primary':
      case 'navy':
        return primary;
      case 'gold':
        return gold;
      case 'dark':
        return dark;
      default:
        return muted;
    }
  }
}

/// Text styles (Inter for the app, Poppins for the landing page — same as the web app).
class T {
  T._();
  static const String inter = 'Inter';
  static const String poppins = 'Poppins';

  static TextStyle s(double size, {FontWeight weight = FontWeight.w400, Color color = C.text, double? height, double? spacing, String family = inter}) {
    return TextStyle(fontFamily: family, fontSize: size, fontWeight: weight, color: color, height: height, letterSpacing: spacing);
  }

  static const TextStyle body = TextStyle(fontFamily: inter, fontSize: 14, color: C.text, height: 1.45);
  static const TextStyle small = TextStyle(fontFamily: inter, fontSize: 12.5, color: C.muted, height: 1.4);
  static const TextStyle tiny = TextStyle(fontFamily: inter, fontSize: 11.5, color: C.muted, height: 1.35);
  static const TextStyle label = TextStyle(fontFamily: inter, fontSize: 13.5, fontWeight: FontWeight.w500, color: C.navy800);
  static const TextStyle strong = TextStyle(fontFamily: inter, fontSize: 14, fontWeight: FontWeight.w600, color: C.text);
  static const TextStyle cardTitle = TextStyle(fontFamily: inter, fontSize: 15, fontWeight: FontWeight.w600, color: C.text);
  static const TextStyle pageTitle = TextStyle(fontFamily: inter, fontSize: 21, fontWeight: FontWeight.w700, color: C.text, height: 1.25);
  static const TextStyle mono = TextStyle(fontFamily: 'monospace', fontSize: 13, color: C.navy800);
}

ThemeData buildTheme() {
  final scheme = ColorScheme.fromSeed(seedColor: C.navy700, brightness: Brightness.light).copyWith(
    primary: C.navy700,
    secondary: C.gold500,
    surface: C.surface,
    error: C.red600,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    fontFamily: T.inter,
    scaffoldBackgroundColor: C.bg,
    splashFactory: InkRipple.splashFactory,
    visualDensity: VisualDensity.standard,
  );
}
