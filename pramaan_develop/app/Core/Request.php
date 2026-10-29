<?php
declare(strict_types=1);

namespace App\Core;

/**
 * The current HTTP request (method, path relative to the app, input, files).
 */
final class Request
{
    private static ?Request $current = null;
    private string $basePath;
    private string $path;
    /** route parameters ({id}) */
    public array $params = [];

    private function __construct()
    {
        $this->basePath = $this->detectBasePath();
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);
        if ($this->basePath !== '' && str_starts_with($uri, $this->basePath)) {
            $uri = substr($uri, strlen($this->basePath));
        }
        $this->path = '/' . trim($uri, '/');
    }

    public static function current(): self
    {
        return self::$current ??= new self();
    }

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** Trimmed string input (arrays become ''). */
    public function str(string $key, string $default = ''): string
    {
        $v = $_POST[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    /** List input (name[]) as trimmed strings. */
    public function list(string $key): array
    {
        $v = $_POST[$key] ?? [];
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $v));
    }

    public function all(): array
    {
        return $_POST;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /** Normalised list of uploaded files for an input name="x[]". */
    public function files(string $key): array
    {
        $f = $_FILES[$key] ?? null;
        if (!$f || !is_array($f['name'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($f['name'] as $i => $name) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = ['name' => (string) $name, 'tmp' => (string) $f['tmp_name'][$i], 'size' => (int) $f['size'][$i], 'error' => (int) $f['error'][$i]];
        }
        return $out;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public function ip(): string
    {
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

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/') || str_contains((string) $this->header('Accept'), 'application/json');
    }

    private function detectBasePath(): string
    {
        $forced = Config::get('app.base_path');
        if (is_string($forced)) {
            return rtrim($forced, '/');
        }
        // .../public/index.php  ->  app folder (root .htaccess forwards everything to /public)
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/public')) {
            $dir = substr($dir, 0, -7);
        }
        return $dir === '.' ? '' : $dir;
    }
}
