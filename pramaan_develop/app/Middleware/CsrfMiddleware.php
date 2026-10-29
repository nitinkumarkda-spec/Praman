<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;

/**
 * Every POST must carry the session CSRF token (X-CSRF-Token header) and come
 * from this site (Origin / Sec-Fetch-Site check as a second layer).
 */
final class CsrfMiddleware
{
    public function handle(Request $request, array $args): void
    {
        if ($request->method() !== 'POST') {
            return;
        }
        $site = $request->header('Sec-Fetch-Site');
        if ($site !== null && !in_array($site, ['same-origin', 'none'], true)) {
            throw new HttpException(403, 'Cross-site request blocked.');
        }
        $origin = $request->header('Origin');
        if ($origin !== null && $origin !== 'null') {
            $host = parse_url($origin, PHP_URL_HOST) . (parse_url($origin, PHP_URL_PORT) ? ':' . parse_url($origin, PHP_URL_PORT) : '');
            if (strcasecmp($host, (string) ($_SERVER['HTTP_HOST'] ?? '')) !== 0) {
                throw new HttpException(403, 'Cross-site request blocked.');
            }
        }
        if (!Csrf::valid($request->header('X-CSRF-Token'))) {
            throw new HttpException(419, 'Your session has expired or the form is out of date. Please refresh the page and try again.');
        }
    }
}
