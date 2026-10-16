<?php
declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

/**
 * Application bootstrap: config, error handling, session, security headers, routing.
 */
final class App
{
    public static function run(string $basePath): void
    {
        Config::load($basePath . '/config');
        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Kolkata'));
        mb_internal_encoding('UTF-8');
        View::setPath($basePath . '/app/Views');

        self::registerErrorHandling($basePath);
        Response::sendSecurityHeaders();

        $request = Request::current();
        try {
            if (Config::get('app.force_https') === true && !$request->isSecure()) {
                header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
                exit;
            }
            if (!in_array($request->method(), ['GET', 'POST'], true)) {
                throw new HttpException(405, 'This action is not allowed.');
            }

            Session::start();

            $router = new Router();
            require $basePath . '/app/routes.php';
            $router->dispatch($request);
        } catch (HttpException $e) {
            self::renderError($e->status, $e->getMessage());
        } catch (Throwable $e) {
            Logger::exception($e);
            self::renderError(500, 'Something went wrong. The error has been logged; please try again or contact IT.');
        }
    }

    private static function registerErrorHandling(string $basePath): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $basePath . '/storage/logs/php-error.log');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal: ' . $err['message'], ['file' => $err['file'] . ':' . $err['line']]);
            }
        });
    }

    public static function renderError(int $status, string $message): void
    {
        // Apache turns the non-standard 419 into a 500, so a CSRF failure is sent as 403 (page still says "Session Expired").
        http_response_code($status === 419 ? 403 : $status);
        $request = Request::current();
        if ($request->wantsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
            return;
        }
        $titles = [
            403 => 'Access Denied',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Session Expired',
            429 => 'Too Many Requests',
            500 => 'Server Error',
        ];
        try {
            echo View::render('errors/error', [
                'status'  => $status,
                'title'   => $titles[$status] ?? 'Error',
                'message' => $message,
            ], 'layouts/auth');
        } catch (Throwable $e) {
            Logger::exception($e);
            echo '<!doctype html><title>Error</title><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
        }
    }
}
