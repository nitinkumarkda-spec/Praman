<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Output helpers: security headers, redirects, JSON.
 */
final class Response
{
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
            . "font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        // Authenticated pages must never be cached by the browser / proxies.
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        if (Config::get('app.force_https') === true) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function redirect(string $path, int $status = 302): never
    {
        $target = preg_match('#^https?://#i', $path) ? self::safeAbsolute($path) : url($path);
        header('Location: ' . $target, true, $status);
        exit;
    }

    /** Redirect back to the referring page (same host only), else to $fallback. */
    public static function back(string $fallback = '/'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST)) {
            header('Location: ' . $ref, true, 302);
            exit;
        }
        self::redirect($fallback);
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
        exit;
    }

    private static function safeAbsolute(string $url): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        return parse_url($url, PHP_URL_HOST) === parse_url('http://' . $host, PHP_URL_HOST) ? $url : url('/');
    }
}
