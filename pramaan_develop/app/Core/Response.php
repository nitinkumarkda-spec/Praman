<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Output helpers + security headers (also set by .htaccess; PHP sets them
 * too so they are present when mod_headers is missing).
 */
final class Response
{
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        $secure = Request::current()->isSecure();
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; "
            . "connect-src 'self'; frame-src 'self' blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'"
            . ($secure ? '; upgrade-insecure-requests' : ''));
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Permitted-Cross-Domain-Policies: none');
        if ($secure) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    public static function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        self::noCache();
        header('Content-Type: application/json; charset=utf-8');
        self::send((string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public static function html(string $html, int $status = 200): never
    {
        http_response_code($status);
        self::noCache();
        header('Content-Type: text/html; charset=utf-8');
        self::send($html);
    }

    /**
     * Output the body, gzip-compressed when the browser accepts it (pages and
     * API state are text and shrink to ~15 %). Works without Apache mod_deflate.
     */
    private static function send(string $body): never
    {
        $accepts = str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')), 'gzip');
        header('Vary: Accept-Encoding');
        if ($accepts && strlen($body) > 1024 && function_exists('gzencode') && !ini_get('zlib.output_compression') && !headers_sent()) {
            $body = (string) gzencode($body, 6);
            header('Content-Encoding: gzip');
        }
        header('Content-Length: ' . strlen($body));
        echo $body;
        exit;
    }
}
