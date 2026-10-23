// PRAMAN — native Flutter port of html/index.html (KDA property verification demo).
// Same data, panels, screens and workflow; everything runs on the phone (no server, works offline).
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app/route_ctx.dart';
import 'app/routes.dart';
import 'app/store.dart';
import 'app/theme.dart';
import 'ui/common.dart';
import 'ui/landing.dart';
import 'ui/shell.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
    systemNavigationBarColor: Colors.white,
    systemNavigationBarIconBrightness: Brightness.dark,
  ));
  Routes.init();
  P.guard = Routes.guard;
  runApp(const PramanApp());
}

class PramanApp extends StatefulWidget {
  const PramanApp({super.key});
  @override
  State<PramanApp> createState() => _PramanAppState();
}

class _PramanAppState extends State<PramanApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    // write the demo database to storage as soon as the app goes to the background
    if (state != AppLifecycleState.resumed && P.ready) P.db.flush();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: App.name,
      debugShowCheckedModeBanner: false,
      theme: buildTheme(),
      navigatorKey: P.navigatorKey,
      scaffoldMessengerKey: P.messengerKey,
      home: const RootView(),
    );
  }
}

/// Splash while the database loads, then the landing page (guests) or the panel shell (signed in).
class RootView extends StatefulWidget {
  const RootView({super.key});
  @override
  State<RootView> createState() => _RootViewState();
}

class _RootViewState extends State<RootView> {
  bool _splashDone = false;
  DateTime? _lastBack;

  @override
  void initState() {
    super.initState();
    final minSplash = Future<void>.delayed(const Duration(milliseconds: 1400));
    Future.wait<void>([P.init(), minSplash]).then((_) {
      if (mounted) setState(() => _splashDone = true);
    });
  }

  void _onBack() {
    final scaffold = shellScaffoldKey.currentState;
    if (scaffold != null && scaffold.isDrawerOpen) {
      scaffold.closeDrawer();
      return;
    }
    if (P.back()) return;
    final now = DateTime.now();
    if (_lastBack != null && now.difference(_lastBack!) < const Duration(seconds: 2)) {
      SystemNavigator.pop();
      return;
    }
    _lastBack = now;
    P.messengerKey.currentState?.hideCurrentSnackBar();
    P.toast('info', 'Press back again to exit ${App.name}.', 2000);
  }

  @override
  Widget build(BuildContext context) {
    if (!_splashDone || !P.ready) return const SplashScreen();
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop) _onBack();
      },
      child: AnimatedBuilder(
        animation: P,
        builder: (context, _) {
          final u = P.user();
          if (u == null) return LandingPage(key: ValueKey('landing-${P.nav}'));
          final spec = Routes.resolve(P.path, P.query, u);
          return AppShell(
            key: const ValueKey('shell'),
            user: u,
            spec: PageSpec(spec.title, FadeSlideIn(key: ValueKey('fade-${P.nav}'), offsetY: 10, duration: const Duration(milliseconds: 280), child: spec.body)),
          );
        },
      ),
    );
  }
}

/// Animated splash: KDA emblem + PRAMAN (the native launch screen shows the emblem first).
class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});
  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen> with SingleTickerProviderStateMixin {
  late final AnimationController _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..forward();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: AnimatedBuilder(
          animation: _c,
          builder: (context, _) {
            final a = Curves.easeOutBack.transform(_c.value.clamp(0.0, 1.0).toDouble());
            final f = Curves.easeOut.transform(((_c.value - .35) / .65).clamp(0.0, 1.0).toDouble());
            return Column(mainAxisSize: MainAxisSize.min, children: [
              Transform.scale(scale: .7 + .3 * a, child: const Emblem(size: 112)),
              const SizedBox(height: 22),
              Opacity(
                opacity: f,
                child: Transform.translate(
                  offset: Offset(0, 12 * (1 - f)),
                  child: Column(children: [
                    Text(App.name, style: T.s(26, weight: FontWeight.w700, color: C.navy800, spacing: 5, family: T.poppins)),
                    const SizedBox(height: 4),
                    Text(App.org, style: T.s(13, weight: FontWeight.w500, color: C.lpMuted, family: T.poppins)),
                    const SizedBox(height: 26),
                    const SizedBox(width: 120, child: LinearProgressIndicator(minHeight: 3, color: C.gold500, backgroundColor: C.navy100)),
                  ]),
                ),
              ),
            ]);
          },
        ),
      ),
    );
  }
}
