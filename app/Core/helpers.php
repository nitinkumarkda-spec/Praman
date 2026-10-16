<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\Table;
use App\Core\View;

/** Real table name for a registry key in config/tables.php: tbl('users'). */
function tbl(string $key): string
{
    return Table::name($key);
}

/** HTML-escape any value for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Application URL for a route path, with optional query params. */
function url(string $path = '/', array $query = []): string
{
    $url = Request::basePath() . '/' . ltrim($path, '/');
    if ($url === '') {
        $url = '/';
    }
    return $query ? $url . '?' . http_build_query($query) : $url;
}

/** Versioned URL of a file in public/assets (cache-busted by mtime). */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = dirname(__DIR__, 2) . '/public/assets/' . $path;
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return Request::basePath() . '/assets/' . $path . '?v=' . $v;
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Previously submitted value after a failed validation. */
function old(string $key, mixed $default = ''): mixed
{
    $old = Session::getFlash('old', []);
    return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
}

/** Validation error message for a field ('' if none). */
function error(string $key): string
{
    $errors = Session::getFlash('errors', []);
    return is_array($errors) && isset($errors[$key]) ? (string) $errors[$key] : '';
}

/** " is-invalid" css class when the field has an error. */
function invalid(string $key): string
{
    return error($key) !== '' ? ' is-invalid' : '';
}

function auth_user(): ?array
{
    return Auth::user();
}

function partial(string $view, array $data = []): string
{
    return View::partial($view, $data);
}

/** "active" when the current path starts with $prefix (sidebar highlighting). */
function nav_active(string $prefix): string
{
    $path = Request::current()->path();
    return ($path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/')) ? ' active' : '';
}

function fmt_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d-m-Y', $ts) : '—';
}

function fmt_datetime(?string $value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d-m-Y h:i A', $ts) : '—';
}

function fmt_num(mixed $value, int $decimals = 0): string
{
    return number_format((float) ($value ?? 0), $decimals);
}

/** Human label for ENUM-like codes: UNDER_VERIFICATION -> Under Verification */
function label(?string $code): string
{
    return $code === null ? '—' : ucwords(strtolower(str_replace('_', ' ', $code)));
}

/** Coloured badge for any status code used in the system. */
function status_badge(?string $status): string
{
    $map = [
        'LISTED'             => ['badge-info',    'fa-list'],
        'UNDER_VERIFICATION' => ['badge-warning', 'fa-hourglass-half'],
        'AUCTION_READY'      => ['badge-success', 'fa-circle-check'],
        'NOT_READY'          => ['badge-danger',  'fa-circle-xmark'],
        'SENT_FOR_AUCTION'   => ['badge-primary', 'fa-gavel'],
        'SOLD'               => ['badge-dark',    'fa-handshake'],
        'WITHHELD'           => ['badge-muted',   'fa-pause'],
        'ARCHIVED'           => ['badge-muted',   'fa-box-archive'],
        'OPEN'               => ['badge-warning', 'fa-folder-open'],
        'CLOSED'             => ['badge-dark',    'fa-lock'],
        'SUPERSEDED'         => ['badge-muted',   'fa-clock-rotate-left'],
        'CANCELLED'          => ['badge-muted',   'fa-ban'],
        'PENDING'            => ['badge-warning', 'fa-clock'],
        'APPROVED'           => ['badge-success', 'fa-check'],
        'REJECTED'           => ['badge-danger',  'fa-xmark'],
        'NO_RESPONSE'        => ['badge-danger',  'fa-bell-slash'],
        'NOT_REQUIRED'       => ['badge-muted',   'fa-minus'],
        'OVERDUE'            => ['badge-danger',  'fa-stopwatch'],
        'READY'              => ['badge-success', 'fa-circle-check'],
        'ACTIVE'             => ['badge-success', 'fa-circle'],
        'INACTIVE'           => ['badge-muted',   'fa-circle'],
    ];
    [$class, $icon] = $map[$status ?? ''] ?? ['badge-muted', 'fa-circle-info'];
    return '<span class="badge ' . $class . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i> ' . e(label($status)) . '</span>';
}

function active_badge(int|string|null $isActive): string
{
    return status_badge((int) $isActive === 1 ? 'ACTIVE' : 'INACTIVE');
}

/** Sortable column header for paginated tables. */
function th_sort(\App\Core\Paginator $p, string $key, string $label, string $class = ''): string
{
    $aria = $p->sort === $key ? ($p->dir === 'asc' ? 'ascending' : 'descending') : 'none';
    return '<th class="sortable' . ($class !== '' ? ' ' . e($class) : '') . '" aria-sort="' . $aria . '"><a href="' . e($p->sortUrl($key)) . '">'
        . e($label) . ' <i class="fa-solid ' . e($p->sortIcon($key)) . '" aria-hidden="true"></i></a></th>';
}
