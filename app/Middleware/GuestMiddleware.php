<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Only for visitors who are NOT logged in (login page).
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        if (Auth::check()) {
            Response::redirect(Auth::home());
        }
    }
}
