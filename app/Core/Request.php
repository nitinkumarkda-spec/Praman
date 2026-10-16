<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Immutable view of the current HTTP request.
 */
final class Request
{
    private static ?self $current = null;

    private string $method;
    private string $path;
    /** @var array<string, string> */
    private array $routeParams = [];

    private function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->path = $this->resolvePath();
    }

    public static function current(): self
    {
        return self::$current ??= new self();
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Path relative to the application base, always starting with "/" and without a trailing slash. */
    public function path(): string
    {
        return $this->path;
    }

    /** Query-string value (trimmed string). */
    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? null;
        return is_string($value) ? trim($value) : $default;
    }

    /** POST value (trimmed string). Arrays are returned by inputArray(). */
    public function input(string $key, ?string $default = null): ?string
    {
        $value = $_POST[$key] ?? null;
        return is_string($value) ? trim($value) : $default;
    }

    /** POST array value, e.g. sectors[] — always an array. */
    public function inputArray(string $key): array
    {
        $value = $_POST[$key] ?? [];
        return is_array($value) ? $value : [];
    }

    /** All POST scalars, trimmed (used for re-filling forms). */
    public function all(): array
    {
        $out = [];
        foreach ($_POST as $k => $v) {
            $out[$k] = is_string($v) ? trim($v) : $v;
        }
        return $out;
    }

    /** Selected keys from POST only. */
    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k, '');
        }
        return $out;
    }

    public function queryInt(string $key, int $default = 0): int
    {
        $v = $this->query($key);
        return ($v !== null && ctype_digit($v)) ? (int) $v : $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains((string) $this->header('Accept'), 'application/json');
    }

    public function ip(): string
    {
        // Behind a trusted reverse proxy, replace this with X-Forwarded-For handling.
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public function isSecure(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function param(string $key): ?string
    {
        return $this->routeParams[$key] ?? null;
    }

    /** Application base path, e.g. "/PARMAM" or "" when the vhost points at /public. */
    public static function basePath(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }
        $configured = Config::get('app.base_path');
        if (is_string($configured)) {
            return $base = rtrim($configured, '/');
        }
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        return $base = $dir;
    }

    private function resolvePath(): string
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $base = self::basePath();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public/') || $uri === '/public') {
            $uri = substr($uri, 7);
        }
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }
}
