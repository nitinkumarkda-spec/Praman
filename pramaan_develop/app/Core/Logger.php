<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * File logger (storage/logs/app-YYYY-MM-DD.log). Never shown to users.
 */
final class Logger
{
    public static function write(string $level, string $message, array $context = []): void
    {
        $dir = (string) Config::get('log.path', BASE_PATH . '/storage/logs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $line = sprintf("[%s] %s: %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) : '');
        @file_put_contents($dir . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function exception(Throwable $e): void
    {
        self::write('error', get_class($e) . ': ' . $e->getMessage(), [
            'file' => $e->getFile() . ':' . $e->getLine(),
            'uri'  => $_SERVER['REQUEST_URI'] ?? '',
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 12),
        ]);
    }
}
