// PRAMAN — Change Password (js/pages/auth.js): strength meter + live rules panel (8 rules).
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/route_ctx.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../app/validation.dart';
import '../ui/common.dart';
import '../ui/forms.dart';

class ChangePasswordPage extends StatefulWidget {
  final RouteCtx ctx;
  const ChangePasswordPage(this.ctx, {super.key});
  @override
  State<ChangePasswordPage> createState() => _ChangePasswordPageState();
}

class _ChangePasswordPageState extends State<ChangePasswordPage> {
  final FormModel _f = FormModel();

  @override
  void dispose() {
    _f.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    await submitForm(_f, action: () {
      final db = P.db;
      if (P.impersonator() != null) {
        _f.fail(null, 'Password cannot be changed while you are logged in as another user. Use User Master → Reset password instead.');
        return;
      }
      final u = db.find('users', P.uid)!;
      final np = _f.value('new_password');
      if (np == u['password']) {
        _f.fail('new_password', 'New password must be different from the current password.');
        return;
      }
      if (np.toLowerCase().contains(str(u['username']).toLowerCase())) {
        _f.fail('new_password', 'New password must not contain your username.');
        return;
      }
      db.update('users', u['user_id'], {'password': np, 'password_changed_at': db.now(), 'must_change_password': 0});
      db.audit('USER', u['user_id'], 'PASSWORD_CHANGED', null, null);
      P.flash('success', 'Password changed successfully.');
      P.go(P.homeOf(P.user()));
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _f,
      builder: (context, _) {
        final v = _f.value('new_password');
        final c = _f.value('confirm_password');
        final s = Validator.strength(v);
        const names = ['Enter a new password', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
        const colors = [C.red600, C.red600, C.amber600, C.amber600, C.blue600, C.green600];
        final username = str(widget.ctx.user['username']).toLowerCase();
        final cur = str(P.db.find('users', P.uid)?['password']);
        final checks = Validator.passwordChecks(v);
        final rules = <(String, String, bool)>[
          ('length', 'At least ${Validator.passwordMin} characters (${v.length})', checks['length']!),
          ('upper', 'One uppercase letter (A–Z)', checks['upper']!),
          ('lower', 'One lowercase letter (a–z)', checks['lower']!),
          ('digit', 'One digit (0–9)', checks['digit']!),
          ('special', 'One special character (e.g. @ # \$ %)', checks['special']!),
          ('differs', 'Different from the current password', v.isNotEmpty && v != cur),
          ('nouser', 'Does not contain your username ($username)', v.isNotEmpty && !v.toLowerCase().contains(username)),
          ('match', 'Confirm password matches the new password', c.isNotEmpty && c == v),
        ];
        final ok = rules.where((r) => r.$3).length;
        const stateNames = ['', 'Very weak', 'Weak', 'Fair', 'Good', 'Strong'];
        final strength = ['length', 'upper', 'lower', 'digit', 'special'].where((k) => checks[k]!).length;
        return PageBody(children: [
          const PageHeader(icon: FA.key, title: 'Change Password', subtitle: 'Keep your account secure with a strong password.'),
          AppCard(
            title: 'Set a new password',
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              FText(form: _f, name: 'new_password', label: 'New password', rules: 'required|password', password: true, prefix: FA.key, maxLength: 72),
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  ProgressBar(s * 20.0, color: colors[s], height: 6),
                  const SizedBox(height: 5),
                  Text(v.isEmpty ? names[0] : names[s], style: T.s(12, weight: FontWeight.w600, color: colors[s])),
                ]),
              ),
              FText(form: _f, name: 'confirm_password', label: 'Confirm new password', rules: 'required|same:new_password', password: true, prefix: FA.key, maxLength: 72),
              Align(
                alignment: Alignment.centerLeft,
                child: Btn('Update password', icon: FA.floppyDisk, loading: _f.submitting, loadingText: 'Saving…', onPressed: _save),
              ),
            ]),
          ),
          AppCard(
            title: 'Password rules',
            icon: FA.listCheck,
            trailing: Pill('$ok / 8', tone: ok == 8 ? Tone.success : (ok >= 5 ? Tone.warning : Tone.muted)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              ProgressBar(ok * 100 / 8, color: ok == 8 ? C.green600 : (ok >= 5 ? C.amber600 : C.red600)),
              const SizedBox(height: 8),
              Text(
                v.isEmpty ? 'Start typing your new password…' : (ok == 8 ? 'All rules met — you can update the password.' : 'Strength: ${stateNames[strength]} · ${8 - ok} rule(s) left'),
                style: T.s(13, weight: ok == 8 ? FontWeight.w600 : FontWeight.w400, color: ok == 8 ? C.green600 : C.slate600),
              ),
              const SizedBox(height: 12),
              Text('NEW PASSWORD', style: T.s(11, weight: FontWeight.w700, color: C.muted, spacing: .6)),
              const SizedBox(height: 6),
              for (final r in rules.take(7)) _rule(r.$2, r.$3),
              const SizedBox(height: 10),
              Text('CONFIRM PASSWORD', style: T.s(11, weight: FontWeight.w700, color: C.muted, spacing: .6)),
              const SizedBox(height: 6),
              _rule(rules.last.$2, rules.last.$3),
            ]),
          ),
        ]);
      },
    );
  }

  Widget _rule(String text, bool ok) => Padding(
        padding: const EdgeInsets.only(bottom: 7),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 250),
            transitionBuilder: (child, a) => ScaleTransition(scale: a, child: child),
            child: Icon(ok ? FA.circleCheck : FA.circleXmark, key: ValueKey(ok), size: 14, color: ok ? C.green600 : C.borderStrong),
          ),
          const SizedBox(width: 9),
          Expanded(child: Text(text, style: T.s(13, color: ok ? C.green600 : C.slate600))),
        ]),
      );
}
