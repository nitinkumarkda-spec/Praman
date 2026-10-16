<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;

/**
 * Rejects every POST without a valid CSRF token (HTTP 419).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        if ($request->isPost() && !Csrf::verify($request)) {
            Logger::warning('CSRF token mismatch', ['path' => $request->path(), 'ip' => $request->ip()]);
            throw new HttpException(419, 'Your form has expired. Please go back, refresh the page and try again.');
        }
    }
}
