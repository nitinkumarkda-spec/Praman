<?php
declare(strict_types=1);

namespace App\Core;

use App\Services\Housekeeping;
use ErrorException;
use Throwable;

/**
 * Application bootstrap: config, error handling, headers, session, routing.
 */
final class App
{
    public static function run(string $basePath): void
    {
        Config::load($basePath . '/config');
        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));
        mb_internal_encoding('UTF-8');
        self::registerErrorHandling();
        \App\Services\Settings::apply(); // limits + branding from the settings table

        $request = Request::current();
        Response::sendSecurityHeaders();
        try {
            if (Config::get('app.force_https') === true && !$request->isSecure()) {
                header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
                exit;
            }
            if (!in_array($request->method(), ['GET', 'POST'], true)) {
                throw new HttpException(405, 'This action is not allowed.');
            }
            Session::start();
            if (Auth::check()) {
                Housekeeping::run();
            }
            $router = new Router();
            require $basePath . '/app/routes.php';
            $router->dispatch($request);
        } catch (HttpException $e) {
            self::renderError($e->status, $e->getMessage());
        } catch (Throwable $e) {
            Logger::exception($e);
            self::renderError(500, Config::get('app.debug') ? $e->getMessage() : 'Something went wrong. Please try again; the error has been logged.');
        }
    }

    private static function registerErrorHandling(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', BASE_PATH . '/storage/logs/php-errors.log');
        set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new ErrorException($str, 0, $no, $file, $line);
        });
    }

    public static function renderError(int $status, string $message): never
    {
        $titles = [403 => 'Access denied', 404 => 'Page not found', 405 => 'Not allowed', 419 => 'Session expired', 422 => 'Invalid request', 429 => 'Too many requests', 500 => 'Server error'];
        if (Request::current()->wantsJson()) {
            // 419 (expired CSRF token) is not a registered HTTP status: Apache would turn it into a 500, so it is sent as 403 + "csrf"
            Response::json(['ok' => false, 'message' => $message ?: ($titles[$status] ?? 'Error'), 'status' => $status, 'csrf' => $status === 419],
                $status === 419 ? 403 : $status);
        }
        $status = $status === 419 ? 403 : $status;
        Response::html(View::render('errors/error', [
            'status' => $status, 'title' => $titles[$status] ?? 'Error', 'message' => $message,
        ]), $status);
    }
}
