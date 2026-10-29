// PRAMAN — field validation rules (port of js/validation.js — mirrors App\Core\Validator).
// A spec is the same string the web form uses in data-rules, e.g. 'required|min:3|max:50|name'.

class Validator {
  Validator._();
  static const int passwordMin = 10;

  static final RegExp _email = RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]{2,}$');
  static final RegExp _mobile = RegExp(r'^[6-9]\d{9}$');
  static final RegExp _integer = RegExp(r'^-?\d+$');
  static final RegExp _code = RegExp(r'^[A-Z][A-Z0-9_]{1,39}$');
  static final RegExp _alphaDash = RegExp(r'^[A-Za-z0-9._-]+$');
  static final RegExp _name = RegExp(r"^[\p{L}\p{M} .'()/-]+$", unicode: true);
  static final RegExp _angle = RegExp(r'[<>]');
  static final RegExp _ucode = RegExp(r'^[A-Z0-9][A-Z0-9/_-]{1,29}$');
  static final RegExp _plotno = RegExp(r'^[A-Za-z0-9][A-Za-z0-9/_ -]{0,29}$');
  static final RegExp _year = RegExp(r'^\d{4}$');
  static final RegExp _upper = RegExp(r'[A-Z]');
  static final RegExp _lower = RegExp(r'[a-z]');
  static final RegExp _digit = RegExp(r'\d');
  static final RegExp _special = RegExp(r'[^A-Za-z0-9]');

  static bool hasAngle(String v) => _angle.hasMatch(v);
  static bool isName(String v) => _name.hasMatch(v);

  static String? passwordError(String v, String l) {
    if (v.length < passwordMin) return '$l must be at least $passwordMin characters.';
    if (v.length > 72) return '$l may not be more than 72 characters.';
    if (!_upper.hasMatch(v) || !_lower.hasMatch(v) || !_digit.hasMatch(v) || !_special.hasMatch(v)) {
      return '$l must contain an uppercase letter, a lowercase letter, a digit and a special character.';
    }
    return null;
  }

  /// Password strength 0–5 (length, upper, lower, digit, special).
  static int strength(String v) {
    var s = 0;
    if (v.length >= passwordMin) s++;
    if (_upper.hasMatch(v)) s++;
    if (_lower.hasMatch(v)) s++;
    if (_digit.hasMatch(v)) s++;
    if (_special.hasMatch(v)) s++;
    return s;
  }

  static Map<String, bool> passwordChecks(String v) => {
        'length': v.length >= passwordMin,
        'upper': _upper.hasMatch(v),
        'lower': _lower.hasMatch(v),
        'digit': _digit.hasMatch(v),
        'special': _special.hasMatch(v),
      };

  /// Validate one value against a rule spec. [choice] = select / radio / checkbox ("Please select …").
  /// [other] returns another field's raw value (for `same:field`).
  static String? check(String raw, String spec, String label, {bool choice = false, String Function(String name)? other}) {
    final list = spec.split('|').where((s) => s.isNotEmpty).toList();
    final v = raw.trim();
    if (v.isEmpty) {
      if (list.contains('required')) return choice ? 'Please select $label.' : '$label is required.';
      return null;
    }
    for (final item in list) {
      final i = item.indexOf(':');
      final name = i == -1 ? item : item.substring(0, i);
      final arg = i == -1 ? '' : item.substring(i + 1);
      final msg = _rule(name, v, arg, label, raw, other);
      if (msg != null) return msg;
    }
    return null;
  }

  static String? _rule(String name, String v, String a, String l, String raw, String Function(String name)? other) {
    switch (name) {
      case 'min':
        return v.length < (int.tryParse(a) ?? 0) ? '$l must be at least $a characters.' : null;
      case 'max':
        return v.length > (int.tryParse(a) ?? 1 << 30) ? '$l may not be more than $a characters.' : null;
      case 'email':
        return _email.hasMatch(v) ? null : '$l must be a valid email address.';
      case 'mobile':
        return _mobile.hasMatch(v) ? null : '$l must be a valid 10-digit mobile number.';
      case 'integer':
        return _integer.hasMatch(v) ? null : '$l must be a whole number.';
      case 'numeric':
        return double.tryParse(v) != null ? null : '$l must be a number.';
      case 'decimal':
        {
          final d = int.tryParse(a.isEmpty ? '2' : a) ?? 2;
          return RegExp('^\\d{1,10}(\\.\\d{1,$d})?\$').hasMatch(v) ? null : '$l must be a number with up to $d decimal places.';
        }
      case 'positive':
        {
          final n = double.tryParse(v);
          return n != null && n > 0 ? null : '$l must be greater than zero.';
        }
      case 'between':
        {
          final p = a.split(',');
          final n = double.tryParse(v);
          final lo = double.tryParse(p[0]) ?? 0;
          final hi = double.tryParse(p.length > 1 ? p[1] : '0') ?? 0;
          return n != null && n >= lo && n <= hi ? null : '$l must be between ${p[0]} and ${p.length > 1 ? p[1] : ''}.';
        }
      case 'in':
        return a.split(',').contains(v) ? null : 'Please select a valid $l.';
      case 'code':
        return _code.hasMatch(v) ? null : '$l must start with a letter and contain only CAPITAL letters, digits and underscore.';
      case 'alpha_dash':
        return _alphaDash.hasMatch(v) ? null : '$l may contain only letters, digits, dot, dash and underscore.';
      case 'name':
        return _name.hasMatch(v) ? null : '$l contains invalid characters.';
      case 'text':
        return _angle.hasMatch(v) ? '$l may not contain < or > characters.' : null;
      case 'ucode':
        return _ucode.hasMatch(v) ? null : '$l may contain only CAPITAL letters, digits, / _ - (2–30 characters).';
      case 'plotno':
        return _plotno.hasMatch(v) ? null : '$l may contain only letters, digits, space, / _ - (max 30).';
      case 'year':
        {
          final y = int.tryParse(v) ?? 0;
          return _year.hasMatch(v) && y >= 1950 && y <= 2100 ? null : '$l must be a valid year (1950–2100).';
        }
      case 'password':
        return passwordError(v, l);
      case 'same':
        {
          final o = other == null ? null : other(a);
          return o != null && o == raw ? null : '$l does not match.';
        }
      default:
        return null;
    }
  }
}
