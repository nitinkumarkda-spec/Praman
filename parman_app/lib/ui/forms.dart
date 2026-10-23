// PRAMAN — form kit: values + errors + validation (data-rules), field widgets and the submit pipeline
// (validate -> confirm -> loading -> action) from app.js / validation.js.
import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../app/fa.dart';
import '../app/format.dart';
import '../app/store.dart';
import '../app/theme.dart';
import '../app/validation.dart';

/// Custom form-level check: returns (field or null, message) when it fails.
typedef FormCheck = (String?, String)? Function();

class _Spec {
  final String label;
  final String rules;
  final bool choice;
  const _Spec(this.label, this.rules, this.choice);
}

class FormModel extends ChangeNotifier {
  final Map<String, TextEditingController> _ctl = {};
  final Map<String, FocusNode> _focus = {};
  final Map<String, String> _vals = {};
  final Map<String, _Spec> _specs = {};
  final Map<String, GlobalKey> _keys = {};
  final Map<String, String?> errors = {};
  final Set<String> _flags = {};
  bool submitting = false;
  bool _disposed = false;

  FormModel([Map<String, Object?> initial = const {}]) {
    initial.forEach((k, v) => _vals[k] = v == null ? '' : v.toString());
  }

  TextEditingController ctl(String name) {
    return _ctl.putIfAbsent(name, () {
      final c = TextEditingController(text: _vals[name] ?? '');
      c.addListener(() {
        if (_vals[name] == c.text) return;
        _vals[name] = c.text;
        if (errors[name] != null) validate(name);
        notify();
      });
      return c;
    });
  }

  FocusNode focus(String name) {
    return _focus.putIfAbsent(name, () {
      final f = FocusNode();
      f.addListener(() {
        if (!f.hasFocus && _specs.containsKey(name)) {
          validate(name);
          notify();
        }
      });
      return f;
    });
  }

  GlobalKey keyOf(String name) => _keys.putIfAbsent(name, () => GlobalKey());

  /// Field widgets register their rules while building (data-rules / data-label).
  void register(String name, String label, String rules, {bool choice = false}) {
    _specs[name] = _Spec(label, rules, choice);
  }

  String value(String name) => _vals[name] ?? '';
  String trimmed(String name) => value(name).trim();
  bool isOn(String name) => value(name) == '1';

  /// Set a value from code / select / checkbox (text fields update their controller too).
  void set(String name, Object? v, {bool silent = false}) {
    final s = v == null ? '' : v.toString();
    _vals[name] = s;
    final c = _ctl[name];
    if (c != null && c.text != s) {
      c.value = TextEditingValue(text: s, selection: TextSelection.collapsed(offset: s.length));
    }
    if (errors[name] != null) validate(name);
    if (!silent) notify();
  }

  bool flag(String f) => _flags.contains(f);
  void toggleFlag(String f) {
    if (!_flags.remove(f)) _flags.add(f);
    notify();
  }

  String? validate(String name) {
    final s = _specs[name];
    if (s == null) return null;
    final m = Validator.check(value(name), s.rules, s.label, choice: s.choice, other: (o) => value(o));
    errors[name] = m;
    return m;
  }

  void setError(String name, String? message) {
    errors[name] = message;
    notify();
  }

  void clearError(String name) {
    if (errors[name] != null) {
      errors[name] = null;
      notify();
    }
  }

  /// Validate every registered field (minus [exclude]) and the custom [checks].
  /// On failure: error toast, scroll to + focus the first invalid field.
  bool validateAll({Set<String> exclude = const {}, List<FormCheck> checks = const []}) {
    String? firstName;
    String? firstMsg;
    for (final e in _specs.entries) {
      if (exclude.contains(e.key)) {
        errors.remove(e.key);
        continue;
      }
      final m = validate(e.key);
      if (m != null && firstMsg == null) {
        firstName = e.key;
        firstMsg = m;
      }
    }
    for (final c in checks) {
      final r = c();
      if (r == null) continue;
      if (r.$1 != null) errors[r.$1!] = r.$2;
      if (firstMsg == null) {
        firstName = r.$1;
        firstMsg = r.$2;
      }
    }
    notify();
    if (firstMsg == null) return true;
    P.toast('error', firstMsg);
    if (firstName != null) reveal(firstName);
    return false;
  }

  /// Server-side style error (P.fail): mark the field, toast, unlock the form.
  void fail(String? field, String message) {
    if (field != null) {
      errors[field] = message;
      reveal(field);
    }
    submitting = false;
    P.toast('error', message);
    notify();
  }

  void reveal(String name) {
    final ctx = _keys[name]?.currentContext;
    if (ctx != null) {
      Scrollable.ensureVisible(ctx, duration: const Duration(milliseconds: 300), alignment: .2, curve: Curves.easeOut);
    }
    final f = _focus[name];
    if (f != null) f.requestFocus();
  }

  void notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    for (final c in _ctl.values) {
      c.dispose();
    }
    for (final f in _focus.values) {
      f.dispose();
    }
    super.dispose();
  }

  bool get disposed => _disposed;
}

/// validate -> confirm -> loading -> action (app.js submit pipeline).
Future<void> submitForm(FormModel form, {
  Set<String> exclude = const {},
  List<FormCheck> checks = const [],
  String? confirm,
  String confirmTitle = 'Please confirm',
  String okText = 'Yes, continue',
  String variant = 'primary',
  required FutureOr<void> Function() action,
}) async {
  if (form.submitting) return;
  if (!form.validateAll(exclude: exclude, checks: checks)) return;
  if (confirm != null) {
    final yes = await P.confirm(title: confirmTitle, message: confirm, okText: okText, variant: variant);
    if (!yes) return;
  }
  form.submitting = true;
  form.notify();
  await Future<void>.delayed(const Duration(milliseconds: 220)); // like a real request
  try {
    await action();
  } catch (e) {
    form.fail(null, 'Unexpected error: $e');
  }
  if (!form.disposed && form.submitting) {
    form.submitting = false;
    form.notify();
  }
}

/// Confirm + small delay + action, for one-click actions without a form (toggles, deletes …).
Future<void> confirmAction({String? confirm, String title = 'Please confirm', String okText = 'Yes, continue', String variant = 'primary', required void Function() action}) async {
  if (confirm != null) {
    final yes = await P.confirm(title: title, message: confirm, okText: okText, variant: variant);
    if (!yes) return;
  }
  await Future<void>.delayed(const Duration(milliseconds: 160));
  action();
}

/* ======================= field widgets ======================= */

InputDecoration inputDeco({String? hint, bool error = false, IconData? prefix, Widget? suffix, bool readOnly = false}) {
  OutlineInputBorder b(Color c, [double w = 1]) => OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide(color: c, width: w));
  return InputDecoration(
    isDense: true,
    hintText: hint,
    hintStyle: T.s(14, color: C.slate400),
    filled: true,
    fillColor: readOnly ? C.slate100 : Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
    prefixIcon: prefix == null ? null : Icon(prefix, size: 15, color: C.slate600),
    prefixIconConstraints: const BoxConstraints(minWidth: 40, minHeight: 40),
    suffixIcon: suffix,
    counterText: '',
    border: b(C.borderStrong),
    enabledBorder: b(error ? C.red600 : C.borderStrong),
    focusedBorder: b(error ? C.red600 : C.lpBlue, 1.6),
    disabledBorder: b(C.border),
  );
}

class UpperCaseFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) => newValue.copyWith(text: newValue.text.toUpperCase());
}

class FieldLabel extends StatelessWidget {
  final String text;
  final bool required;
  final Widget? leading;
  final String? extra;
  const FieldLabel(this.text, {super.key, this.required = false, this.leading, this.extra});
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
          if (leading != null) leading!,
          Expanded(
            child: Text.rich(TextSpan(children: [
              TextSpan(text: text, style: T.label),
              if (required) TextSpan(text: ' *', style: T.s(13.5, weight: FontWeight.w700, color: C.red600)),
              if (extra != null) TextSpan(text: '  $extra', style: T.tiny),
            ])),
          ),
        ]),
      );
}

class FieldError extends StatelessWidget {
  final String? message;
  const FieldError(this.message, {super.key});
  @override
  Widget build(BuildContext context) {
    if (message == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 5),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Padding(padding: EdgeInsets.only(top: 2), child: Icon(FA.circleExclamation, size: 11, color: C.red600)),
        const SizedBox(width: 6),
        Expanded(child: Text(message!, style: T.s(12.5, color: C.red600, height: 1.35))),
      ]),
    );
  }
}

class FieldHint extends StatelessWidget {
  final String text;
  const FieldHint(this.text, {super.key});
  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.only(top: 5), child: Text(text, style: T.s(12, color: C.muted, height: 1.35)));
}

/// Text / number / password / textarea input.
class FText extends StatelessWidget {
  final FormModel form;
  final String name;
  final String label;
  final String rules;
  final String? hint;
  final String? placeholder;
  final TextInputType? keyboard;
  final int? maxLength;
  final bool upper;
  final bool readOnly;
  final bool password;
  final int maxLines;
  final IconData? prefix;
  final bool showLabel;
  final Widget? labelLeading;
  final String? labelExtra;
  final bool? requiredMark;
  final ValueChanged<String>? onSubmitted;
  final TextInputAction? action;
  const FText({
    super.key,
    required this.form,
    required this.name,
    required this.label,
    this.rules = '',
    this.hint,
    this.placeholder,
    this.keyboard,
    this.maxLength,
    this.upper = false,
    this.readOnly = false,
    this.password = false,
    this.maxLines = 1,
    this.prefix,
    this.showLabel = true,
    this.labelLeading,
    this.labelExtra,
    this.requiredMark,
    this.onSubmitted,
    this.action,
  });

  @override
  Widget build(BuildContext context) {
    if (!readOnly && rules.isNotEmpty) form.register(name, label, rules);
    final err = form.errors[name];
    final show = form.flag('show:$name');
    return Container(
      key: form.keyOf(name),
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (showLabel) FieldLabel(label, required: requiredMark ?? rules.split('|').contains('required'), leading: labelLeading, extra: labelExtra),
        TextField(
          controller: form.ctl(name),
          focusNode: form.focus(name),
          readOnly: readOnly,
          keyboardType: keyboard ?? (maxLines > 1 ? TextInputType.multiline : TextInputType.text),
          textInputAction: action ?? (maxLines > 1 ? TextInputAction.newline : TextInputAction.next),
          maxLength: maxLength,
          maxLines: password ? 1 : maxLines,
          minLines: maxLines > 1 ? maxLines : null,
          obscureText: password && !show,
          enableSuggestions: !password,
          autocorrect: false,
          textCapitalization: upper ? TextCapitalization.characters : TextCapitalization.none,
          inputFormatters: upper ? [UpperCaseFormatter()] : null,
          onSubmitted: onSubmitted,
          style: T.s(14.5, color: readOnly ? C.slate600 : C.text),
          decoration: inputDeco(
            hint: placeholder,
            error: err != null,
            prefix: prefix,
            readOnly: readOnly,
            suffix: password
                ? IconButton(
                    onPressed: () => form.toggleFlag('show:$name'),
                    icon: Icon(show ? FA.eyeSlash : FA.eye, size: 16, color: C.slate600),
                    tooltip: show ? 'Hide password' : 'Show password',
                  )
                : null,
          ),
        ),
        if (hint != null) FieldHint(hint!),
        FieldError(err),
      ]),
    );
  }
}

/// Select (dropdown) — opens a bottom-sheet picker. Value lives in the form (e.g. '3').
class FSelect extends StatelessWidget {
  final FormModel form;
  final String name;
  final String label;
  final Map<String, String> options;
  final String rules;
  final String? placeholder;
  final String? hint;
  final bool enabled;
  final ValueChanged<String>? onChanged;
  final bool showLabel;
  final Widget? labelLeading;
  const FSelect({super.key, required this.form, required this.name, required this.label, required this.options, this.rules = '', this.placeholder, this.hint, this.enabled = true, this.onChanged, this.showLabel = true, this.labelLeading});

  @override
  Widget build(BuildContext context) {
    if (enabled && rules.isNotEmpty) form.register(name, label, rules, choice: true);
    final err = form.errors[name];
    final v = form.value(name);
    final text = options[v];
    return Container(
      key: form.keyOf(name),
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (showLabel) FieldLabel(label, required: rules.split('|').contains('required'), leading: labelLeading),
        SelectBox(
          text: text ?? (placeholder ?? 'Select'),
          isPlaceholder: text == null,
          error: err != null,
          enabled: enabled,
          onTap: () async {
            final picked = await pickOption(context, title: label, options: options, selected: v, placeholder: placeholder);
            if (picked == null) return;
            form.set(name, picked);
            form.validate(name);
            form.notify();
            if (onChanged != null) onChanged!(picked);
          },
        ),
        if (hint != null) FieldHint(hint!),
        FieldError(err),
      ]),
    );
  }
}

/// The closed select box (also used by the list toolbar filters).
class SelectBox extends StatelessWidget {
  final String text;
  final bool isPlaceholder;
  final bool error;
  final bool enabled;
  final VoidCallback onTap;
  final bool compact;
  final IconData? icon;
  const SelectBox({super.key, required this.text, required this.onTap, this.isPlaceholder = false, this.error = false, this.enabled = true, this.compact = false, this.icon});

  @override
  Widget build(BuildContext context) {
    return Opacity(
      opacity: enabled ? 1 : .6,
      child: Material(
        color: enabled ? Colors.white : C.slate100,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10), side: BorderSide(color: error ? C.red600 : C.borderStrong)),
        child: InkWell(
          borderRadius: BorderRadius.circular(10),
          onTap: enabled ? onTap : null,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: compact ? 9 : 13),
            child: Row(children: [
              if (icon != null) Padding(padding: const EdgeInsets.only(right: 8), child: Icon(icon, size: 13, color: C.slate600)),
              Expanded(
                child: Text(text, maxLines: 1, overflow: TextOverflow.ellipsis, style: T.s(compact ? 13 : 14.5, color: isPlaceholder ? C.slate400 : C.text)),
              ),
              const Icon(FA.chevronDown, size: 11, color: C.slate600),
            ]),
          ),
        ),
      ),
    );
  }
}

/// Bottom-sheet option picker. Returns the chosen key ('' = placeholder / clear) or null when dismissed.
Future<String?> pickOption(BuildContext context, {required String title, required Map<String, String> options, String? selected, String? placeholder}) {
  return showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.white,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
    builder: (c) => _OptionSheet(title: title, options: options, selected: selected, placeholder: placeholder),
  );
}

class _OptionSheet extends StatefulWidget {
  final String title;
  final Map<String, String> options;
  final String? selected;
  final String? placeholder;
  const _OptionSheet({required this.title, required this.options, this.selected, this.placeholder});
  @override
  State<_OptionSheet> createState() => _OptionSheetState();
}

class _OptionSheetState extends State<_OptionSheet> {
  String _q = '';

  @override
  Widget build(BuildContext context) {
    final entries = widget.options.entries.where((e) => _q.isEmpty || e.value.toLowerCase().contains(_q.toLowerCase())).toList();
    final maxH = MediaQuery.of(context).size.height * .75;
    return SafeArea(
      child: Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
        child: ConstrainedBox(
          constraints: BoxConstraints(maxHeight: maxH),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Center(child: Container(margin: const EdgeInsets.only(top: 10), width: 40, height: 4, decoration: BoxDecoration(color: C.border, borderRadius: BorderRadius.circular(4)))),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 14, 18, 8),
              child: Text(widget.title, style: T.s(16, weight: FontWeight.w700, color: C.navy800)),
            ),
            if (widget.options.length > 10)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
                child: TextField(
                  autofocus: false,
                  onChanged: (v) => setState(() => _q = v),
                  decoration: inputDeco(hint: 'Search…', prefix: FA.magnifyingGlass),
                ),
              ),
            Flexible(
              child: ListView(
                shrinkWrap: true,
                padding: const EdgeInsets.only(bottom: 12),
                children: [
                  if (widget.placeholder != null && _q.isEmpty) _row('', widget.placeholder!, muted: true),
                  for (final e in entries) _row(e.key, e.value),
                  if (entries.isEmpty) Padding(padding: const EdgeInsets.all(20), child: Text('No matching option', textAlign: TextAlign.center, style: T.small)),
                ],
              ),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _row(String key, String text, {bool muted = false}) {
    final on = (widget.selected ?? '') == key;
    return InkWell(
      onTap: () => Navigator.of(context).pop(key),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
        color: on ? C.navy50 : null,
        child: Row(children: [
          Expanded(child: Text(text, style: T.s(14.5, weight: on ? FontWeight.w600 : FontWeight.w400, color: muted ? C.muted : (on ? C.navy700 : C.text)))),
          if (on) const Icon(FA.check, size: 14, color: C.navy700),
        ]),
      ),
    );
  }
}

/// Checkbox row (.check) — value '1' when ticked.
class FCheck extends StatelessWidget {
  final FormModel form;
  final String name;
  final String title;
  final String? subtitle;
  final bool enabled;
  final String rules;
  final String? label;
  final ValueChanged<bool>? onChanged;
  const FCheck({super.key, required this.form, required this.name, required this.title, this.subtitle, this.enabled = true, this.rules = '', this.label, this.onChanged});

  @override
  Widget build(BuildContext context) {
    if (enabled && rules.isNotEmpty) form.register(name, label ?? title, rules, choice: true);
    final on = form.isOn(name);
    final err = form.errors[name];
    return Container(
      key: form.keyOf(name),
      margin: const EdgeInsets.only(bottom: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        CheckRow(
          value: on,
          enabled: enabled,
          title: title,
          subtitle: subtitle,
          error: err != null,
          onChanged: (v) {
            form.set(name, v ? '1' : '');
            if (onChanged != null) onChanged!(v);
          },
        ),
        FieldError(err),
      ]),
    );
  }
}

/// A tappable row with a checkbox, title and optional subtitle (no form needed).
class CheckRow extends StatelessWidget {
  final bool value;
  final bool? tristate; // null value shown as indeterminate when true
  final bool enabled;
  final String title;
  final String? subtitle;
  final bool error;
  final ValueChanged<bool> onChanged;
  final bool dense;
  const CheckRow({super.key, required this.value, required this.onChanged, required this.title, this.subtitle, this.enabled = true, this.error = false, this.tristate, this.dense = false});

  @override
  Widget build(BuildContext context) {
    final indeterminate = tristate == true;
    return InkWell(
      onTap: enabled ? () => onChanged(!value) : null,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: dense ? 4 : 6),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 160),
            margin: const EdgeInsets.only(top: 1, right: 10),
            width: 20,
            height: 20,
            decoration: BoxDecoration(
              color: value || indeterminate ? C.navy700 : Colors.white,
              borderRadius: BorderRadius.circular(5),
              border: Border.all(color: error ? C.red600 : (value || indeterminate ? C.navy700 : C.borderStrong), width: 1.5),
            ),
            alignment: Alignment.center,
            child: indeterminate
                ? Container(width: 9, height: 2, color: Colors.white)
                : (value ? const Icon(FA.check, size: 11, color: Colors.white) : null),
          ),
          Expanded(
            child: Opacity(
              opacity: enabled ? 1 : .55,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(title, style: T.s(dense ? 13.5 : 14, weight: FontWeight.w600, color: C.text)),
                if (subtitle != null) Text(subtitle!, style: T.s(12, color: C.muted, height: 1.35)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}

/// Radio-style choice (Yes / No …) — value stored in the form.
class FChoice extends StatelessWidget {
  final FormModel form;
  final String name;
  final String? label;
  final List<(String, String)> options; // value, text
  final String rules;
  final String? ruleLabel;
  final ValueChanged<String>? onChanged;
  final bool showLabel;
  final Widget? labelLeading;
  const FChoice({super.key, required this.form, required this.name, required this.options, this.label, this.rules = '', this.ruleLabel, this.onChanged, this.showLabel = true, this.labelLeading});

  @override
  Widget build(BuildContext context) {
    if (rules.isNotEmpty) form.register(name, ruleLabel ?? label ?? name, rules, choice: true);
    final v = form.value(name);
    final err = form.errors[name];
    return Container(
      key: form.keyOf(name),
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        if (showLabel && label != null) FieldLabel(label!, required: rules.contains('required'), leading: labelLeading),
        Wrap(spacing: 10, runSpacing: 8, children: [
          for (final o in options)
            RadioPill(
              text: o.$2,
              selected: v == o.$1,
              error: err != null,
              onTap: () {
                form.set(name, o.$1);
                if (onChanged != null) onChanged!(o.$1);
              },
            ),
        ]),
        FieldError(err),
      ]),
    );
  }
}

class RadioPill extends StatelessWidget {
  final String text;
  final bool selected;
  final bool error;
  final VoidCallback onTap;
  const RadioPill({super.key, required this.text, required this.selected, required this.onTap, this.error = false});
  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? C.navy50 : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
        side: BorderSide(color: selected ? C.navy700 : (error ? C.red600 : C.borderStrong), width: selected ? 1.6 : 1),
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 160),
              width: 18,
              height: 18,
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: selected ? C.navy700 : C.borderStrong, width: selected ? 5 : 1.5)),
            ),
            const SizedBox(width: 9),
            Text(text, style: T.s(14, weight: selected ? FontWeight.w600 : FontWeight.w500, color: C.text)),
          ]),
        ),
      ),
    );
  }
}

/// Date field (yyyy-mm-dd) with the Material date picker.
class FDate extends StatelessWidget {
  final FormModel form;
  final String name;
  final String label;
  final String rules;
  final DateTime? last;
  final bool enabled;
  final ValueChanged<String>? onChanged;
  const FDate({super.key, required this.form, required this.name, required this.label, this.rules = '', this.last, this.enabled = true, this.onChanged});

  @override
  Widget build(BuildContext context) {
    if (enabled && rules.isNotEmpty) form.register(name, label, rules, choice: true);
    final v = form.value(name);
    final err = form.errors[name];
    return Container(
      key: form.keyOf(name),
      margin: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        FieldLabel(label, required: rules.contains('required')),
        SelectBox(
          text: v.isEmpty ? 'dd-mm-yyyy' : fmtDate(v),
          isPlaceholder: v.isEmpty,
          error: err != null,
          enabled: enabled,
          icon: FA.calendar,
          onTap: () async {
            final now = DateTime.now();
            final initial = parseDate(v) ?? (last != null && last!.isBefore(now) ? last! : now);
            final d = await showDatePicker(
              context: context,
              initialDate: initial,
              firstDate: DateTime(1950),
              lastDate: last ?? DateTime(2100),
            );
            if (d == null) return;
            final s = sqlDate(d);
            form.set(name, s);
            if (onChanged != null) onChanged!(s);
          },
        ),
        FieldError(err),
      ]),
    );
  }
}
