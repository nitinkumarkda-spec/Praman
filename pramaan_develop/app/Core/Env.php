<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Minimal .env reader (no Composer needed).
 *   KEY=value            # comment
 *   KEY="quoted value"   escapes \n \" \\ inside double quotes
 *   KEY='literal value'
 *   KEY=${OTHER_KEY}     reference to an earlier key
 * Real environment variables (set by the server) win over the file.
 */
final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $file): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        if (!is_file($file)) {
            return; // optional: config/*.php has safe defaults
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $n => $raw) {
            $line = trim($raw);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }
            if (!preg_match('/^([A-Z][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $m)) {
                throw new RuntimeException('.env line ' . ($n + 1) . ' is not KEY=value.');
            }
            self::$vars[$m[1]] = self::parseValue($m[2]);
        }
    }

    /** Typed value: "true"/"false"/"null"/"empty" become bool / null / ''. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $server = getenv($key);
        $value = $server !== false ? $server : (self::$vars[$key] ?? null);
        if ($value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }

    private static function parseValue(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if ($v[0] === '"') {
            if (!preg_match('/^"((?:[^"\\\\]|\\\\.)*)"\s*(#.*)?$/', $v, $m)) {
                throw new RuntimeException('.env: unclosed double quote in ' . $v);
            }
            return self::expand(strtr($m[1], ['\\n' => "\n", '\\"' => '"', '\\\\' => '\\']));
        }
        if ($v[0] === "'") {
            if (!preg_match("/^'([^']*)'\\s*(#.*)?$/", $v, $m)) {
                throw new RuntimeException('.env: unclosed single quote in ' . $v);
            }
            return $m[1];
        }
        $v = preg_replace('/\s+#.*$/', '', $v) ?? $v; // inline comment
        return self::expand(trim($v));
    }

    private static function expand(string $v): string
    {
        return preg_replace_callback('/\$\{([A-Z][A-Z0-9_]*)\}/', static fn ($m) => (string) (getenv($m[1]) ?: (self::$vars[$m[1]] ?? '')), $v) ?? $v;
    }
}
