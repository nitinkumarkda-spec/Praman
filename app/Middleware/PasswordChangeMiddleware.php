<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Users with must_change_password = 1 can only reach the change-password page and logout.
 */
final class PasswordChangeMiddleware implements MiddlewareInterface
{
    private const ALLOWED = ['/change-password', '/logout'];

    public function handle(Request $request): void
    {
        if (\App\Core\Config::get('security.force_password_change') !== true) {
            return; // forced change disabled in config/config.php
        }
        $user = Auth::user();
        if ($user === null || (int) $user['must_change_password'] !== 1 || in_array($request->path(), self::ALLOWED, true)) {
            return;
        }
        if ($request->wantsJson()) {
            throw new HttpException(403, 'Please change your password before continuing.');
        }
        Session::flash('warning', 'For security, please set a new password before continuing.');
        Response::redirect('/change-password');
    }
}
