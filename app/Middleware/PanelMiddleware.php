<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;

/**
 * RBAC: the logged-in user's panel must be one of the allowed panels for the route.
 */
final class PanelMiddleware implements MiddlewareInterface
{
    /** @param string[] $panels */
    public function __construct(private readonly array $panels)
    {
    }

    public function handle(Request $request): void
    {
        $panel = Auth::panel();
        if ($panel === null || !in_array($panel, $this->panels, true)) {
            Logger::warning('RBAC denied', ['user_id' => Auth::id(), 'panel' => $panel, 'path' => $request->path()]);
            Audit::log('SECURITY', Auth::id(), 'ACCESS_DENIED', null, ['path' => $request->path(), 'method' => $request->method()]);
            throw new HttpException(403, 'You do not have permission to access this page.');
        }
    }
}
