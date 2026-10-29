<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;

/** Signed-in users only. */
final class AuthMiddleware
{
    public function handle(Request $request, array $args): void
    {
        if (!Auth::check()) {
            throw new HttpException(401, 'Please login to continue.');
        }
    }
}
