// PRAMAN — landing page for guests (js/pages/landing.js + css/landing.css): white header with the KDA
// emblem and a Sign in button, light hero with the headline, a 4-photo slider of Kota, and the sign-in
// form in an animated popup (with the demo accounts). Opens by itself when a flash message is waiting.
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:url_launcher/url_launcher.dart';

import '../app/fa.dart';
import '../app/store.dart';
import '../app/theme.dart';
import 'common.dart';
import 'forms.dart';

class LandingPage extends StatefulWidget {
  const LandingPage({super.key});
  @override
  State<LandingPage> createState() => _LandingPageState();
}

class _LandingPageState extends State<LandingPage> {
  @override
  void initState() {
    super.initState();
    // a waiting flash message ("Please login to continue", "logged out" …) opens the popup straight away
    if (P.flashes.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) showSignIn(context);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.dark.copyWith(statusBarColor: Colors.transparent, systemNavigationBarColor: Colors.white, systemNavigationBarIconBrightness: Brightness.dark),
      child: Scaffold(
        backgroundColor: const Color(0xFFF7F9FD),
        body: Column(children: [
          const _Header(),
          Expanded(
            child: SingleChildScrollView(
              child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [_Hero(), _Footer()]),
            ),
          ),
        ]),
      ),
    );
  }
}

/* ======================= header ======================= */

class _Header extends StatelessWidget {
  const _Header();
  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xF2FFFFFF),
        border: Border(bottom: BorderSide(color: Color(0x0F0F2B52))),
        boxShadow: [BoxShadow(color: Color(0x140A1F3C), blurRadius: 18, offset: Offset(0, 6))],
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 10),
          child: Row(children: [
            const Emblem(size: 54),
            const SizedBox(width: 10),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(App.name, style: T.s(17, weight: FontWeight.w700, color: C.lpInk, spacing: 2.2, family: T.poppins)),
                Text(App.org, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(11, weight: FontWeight.w500, color: C.lpMuted, family: T.poppins)),
              ]),
            ),
            const SizedBox(width: 8),
            _GradientButton(text: 'Sign in', icon: FA.rightToBracket, small: true, onTap: () => showSignIn(context)),
          ]),
        ),
      ),
    );
  }
}

class _GradientButton extends StatelessWidget {
  final String text;
  final IconData icon;
  final VoidCallback onTap;
  final bool small;
  const _GradientButton({required this.text, required this.icon, required this.onTap, this.small = false});
  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [C.btnTop, C.btnBottom], begin: Alignment.topCenter, end: Alignment.bottomCenter),
        borderRadius: BorderRadius.circular(999),
        boxShadow: const [BoxShadow(color: Color(0x99163F7A), blurRadius: 22, offset: Offset(0, 10), spreadRadius: -10)],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(999),
          onTap: onTap,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: small ? 16 : 26, vertical: small ? 10 : 15),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, size: small ? 13 : 15, color: Colors.white),
              const SizedBox(width: 8),
              Text(text, style: T.s(small ? 13.5 : 15.5, weight: FontWeight.w600, color: Colors.white, family: T.poppins)),
            ]),
          ),
        ),
      ),
    );
  }
}

/* ======================= hero ======================= */

class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    final sections = P.db.count('roles', {'is_active': 1});
    final copy = Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      FadeSlideIn(
        child: Container(
          padding: const EdgeInsets.fromLTRB(5, 5, 14, 5),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(999),
            border: Border.all(color: C.lpLine),
            boxShadow: const [BoxShadow(color: Color(0x260F2B52), blurRadius: 20, offset: Offset(0, 8), spreadRadius: -14)],
          ),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFFF8CF5A), C.lpGold]), borderRadius: BorderRadius.circular(999)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(FA.shieldHalved, size: 10, color: C.navy900),
                const SizedBox(width: 5),
                Text('KDA', style: T.s(11.5, weight: FontWeight.w700, color: C.navy900, family: T.poppins, spacing: .6)),
              ]),
            ),
            const SizedBox(width: 10),
            Flexible(child: Text('Property verification before every auction', style: T.s(12, weight: FontWeight.w500, color: C.lpInk, family: T.poppins))),
          ]),
        ),
      ),
      const SizedBox(height: 20),
      FadeSlideIn(
        delay: const Duration(milliseconds: 80),
        child: Text.rich(
          TextSpan(children: [
            TextSpan(text: 'Every vacant property.\n', style: _titleStyle(C.lpInk)),
            TextSpan(text: 'Verified &\nauction-ready.', style: _titleStyle(C.lpBlue).copyWith(decoration: TextDecoration.underline, decorationColor: const Color(0x80F6C445), decorationThickness: 5)),
          ]),
        ),
      ),
      const SizedBox(height: 16),
      FadeSlideIn(
        delay: const Duration(milliseconds: 160),
        child: Text.rich(TextSpan(children: [
          TextSpan(text: '${App.name} ', style: T.s(15, weight: FontWeight.w700, color: C.lpInk, family: T.poppins, height: 1.7)),
          TextSpan(
            text: 'brings Data Entry, OIC Auction and ${sections > 0 ? 'all $sections' : 'every'} verification sections of the ${App.org} onto one secure, fully audited workflow.',
            style: T.s(15, color: C.lpMuted, family: T.poppins, height: 1.7),
          ),
        ])),
      ),
      const SizedBox(height: 24),
      FadeSlideIn(
        delay: const Duration(milliseconds: 240),
        child: _GradientButton(text: 'Sign in to ${App.name}', icon: FA.rightToBracket, onTap: () => showSignIn(context)),
      ),
    ]);
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFFFBFCFF), Color(0xFFF0F4FB)]),
      ),
      child: Stack(children: [
        // soft blue / gold glows (radial gradients of the web hero)
        Positioned(top: -60, right: -80, child: _Glow(color: const Color(0x261D4F91), size: 320)),
        Positioned(bottom: -80, left: -90, child: _Glow(color: const Color(0x26E9A91C), size: 300)),
        Padding(
          padding: const EdgeInsets.fromLTRB(18, 26, 18, 34),
          child: LayoutBuilder(builder: (context, c) {
            if (c.maxWidth >= 860) {
              return Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                Expanded(flex: 11, child: copy),
                const SizedBox(width: 40),
                const Expanded(flex: 10, child: HeroSlider()),
              ]);
            }
            return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [copy, const SizedBox(height: 40), const HeroSlider()]);
          }),
        ),
      ]),
    );
  }

  TextStyle _titleStyle(Color c) => TextStyle(fontFamily: T.poppins, fontSize: 33, fontWeight: FontWeight.w700, height: 1.16, letterSpacing: -.6, color: c);
}

class _Glow extends StatelessWidget {
  final Color color;
  final double size;
  const _Glow({required this.color, required this.size});
  @override
  Widget build(BuildContext context) => IgnorePointer(
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(shape: BoxShape.circle, gradient: RadialGradient(colors: [color, color.withAlpha(0)])),
        ),
      );
}

/* ======================= photo slider ======================= */

class _Slide {
  final String asset;
  final String credit;
  const _Slide(this.asset, this.credit);
}

const List<_Slide> _slides = [
  _Slide('assets/images/slides/kishore_sagar.jpg', 'Kishore Sagar, Kota — photo: Kushagra140, CC BY-SA 4.0, via Wikimedia Commons'),
  _Slide('assets/images/slides/garh_palace.jpg', 'Garh Palace, Kota — photo: Shrikant Bansod, CC BY-SA 4.0, via Wikimedia Commons'),
  _Slide('assets/images/slides/river_front.jpg', 'Chambal River Front, Kota — photo: Imakanksha, CC BY-SA 4.0, via Wikimedia Commons'),
  _Slide('assets/images/slides/seven_wonders.jpg', 'Seven Wonders Park, Kota — photo: Arvind Sunda07, CC BY-SA 4.0, via Wikimedia Commons'),
];

/// Big frame on a fanned "photo stack", clip-path wipe between photos, slow Ken Burns zoom,
/// thumbnails with an autoplay progress bar, counter, arrows and swipe.
class HeroSlider extends StatefulWidget {
  const HeroSlider({super.key});
  @override
  State<HeroSlider> createState() => _HeroSliderState();
}

class _HeroSliderState extends State<HeroSlider> with TickerProviderStateMixin {
  int _cur = 0;
  int _prev = -1;
  int _dir = 1;
  int _serial = 0;
  late final AnimationController _wipe = AnimationController(vsync: this, duration: const Duration(milliseconds: 1000));
  late final AnimationController _progress = AnimationController(vsync: this, duration: const Duration(seconds: 6));

  @override
  void initState() {
    super.initState();
    _progress.addStatusListener((s) {
      if (s == AnimationStatus.completed) _go(_cur + 1, 1);
    });
    _progress.forward();
    _wipe.value = 1;
  }

  @override
  void dispose() {
    _wipe.dispose();
    _progress.dispose();
    super.dispose();
  }

  void _go(int to, int dir) {
    final n = _slides.length;
    final next = ((to % n) + n) % n;
    if (next == _cur) return;
    setState(() {
      _prev = _cur;
      _cur = next;
      _dir = dir;
      _serial++;
    });
    _wipe.forward(from: 0);
    _progress.forward(from: 0);
  }

  void _pause(bool on) {
    if (on) {
      _progress.stop();
    } else if (!_progress.isAnimating) {
      _progress.forward();
    }
  }

  @override
  Widget build(BuildContext context) {
    return FadeSlideIn(
      delay: const Duration(milliseconds: 160),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        AspectRatio(
          aspectRatio: 10 / 9,
          child: Stack(clipBehavior: Clip.none, children: [
            // the two cards behind the frame
            Positioned.fill(child: Transform.translate(offset: const Offset(-10, 10), child: Transform.rotate(angle: -.07, child: _deck(const Color(0xFFD9E4F5))))),
            Positioned.fill(child: Transform.translate(offset: const Offset(10, 8), child: Transform.rotate(angle: .05, child: _deck(const Color(0xFFFBE9BD))))),
            Positioned.fill(child: _frame()),
          ]),
        ),
        const SizedBox(height: 18),
        Row(children: [
          for (var i = 0; i < _slides.length; i++) ...[
            if (i > 0) const SizedBox(width: 10),
            Expanded(child: _thumb(i)),
          ],
        ]),
      ]),
    );
  }

  Widget _deck(Color tint) => Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: C.lpLine),
          gradient: LinearGradient(begin: Alignment.topLeft, end: Alignment.bottomRight, colors: [tint, Colors.white], stops: const [0, .7]),
          boxShadow: const [BoxShadow(color: Color(0x2E0A1F3C), blurRadius: 40, offset: Offset(0, 20), spreadRadius: -24)],
        ),
      );

  Widget _frame() {
    return GestureDetector(
      onTapDown: (_) => _pause(true),
      onTapUp: (_) => _pause(false),
      onTapCancel: () => _pause(false),
      onLongPress: () => P.toast('info', _slides[_cur].credit, 5000),
      onHorizontalDragEnd: (d) {
        final v = d.primaryVelocity ?? 0;
        if (v < -150) _go(_cur + 1, 1);
        if (v > 150) _go(_cur - 1, -1);
      },
      child: Container(
        decoration: BoxDecoration(
          color: C.navy100,
          borderRadius: BorderRadius.circular(26),
          border: Border.all(color: Colors.white, width: 5),
          boxShadow: const [BoxShadow(color: Color(0x660A1F3C), blurRadius: 50, offset: Offset(0, 26), spreadRadius: -26)],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(21),
          child: Stack(fit: StackFit.expand, children: [
            AnimatedBuilder(
              animation: _wipe,
              builder: (context, _) {
                final t = Curves.easeInOutCubic.transform(_wipe.value);
                return LayoutBuilder(builder: (context, c) {
                  final w = c.maxWidth;
                  return Stack(fit: StackFit.expand, children: [
                    if (_prev >= 0 && _wipe.value < 1)
                      Transform.translate(
                        offset: Offset(-_dir * .16 * w * t, 0),
                        child: Stack(fit: StackFit.expand, children: [
                          _KenBurns(key: ValueKey('kb-p-$_prev-$_serial'), asset: _slides[_prev].asset, startScale: 1.05),
                          Container(color: Colors.black.withAlpha((64 * t).round())),
                        ]),
                      ),
                    ClipRect(
                      clipper: _WipeClipper(t, _dir),
                      child: _KenBurns(key: ValueKey('kb-$_cur-$_serial'), asset: _slides[_cur].asset),
                    ),
                  ]);
                });
              },
            ),
            // counter "01 / 04"
            Positioned(
              left: 12,
              bottom: 12,
              child: _glass(
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 7),
                  child: Row(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.end, children: [
                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 450),
                      transitionBuilder: (child, a) => FadeTransition(
                        opacity: a,
                        child: SlideTransition(position: Tween<Offset>(begin: const Offset(0, .7), end: Offset.zero).animate(a), child: child),
                      ),
                      child: Text((_cur + 1).toString().padLeft(2, '0'), key: ValueKey(_cur), style: T.s(15, weight: FontWeight.w700, color: C.lpInk, family: T.poppins)),
                    ),
                    Text(' / ${_slides.length.toString().padLeft(2, '0')}', style: T.s(12, weight: FontWeight.w600, color: C.lpMuted, family: T.poppins)),
                  ]),
                ),
              ),
            ),
            // arrows
            Positioned(
              right: 12,
              bottom: 12,
              child: Row(children: [
                _arrow(FA.arrowLeft, () => _go(_cur - 1, -1)),
                const SizedBox(width: 8),
                _arrow(FA.arrowRight, () => _go(_cur + 1, 1)),
              ]),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _glass(Widget child) => ClipRRect(
        borderRadius: BorderRadius.circular(999),
        child: BackdropFilter(
          filter: ui.ImageFilter.blur(sigmaX: 8, sigmaY: 8),
          child: Container(color: const Color(0xE6FFFFFF), child: child),
        ),
      );

  Widget _arrow(IconData icon, VoidCallback onTap) => _glass(
        Material(
          color: Colors.transparent,
          child: InkWell(
            onTap: onTap,
            child: SizedBox(width: 40, height: 40, child: Icon(icon, size: 14, color: C.lpInk)),
          ),
        ),
      );

  Widget _thumb(int i) {
    final on = i == _cur;
    return GestureDetector(
      onTap: () => _go(i, i < _cur ? -1 : 1),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeOut,
        transform: Matrix4.translationValues(0, on ? -4 : 0, 0),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white, width: 2.5),
          boxShadow: on
              ? const [BoxShadow(color: C.lpBlue, spreadRadius: 2), BoxShadow(color: Color(0x991D4F91), blurRadius: 24, offset: Offset(0, 12), spreadRadius: -12)]
              : const [BoxShadow(color: Color(0x660A1F3C), blurRadius: 18, offset: Offset(0, 8), spreadRadius: -12)],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: AspectRatio(
            aspectRatio: 16 / 10,
            child: Stack(fit: StackFit.expand, children: [
              Opacity(opacity: on ? 1 : .6, child: Image.asset(_slides[i].asset, fit: BoxFit.cover)),
              if (on)
                Positioned(
                  left: 6,
                  right: 6,
                  bottom: 5,
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(3),
                    child: Container(
                      height: 3,
                      color: const Color(0x99FFFFFF),
                      alignment: Alignment.centerLeft,
                      child: AnimatedBuilder(
                        animation: _progress,
                        builder: (context, _) => FractionallySizedBox(
                          widthFactor: _progress.value,
                          child: Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFFF6C445), C.lpGold]))),
                        ),
                      ),
                    ),
                  ),
                ),
            ]),
          ),
        ),
      ),
    );
  }
}

/// Reveals the new photo from the right (next) or from the left (previous).
class _WipeClipper extends CustomClipper<Rect> {
  final double t;
  final int dir;
  _WipeClipper(this.t, this.dir);
  @override
  Rect getClip(Size size) {
    final w = size.width * t;
    return dir > 0 ? Rect.fromLTWH(size.width - w, 0, w, size.height) : Rect.fromLTWH(0, 0, w, size.height);
  }

  @override
  bool shouldReclip(covariant _WipeClipper old) => old.t != t || old.dir != dir;
}

/// Slow zoom of the active photo (scale 1.18 → 1.03 over 7 s).
class _KenBurns extends StatefulWidget {
  final String asset;
  final double startScale;
  const _KenBurns({super.key, required this.asset, this.startScale = 1.18});
  @override
  State<_KenBurns> createState() => _KenBurnsState();
}

class _KenBurnsState extends State<_KenBurns> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(seconds: 7))..forward();
  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      child: Image.asset(widget.asset, fit: BoxFit.cover, gaplessPlayback: true),
      builder: (context, child) {
        final s = widget.startScale - (widget.startScale - 1.03) * Curves.easeOutCubic.transform(_c.value);
        return Transform.scale(scale: s, child: child);
      },
    );
  }
}

/* ======================= footer ======================= */

class _Footer extends StatelessWidget {
  const _Footer();
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(18, 24, 18, 26),
      decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: C.lpLine))),
      child: SafeArea(
        top: false,
        child: Column(children: [
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Emblem(size: 56),
            const SizedBox(width: 12),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(App.name, style: T.s(18, weight: FontWeight.w700, color: C.lpInk, spacing: .8, family: T.poppins)),
              Text('PROPERTY VERIFICATION SYSTEM', style: T.s(9.5, weight: FontWeight.w600, color: const Color(0xFF8A97AB), spacing: 1.3, family: T.poppins)),
            ]),
          ]),
          const SizedBox(height: 16),
          const Divider(color: C.lpLine, height: 1),
          const SizedBox(height: 14),
          Text('© ${DateTime.now().year} ${App.org},\nGovt. of Rajasthan. All rights reserved.', textAlign: TextAlign.center, style: T.s(12.5, color: C.lpMuted, height: 1.6, family: T.poppins)),
          const SizedBox(height: 10),
          InkWell(
            onTap: () => launchUrl(Uri.parse(App.kdaSite), mode: LaunchMode.externalApplication),
            child: Padding(
              padding: const EdgeInsets.all(6),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Text('kdakota.rajasthan.gov.in', style: T.s(13.5, weight: FontWeight.w600, color: C.lpBlue, family: T.poppins)),
                const SizedBox(width: 6),
                const Icon(FA.arrowUpRightFromSquare, size: 11, color: C.lpBlue),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

/* ======================= sign-in popup ======================= */

Future<void> showSignIn(BuildContext context) {
  final flashes = List<Flash>.of(P.flashes);
  return showGeneralDialog<void>(
    context: context,
    barrierDismissible: true,
    barrierLabel: 'Close',
    barrierColor: const Color(0x4D0F2B52),
    transitionDuration: const Duration(milliseconds: 480),
    pageBuilder: (context, a1, a2) => _SignInDialog(flashes: flashes),
    transitionBuilder: (context, anim, secondary, child) {
      final curved = CurvedAnimation(parent: anim, curve: const Cubic(.2, .9, .3, 1.15), reverseCurve: Curves.easeIn);
      return BackdropFilter(
        filter: ui.ImageFilter.blur(sigmaX: 6 * anim.value, sigmaY: 6 * anim.value),
        child: FadeTransition(
          opacity: CurvedAnimation(parent: anim, curve: Curves.easeOut),
          child: AnimatedBuilder(
            animation: curved,
            child: child,
            builder: (context, child) => Transform.translate(
              offset: Offset(0, 32 * (1 - curved.value)),
              child: Transform.scale(scale: .94 + .06 * curved.value, child: child),
            ),
          ),
        ),
      );
    },
  );
}

const List<(String, String, IconData)> _demoAccounts = [
  ('superadmin', 'Super Admin', FA.userShield),
  ('dataentry', 'Data Entry', FA.keyboard),
  ('oic', 'OIC Auction', FA.gavel),
  ('eng1', 'Engineering Cell', FA.helmetSafety),
  ('tp1', 'Town Planning Cell', FA.mapLocationDot),
  ('legal1', 'Legal Cell', FA.scaleBalanced),
];

class _SignInDialog extends StatefulWidget {
  final List<Flash> flashes;
  const _SignInDialog({required this.flashes});
  @override
  State<_SignInDialog> createState() => _SignInDialogState();
}

class _SignInDialogState extends State<_SignInDialog> {
  final FormModel _f = FormModel();
  late List<Flash> _flashes = List<Flash>.of(widget.flashes);

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    await submitForm(_f, action: () {
      final res = P.attempt(_f.value('username'), _f.value('password'));
      if (!res.ok) {
        _f.fail('password', res.message);
        return;
      }
      _f.submitting = false;
      final nav = Navigator.of(context);
      nav.pop();
      P.flash('success', res.message);
      P.history.clear();
      P.go(P.homeOf(P.user()), null, true);
    });
  }

  void _demo(String username) {
    _f.set('username', username);
    _f.set('password', 'Praman@123');
    _submit();
  }

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(14, 16, 14, 16 + media.viewInsets.bottom),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: Material(
              color: Colors.white,
              borderRadius: BorderRadius.circular(22),
              elevation: 0,
              child: Container(
                decoration: BoxDecoration(borderRadius: BorderRadius.circular(22), boxShadow: const [BoxShadow(color: Color(0x8C0A1F3C), blurRadius: 90, offset: Offset(0, 40), spreadRadius: -30)]),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(22),
                  child: AnimatedBuilder(
                    animation: _f,
                    builder: (context, _) => Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Container(height: 5, decoration: const BoxDecoration(gradient: LinearGradient(colors: [C.lpBlue, Color(0xFF2F6FC4), C.lpGold], stops: [0, .55, 1]))),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(22, 22, 12, 6),
                        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text('Sign in', style: T.s(25, weight: FontWeight.w700, color: C.navy800, family: T.poppins)),
                              const SizedBox(height: 2),
                              Text.rich(TextSpan(children: [
                                TextSpan(text: 'Use your ', style: T.s(13, color: C.muted, family: T.poppins)),
                                TextSpan(text: App.name, style: T.s(13, weight: FontWeight.w600, color: C.navy700, family: T.poppins)),
                                TextSpan(text: ' username and password.', style: T.s(13, color: C.muted, family: T.poppins)),
                              ])),
                            ]),
                          ),
                          _CloseButton(onTap: () => Navigator.of(context).pop()),
                        ]),
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(22, 12, 22, 22),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                          for (final fl in _flashes)
                            AlertBox(type: fl.type, text: fl.message, onClose: () => setState(() => _flashes = _flashes.where((x) => !identical(x, fl)).toList())),
                          FText(
                            form: _f,
                            name: 'username',
                            label: 'Username',
                            rules: 'required|max:50|alpha_dash',
                            placeholder: 'Enter your username',
                            prefix: FA.user,
                            maxLength: 50,
                          ),
                          FText(
                            form: _f,
                            name: 'password',
                            label: 'Password',
                            rules: 'required',
                            placeholder: 'Enter your password',
                            prefix: FA.lock,
                            password: true,
                            maxLength: 72,
                            action: TextInputAction.done,
                            onSubmitted: (_) => _submit(),
                          ),
                          const SizedBox(height: 4),
                          _SignInButton(loading: _f.submitting, onTap: _submit),
                          const SizedBox(height: 20),
                          _demoBox(),
                        ]),
                      ),
                    ]),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _demoBox() {
    return Container(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
      decoration: BoxDecoration(color: const Color(0xFFFDF8E9), borderRadius: BorderRadius.circular(12), border: Border.all(color: const Color(0xFFF1E2B1))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 8, runSpacing: 6, children: [
          const Icon(FA.users, size: 15, color: C.navy700),
          Text('Demo accounts', style: T.s(14, weight: FontWeight.w700, color: C.navy800)),
          Text('password', style: T.s(12, color: C.muted)),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 2),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(999), border: Border.all(color: const Color(0xFFE8DAB0))),
            child: Text('Praman@123', style: T.s(12, weight: FontWeight.w500, color: C.navy800)),
          ),
        ]),
        const SizedBox(height: 4),
        Text('Panel users and Role Users (verification sections):', style: T.s(12, color: C.muted)),
        const SizedBox(height: 10),
        LayoutBuilder(builder: (context, c) {
          final w = c.maxWidth >= 300 ? (c.maxWidth - 8) / 2 : c.maxWidth;
          return Wrap(spacing: 8, runSpacing: 8, children: [
            for (final a in _demoAccounts)
              SizedBox(
                width: w,
                child: Material(
                  color: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8), side: const BorderSide(color: Color(0xFFE3E7EE))),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: _f.submitting ? null : () => _demo(a.$1),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
                      child: Row(children: [
                        SizedBox(width: 18, child: Icon(a.$3, size: 13, color: C.navy700)),
                        const SizedBox(width: 6),
                        Expanded(child: Text(a.$2, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(12.5, weight: FontWeight.w500, color: C.navy800))),
                      ]),
                    ),
                  ),
                ),
              ),
          ]);
        }),
        const SizedBox(height: 10),
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Padding(padding: EdgeInsets.only(top: 2), child: Icon(FA.database, size: 11, color: C.muted)),
          const SizedBox(width: 6),
          Expanded(child: Text('All data is stored on this phone only (demo database).', style: T.s(11.5, color: C.muted))),
          InkWell(
            onTap: () async {
              final yes = await P.confirm(title: 'Reset demo data', message: 'Reset ALL demo data to the original sample data? Everything you added will be removed.', okText: 'Yes, reset', variant: 'danger');
              if (!yes) return;
              P.db.reset();
              P.toast('success', 'Demo data has been reset.');
            },
            child: Padding(
              padding: const EdgeInsets.only(left: 6),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(FA.rotate, size: 11, color: C.blue600),
                const SizedBox(width: 4),
                Text('Reset', style: T.s(12, weight: FontWeight.w600, color: C.blue600)),
              ]),
            ),
          ),
        ]),
      ]),
    );
  }
}

class _CloseButton extends StatefulWidget {
  final VoidCallback onTap;
  const _CloseButton({required this.onTap});
  @override
  State<_CloseButton> createState() => _CloseButtonState();
}

class _CloseButtonState extends State<_CloseButton> {
  bool _down = false;
  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) => setState(() => _down = true),
      onTapCancel: () => setState(() => _down = false),
      onTapUp: (_) {
        setState(() => _down = false);
        widget.onTap();
      },
      child: AnimatedRotation(
        turns: _down ? .25 : 0,
        duration: const Duration(milliseconds: 250),
        child: Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(color: _down ? C.red100 : C.slate100, shape: BoxShape.circle),
          alignment: Alignment.center,
          child: Icon(FA.xmark, size: 15, color: _down ? C.red600 : C.slate600),
        ),
      ),
    );
  }
}

class _SignInButton extends StatelessWidget {
  final bool loading;
  final VoidCallback onTap;
  const _SignInButton({required this.loading, required this.onTap});
  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [C.btnTop, C.btnBottom], begin: Alignment.topCenter, end: Alignment.bottomCenter),
        borderRadius: BorderRadius.circular(10),
        boxShadow: const [BoxShadow(color: Color(0xCC163F7A), blurRadius: 24, offset: Offset(0, 12), spreadRadius: -12)],
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: loading ? null : onTap,
          child: SizedBox(
            height: 50,
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              if (loading)
                const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
              else
                const Icon(FA.rightToBracket, size: 15, color: Colors.white),
              const SizedBox(width: 10),
              Text(loading ? 'Signing in…' : 'Sign in', style: T.s(15.5, weight: FontWeight.w600, color: Colors.white)),
            ]),
          ),
        ),
      ),
    );
  }
}
