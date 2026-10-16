<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * A middleware either returns (allow) or stops the request (redirect / HttpException).
 */
interface MiddlewareInterface
{
    public function handle(Request $request): void;
}
