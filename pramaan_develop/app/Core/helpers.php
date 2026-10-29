<?php
declare(strict_types=1);

use App\Core\Clock;
use App\Core\Config;

/** Value from .env (or the real environment), typed: true / false / null / ''. */
function env(string $key, mixed $default = null): mixed
{
    return App\Core\Env::get($key, $default);
}

/** HTML-escape for views. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read a config value with dot notation (e.g. "security.lockout_minutes"). */
function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/** Current date-time (Y-m-d H:i:s), optionally shifted by minutes. */
function now(int $offsetMinutes = 0): string
{
    return Clock::now($offsetMinutes);
}

/** Current date (Y-m-d), optionally shifted by days. */
function today(int $offsetDays = 0): string
{
    return Clock::today($offsetDays);
}

/** 2026-10-28 -> 28-10-2026 */
function dmy(?string $ymd): string
{
    if (!$ymd) {
        return '—';
    }
    $p = explode('-', substr($ymd, 0, 10));
    return count($p) === 3 ? $p[2] . '-' . $p[1] . '-' . $p[0] : $ymd;
}

/** LISTED_ITEM -> Listed Item */
function label(?string $code): string
{
    if ($code === null) {
        return '—';
    }
    return ucwords(strtolower(str_replace('_', ' ', $code)));
}

/** Web path of the app (no trailing slash), e.g. /pramaan_kda/pramaan_develop */
function base_path(): string
{
    return App\Core\Request::current()->basePath();
}

/** Absolute URL path to a public asset with a cache-busting version. */
function asset(string $path): string
{
    $file = BASE_PATH . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr((string) filemtime($file), -6) : '1';
    return base_path() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}
