<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\Setting;
use App\Core\Logger;
use Throwable;

/**
 * Application settings stored in the `settings` table and edited by the
 * Super Admin (App Settings screen). Branding, landing page texts, login
 * page, security and verification limits all come from here.
 */
final class Settings
{
    /**
     * key => [group, label, type (text|int|bool|url), Validator rules, public (sent to guests), default, hint]
     */
    public const DEFS = [
        'app_name'        => ['branding', 'Application name', 'text', 'required|min:2|max:30|text', true, 'PRAMAAN', 'Shown in the header, page titles and notifications.'],
        'app_tagline'     => ['branding', 'Tagline', 'text', 'required|min:3|max:60|text', true, 'Property Verification System', 'Shown under the name in every header.'],
        'app_full_name'   => ['branding', 'Full name', 'text', 'required|min:3|max:150|text', true, 'Property Records & Auction-readiness Multi-section Assessment Network', null],
        'org_name'        => ['branding', 'Organisation', 'text', 'required|min:3|max:100|text', true, 'Kota Development Authority', null],
        'org_short'       => ['branding', 'Organisation short name', 'text', 'required|min:2|max:15|text', true, 'KDA', null],
        'org_website'     => ['branding', 'Organisation website', 'url', 'required|max:150|url', true, 'https://kdakota.rajasthan.gov.in/', 'Link in the landing page footer (https://…).'],
        'copyright_note'  => ['branding', 'Copyright note', 'text', 'required|max:120|text', true, 'Govt. of Rajasthan. All Rights Reserved.', null],

        'header_note'     => ['landing', 'Header note', 'text', 'required|max:80|text', true, 'Secure portal · authorised KDA personnel', null],
        'hero_eyebrow'    => ['landing', 'Hero label', 'text', 'required|max:80|text', true, 'Property verification before every auction', null],
        'hero_line1'      => ['landing', 'Headline — line 1', 'text', 'required|max:40|text', true, 'Every vacant property.', null],
        'hero_line2'      => ['landing', 'Headline — line 2 (highlighted)', 'text', 'required|max:40|text', true, 'Verified &', null],
        'hero_line3'      => ['landing', 'Headline — line 3 (highlighted)', 'text', 'required|max:40|text', true, 'auction-ready.', null],


        'login_max_attempts'   => ['security', 'Wrong passwords before lock', 'int', 'required|integer|between:3,10', false, '5', null],
        'lockout_minutes'      => ['security', 'Lock duration (minutes)', 'int', 'required|integer|between:1,1440', false, '15', null],
        'ip_max_attempts'      => ['security', 'Failed logins per IP (15 min)', 'int', 'required|integer|between:5,200', false, '20', null],
        'password_min_length'  => ['security', 'Minimum password length', 'int', 'required|integer|between:8,32', true, '10', null],
        'idle_timeout_minutes' => ['security', 'Auto logout after idle (minutes)', 'int', 'required|integer|between:5,240', false, '30', null],

        'validity_days'       => ['verification', 'Auction Ready validity (days)', 'int', 'required|integer|between:30,730', true, '180', null],
        'reverify_alert_days' => ['verification', 'Re-verification alert before expiry (days)', 'int', 'required|integer|between:1,90', true, '15', null],

        'poll_seconds'     => ['system', 'Refresh notifications every (seconds)', 'int', 'required|integer|between:30,600', true, '60', null],
    ];

    public const GROUPS = [
        'branding' => ['Branding', 'fa-id-badge'], 'landing' => ['Landing page', 'fa-house'],
        'security' => ['Security', 'fa-shield-halved'], 'verification' => ['Verification', 'fa-list-check'], 'system' => ['System', 'fa-gear'],
    ];

    private static ?array $values = null;

    /** Load from the DB (defaults when the table is missing or empty). */
    public static function all(): array
    {
        if (self::$values === null) {
            $v = array_map(static fn ($d) => $d[5], self::DEFS);
            try {
                foreach (Setting::select(['setting_key', 'setting_value']) as $r) {
                    if (isset(self::DEFS[$r['setting_key']])) {
                        $v[$r['setting_key']] = (string) $r['setting_value'];
                    }
                }
            } catch (Throwable $e) {
                Logger::exception($e);
            }
            self::$values = $v;
        }
        return self::$values;
    }

    public static function get(string $key): string
    {
        return self::all()[$key] ?? '';
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** Typed values (ints as numbers, bools as true/false). */
    public static function typed(bool $publicOnly): array
    {
        $out = [];
        foreach (self::all() as $k => $v) {
            [, , $type, , $public] = self::DEFS[$k];
            if ($publicOnly && !$public) {
                continue;
            }
            $out[$k] = match ($type) { 'int' => (int) $v, 'bool' => $v === '1', default => $v };
        }
        return $out;
    }

    /** Push the limits into Config so the core classes use them. */
    public static function apply(): void
    {
        $s = self::all();
        Config::set('app.name', $s['app_name']);
        Config::set('security.login_max_attempts', (int) $s['login_max_attempts']);
        Config::set('security.lockout_minutes', (int) $s['lockout_minutes']);
        Config::set('security.ip_max_attempts', (int) $s['ip_max_attempts']);
        Config::set('security.password_min_length', (int) $s['password_min_length']);
        Config::set('session.idle_timeout', (int) $s['idle_timeout_minutes'] * 60);
        Config::set('verification.validity_days', (int) $s['validity_days']);
        Config::set('verification.reverify_alert_days', (int) $s['reverify_alert_days']);
    }

    public static function save(array $values, ?int $by): void
    {
        foreach ($values as $k => $v) {
            if (!isset(self::DEFS[$k])) {
                continue;
            }
            Setting::upsert(['setting_key' => $k, 'setting_value' => (string) $v, 'updated_by' => $by, 'updated_at' => now()], ['setting_value', 'updated_by', 'updated_at']);
        }
        self::$values = null;
        self::apply();
    }

    /** Insert every default (installer / reset). */
    public static function seed(): void
    {
        foreach (self::DEFS as $k => $d) {
            Setting::insertIgnore(['setting_key' => $k, 'setting_value' => $d[5], 'updated_at' => now()]);
        }
        self::$values = null;
    }

    /** Definitions for the App Settings form. */
    public static function meta(): array
    {
        $out = [];
        foreach (self::DEFS as $k => [$group, $label, $type, $rules, , , $hint]) {
            $out[] = ['key' => $k, 'group' => $group, 'label' => $label, 'type' => $type, 'rules' => $rules, 'hint' => $hint];
        }
        return $out;
    }
}
