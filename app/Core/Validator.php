<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validation (JS validation is only a convenience — this is the authority).
 *
 * $v = Validator::make($data, [
 *     'name'   => 'required|min:3|max:120',
 *     'mobile' => 'required|mobile',
 *     'sla'    => 'required|integer|between:1,365',
 *     'type'   => 'required|in:PLOT,SHOP',
 * ], ['name' => 'Full name']);
 * if ($v->fails()) { $v->errors(); }
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    private function __construct(private readonly array $data, private readonly array $labels)
    {
    }

    /**
     * @param array<string, mixed>  $data
     * @param array<string, string> $rules
     * @param array<string, string> $labels
     */
    public static function make(array $data, array $rules, array $labels = []): self
    {
        $v = new self($data, $labels);
        foreach ($rules as $field => $ruleString) {
            $v->applyRules($field, explode('|', $ruleString));
        }
        return $v;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> field => first error message */
    public function errors(): array
    {
        return $this->errors;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function label(string $field): string
    {
        return $this->labels[$field] ?? ucwords(str_replace('_', ' ', $field));
    }

    private function applyRules(string $field, array $rules): void
    {
        $raw = $this->data[$field] ?? null;
        $value = is_string($raw) ? trim($raw) : $raw;
        $empty = $value === null || $value === '' || $value === [];

        if (in_array('nullable', $rules, true) && $empty) {
            return;
        }

        foreach ($rules as $rule) {
            [$name, $arg] = array_pad(explode(':', $rule, 2), 2, null);
            if ($name === 'nullable') {
                continue;
            }
            if ($name === 'required') {
                if ($empty) {
                    $this->addError($field, $this->label($field) . ' is required.');
                    return;
                }
                continue;
            }
            if ($empty) {
                continue;
            }
            $message = $this->check($name, $arg, $value, $field);
            if ($message !== null) {
                $this->addError($field, $message);
                return;
            }
        }
    }

    private function check(string $name, ?string $arg, mixed $value, string $field): ?string
    {
        $label = $this->label($field);
        $str = is_scalar($value) ? (string) $value : '';

        return match ($name) {
            'min'      => mb_strlen($str) < (int) $arg ? "{$label} must be at least {$arg} characters." : null,
            'max'      => mb_strlen($str) > (int) $arg ? "{$label} may not be more than {$arg} characters." : null,
            'email'    => filter_var($str, FILTER_VALIDATE_EMAIL) === false ? "{$label} must be a valid email address." : null,
            'mobile'   => preg_match('/^[6-9]\d{9}$/', $str) !== 1 ? "{$label} must be a valid 10-digit mobile number." : null,
            'integer'  => preg_match('/^-?\d+$/', $str) !== 1 ? "{$label} must be a whole number." : null,
            'numeric'  => !is_numeric($str) ? "{$label} must be a number." : null,
            'decimal'  => preg_match('/^\d{1,10}(\.\d{1,' . ((int) ($arg ?? 2)) . '})?$/', $str) !== 1
                            ? "{$label} must be a number with up to " . ((int) ($arg ?? 2)) . ' decimal places.' : null,
            'positive' => (!is_numeric($str) || (float) $str <= 0) ? "{$label} must be greater than zero." : null,
            'between'  => $this->between($str, (string) $arg, $label),
            'in'       => !in_array($str, explode(',', (string) $arg), true) ? "Please select a valid {$label}." : null,
            'code'     => preg_match('/^[A-Z][A-Z0-9_]{1,39}$/', $str) !== 1
                            ? "{$label} must start with a letter and contain only CAPITAL letters, digits and underscore." : null,
            'alpha_dash' => preg_match('/^[A-Za-z0-9._-]+$/', $str) !== 1 ? "{$label} may contain only letters, digits, dot, dash and underscore." : null,
            'name'     => preg_match('/^[\p{L}\p{M} .\'()\/-]+$/u', $str) !== 1 ? "{$label} contains invalid characters." : null,
            'text'     => preg_match('/[<>]/', $str) === 1 ? "{$label} may not contain < or > characters." : null,
            'regex'    => preg_match((string) $arg, $str) !== 1 ? "{$label} format is invalid." : null,
            'same'     => $str !== (string) ($this->data[(string) $arg] ?? '') ? "{$label} does not match." : null,
            'ucode'    => preg_match('/^[A-Z0-9][A-Z0-9\/_-]{1,29}$/', $str) !== 1
                            ? "{$label} may contain only CAPITAL letters, digits, / _ - (2–30 characters)." : null,
            'plotno'   => preg_match('/^[A-Za-z0-9][A-Za-z0-9\/_ -]{0,29}$/', $str) !== 1
                            ? "{$label} may contain only letters, digits, space, / _ - (max 30)." : null,
            'year'     => (preg_match('/^\d{4}$/', $str) !== 1 || (int) $str < 1950 || (int) $str > 2100) ? "{$label} must be a valid year (1950–2100)." : null,
            'password' => self::passwordError($str, $label),
            default    => null,
        };
    }

    private function between(string $value, string $arg, string $label): ?string
    {
        [$min, $max] = array_map('floatval', explode(',', $arg));
        if (!is_numeric($value) || (float) $value < $min || (float) $value > $max) {
            return "{$label} must be between " . rtrim(rtrim((string) $min, '0'), '.') . ' and ' . rtrim(rtrim((string) $max, '0'), '.') . '.';
        }
        return null;
    }

    /** Password policy shared by change-password and user master. */
    public static function passwordError(string $password, string $label = 'Password'): ?string
    {
        $min = (int) Config::get('security.password_min_length', 10);
        if (mb_strlen($password) < $min) {
            return "{$label} must be at least {$min} characters.";
        }
        if (mb_strlen($password) > 72) {
            return "{$label} may not be more than 72 characters.";
        }
        if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password)
            || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
            return "{$label} must contain an uppercase letter, a lowercase letter, a digit and a special character.";
        }
        return null;
    }
}
