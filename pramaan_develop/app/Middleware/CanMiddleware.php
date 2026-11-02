<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\Audit;

/** Permission-based access: "can:user.create,user.edit" (any one is enough). Denials are audited. */
final class CanMiddleware
{
    public function handle(Request $request, array $args): void
    {
        if (!Auth::check()) {
            throw new HttpException(401, 'Please login to continue.');
        }
        if (!Auth::can(...$args)) {
            Audit::log('ROUTE', null, 'ACCESS_DENIED', null, ['path' => $request->path(), 'permission' => implode(',', $args)]);
            throw new HttpException(403, 'You do not have permission to do this.');
        }
    }
}
