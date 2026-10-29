<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/** Only when nobody is signed in (login). */
final class GuestMiddleware
{
    public function handle(Request $request, array $args): void
    {
        if (Auth::check()) {
            throw new HttpException(409, 'You are already logged in. Refresh the page.');
        }
    }
}
