<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validation with the same rule names as the UI
 * (public/assets/js/validation.js): "required|min:3|max:100|name".
 */
final class Validator
{
    private array $errors = [];
    private array $clean = [];

    public function __construct(private array $data, private array $rules)
    {
    }

    public function passes(): bool
    {
        foreach ($this->rules as $field => $spec) {
            [$label, $ruleStr] = is_array($spec) ? $spec : [label($field), $spec];
            $value = $this->data[$field] ?? null;
            $value = is_string($value) ? trim($value) : $value;
            if (is_array($value)) {
                $this->errors[$field] = $label . ' is invalid.';
                continue;
            }
            $value = (string) ($value ?? '');
            $rules = array_filter(explode('|', $ruleStr));
            $error = $this->check($value, $rules, $label);
            if ($error !== null) {
                $this->errors[$field] = $error;
                continue;
            }
            $this->clean[$field] = $value;
        }
        return !$this->errors;
    }

    /** @return array{0:string,1:string} */
    public function firstError(): array
    {
        $field = (string) array_key_first($this->errors);
        return [$field, $this->errors[$field]];
    }

    public function clean(): array
    {
        return $this->clean;
    }

    private function check(string $v, array $rules, string $label): ?string
    {
        $required = in_array('required', $rules, true);
        if ($v === '') {
            return $required ? $label . ' is required.' : null;
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $v)) {
            return $label . ' contains invalid characters.';
        }
        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            $len = mb_strlen($v);
            $err = match ($name) {
                'required' => null,
                'min'      => $len < (int) $arg ? $label . ' must be at least ' . $arg . ' characters.' : null,
                'max'      => $len > (int) $arg ? $label . ' may not be more than ' . $arg . ' characters.' : null,
                'name'     => !preg_match("/^[\p{L}\p{M} .'()\/-]+$/u", $v) ? $label . ' may contain only letters, spaces and . \' ( ) / -' : null,
                'text'     => preg_match('/[<>]/', $v) ? $label . ' may not contain < or >.' : null,
                'code'     => !preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $v) ? $label . ': use CAPITAL letters, digits and underscore (2–40), starting with a letter.' : null,
                'ucode'    => !preg_match('/^[A-Z0-9][A-Z0-9\/_-]{1,29}$/', $v) ? $label . ' may contain only CAPITAL letters, digits, / _ - (2–30 characters).' : null,
                'alpha_dash' => !preg_match('/^[A-Za-z0-9_.-]+$/', $v) ? $label . ' may contain only letters, digits, dot, dash and underscore.' : null,
                'mobile'   => !preg_match('/^[6-9]\d{9}$/', $v) ? $label . ' must be a valid 10-digit mobile number.' : null,
                'email'    => filter_var($v, FILTER_VALIDATE_EMAIL) === false ? $label . ' must be a valid email address.' : null,
                'integer'  => !preg_match('/^-?\d{1,9}$/', $v) ? $label . ' must be a whole number.' : null,
                'between'  => $this->between($v, (string) $arg, $label),
                'year'     => (!preg_match('/^\d{4}$/', $v) || (int) $v < 1950 || (int) $v > 2100) ? $label . ' must be a year between 1950 and 2100.' : null,
                'decimal'  => !preg_match('/^\d{1,10}(\.\d{1,' . max(1, (int) $arg) . '})?$/', $v) ? $label . ' must be a number with at most ' . (int) $arg . ' decimals.' : null,
                'positive' => (!is_numeric($v) || (float) $v <= 0) ? $label . ' must be greater than zero.' : null,
                'plotno'   => !preg_match('/^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/', $v) ? $label . ' may contain only letters, digits, space, / _ - (max 30).' : null,
                'password' => Password::problem($v),
                'in'       => !in_array($v, explode(',', (string) $arg), true) ? 'Please select a valid ' . strtolower($label) . '.' : null,
                'date'     => !self::isDate($v) ? $label . ' must be a valid date.' : null,
                'url'      => (!preg_match('~^https?://[^\s<>"\']+$~i', $v) || filter_var($v, FILTER_VALIDATE_URL) === false) ? $label . ' must be a valid web address (https://…).' : null,
                'csvlist'  => !preg_match('/^[a-z0-9_.-]+(\s*,\s*[a-z0-9_.-]+)*$/i', $v) ? $label . ': use usernames separated by commas.' : null,
                default    => null,
            };
            if ($err !== null) {
                return $err;
            }
        }
        return null;
    }

    private function between(string $v, string $arg, string $label): ?string
    {
        [$min, $max] = array_map('intval', explode(',', $arg . ','));
        if (!preg_match('/^-?\d+$/', $v) || (int) $v < $min || (int) $v > $max) {
            return $label . ' must be between ' . $min . ' and ' . $max . '.';
        }
        return null;
    }

    public static function isDate(string $v): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            return false;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
