// PRAMAN — toast + confirm dialog (Praman.toast / Praman.confirm in app.js).
import 'package:flutter/material.dart';

import '../app/fa.dart';
import '../app/theme.dart';

class ToastView extends StatelessWidget {
  final String type; // success | error | warning | info
  final String message;
  const ToastView({super.key, required this.type, required this.message});

  @override
  Widget build(BuildContext context) {
    final tone = Tone.of(type == 'error' ? 'danger' : type);
    final IconData icon = type == 'success'
        ? FA.circleCheck
        : type == 'error'
            ? FA.circleExclamation
            : type == 'warning'
                ? FA.triangleExclamation
                : FA.circleInfo;
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border(left: BorderSide(color: tone.fg, width: 4)),
        boxShadow: const [BoxShadow(color: Color(0x290F2B52), blurRadius: 24, offset: Offset(0, 10))],
      ),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(padding: const EdgeInsets.only(top: 2), child: Icon(icon, size: 16, color: tone.fg)),
          const SizedBox(width: 10),
          Expanded(child: Text(message, style: T.s(13.5, color: C.text, height: 1.4))),
        ],
      ),
    );
  }
}

/// Styled confirm dialog — returns true when the user presses the OK button.
class ConfirmDialog extends StatelessWidget {
  final String title;
  final String message;
  final String okText;
  final String cancelText;
  final String variant; // primary | danger | success | warning
  const ConfirmDialog({super.key, required this.title, required this.message, this.okText = 'Yes, continue', this.cancelText = 'No, cancel', this.variant = 'primary'});

  @override
  Widget build(BuildContext context) {
    final Color okColor = variant == 'danger'
        ? C.red600
        : variant == 'success'
            ? C.green600
            : variant == 'warning'
                ? C.amber600
                : C.navy700;
    return Dialog(
      backgroundColor: Colors.white,
      surfaceTintColor: Colors.white,
      insetPadding: const EdgeInsets.symmetric(horizontal: 22, vertical: 24),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 16),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(color: C.gold100, borderRadius: BorderRadius.circular(12)),
                alignment: Alignment.center,
                child: const Icon(FA.circleQuestion, size: 18, color: C.gold600),
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(title, style: T.s(17, weight: FontWeight.w700, color: C.navy800))),
            ]),
            const SizedBox(height: 14),
            Text(message, style: T.s(14, color: C.slate600, height: 1.5)),
            const SizedBox(height: 20),
            Wrap(
              alignment: WrapAlignment.end,
              spacing: 10,
              runSpacing: 8,
              children: [
                OutlinedButton.icon(
                  onPressed: () => Navigator.of(context).pop(false),
                  icon: const Icon(FA.xmark, size: 14),
                  label: Text(cancelText),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: C.text,
                    side: const BorderSide(color: C.borderStrong),
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
                FilledButton.icon(
                  onPressed: () => Navigator.of(context).pop(true),
                  icon: const Icon(FA.check, size: 14),
                  label: Text(okText),
                  style: FilledButton.styleFrom(
                    backgroundColor: okColor,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
