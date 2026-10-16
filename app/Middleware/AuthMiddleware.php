<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires a logged-in, active user. Also forces the first-login password change.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->wantsJson()) {
                throw new HttpException(401, 'Your session has expired. Please login again.');
            }
            if (!Session::getFlash('warning')) {
                Session::flash('warning', 'Please login to continue.');
            }
            Response::redirect('/login');
        }
        (new PasswordChangeMiddleware())->handle($request);
    }
}
