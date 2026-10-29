<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Services\Audit;

/** Role-based access: "panel:SUPER_ADMIN,DATA_ENTRY". Denials are audited. */
final class PanelMiddleware
{
    public function handle(Request $request, array $args): void
    {
        $panel = Auth::panel();
        if ($panel === null) {
            throw new HttpException(401, 'Please login to continue.');
        }
        if (!in_array($panel, $args, true)) {
            Audit::log('ROUTE', null, 'ACCESS_DENIED', null, ['path' => $request->path()]);
            throw new HttpException(403, 'You do not have permission to do this.');
        }
    }
}
