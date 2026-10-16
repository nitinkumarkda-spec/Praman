<?php
declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\MiddlewareInterface;
use App\Middleware\PanelMiddleware;
use App\Middleware\PasswordChangeMiddleware;

/**
 * Minimal router: GET/POST routes with {param} segments and middleware aliases.
 *
 *   $router->get('/admin/users/{id}/edit', [UserController::class, 'edit'], ['auth', 'panel:SUPER_ADMIN']);
 *
 * Middleware aliases: guest | auth | panel:A,B | csrf (csrf runs automatically on every POST).
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, regex: string, handler: array, middleware: string[]}> */
    private array $routes = [];
    /** @var string[] */
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** Group routes under a prefix + shared middleware. */
    public function group(string $prefix, array $middleware, callable $routes): void
    {
        $prevPrefix = $this->groupPrefix;
        $prevMw = $this->groupMiddleware;
        $this->groupPrefix = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMw, $middleware);
        $routes($this);
        $this->groupPrefix = $prevPrefix;
        $this->groupMiddleware = $prevMw;
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $full = '/' . trim($this->groupPrefix . $path, '/');
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $full) . '$#';
        $this->routes[] = [
            'method'     => $method,
            'pattern'    => $full,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $allowed = [];
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $m) !== 1) {
                continue;
            }
            if ($route['method'] !== $request->method()) {
                $allowed[] = $route['method'];
                continue;
            }
            $request->setRouteParams(array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY));

            $middleware = $route['middleware'];
            if ($request->isPost() && !in_array('csrf', $middleware, true)) {
                array_unshift($middleware, 'csrf');
            }
            foreach ($middleware as $alias) {
                $this->resolveMiddleware($alias)->handle($request);
            }

            [$class, $method] = $route['handler'];
            $controller = new $class();
            $controller->$method();
            return;
        }
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405, 'This action is not allowed.');
        }
        throw new HttpException(404, 'The page you are looking for does not exist.');
    }

    private function resolveMiddleware(string $alias): MiddlewareInterface
    {
        [$name, $arg] = array_pad(explode(':', $alias, 2), 2, '');
        return match ($name) {
            'guest' => new GuestMiddleware(),
            'auth'  => new AuthMiddleware(),
            'pwd'   => new PasswordChangeMiddleware(),
            'panel' => new PanelMiddleware(array_filter(explode(',', $arg))),
            'csrf'  => new CsrfMiddleware(),
            default => throw new \LogicException("Unknown middleware: {$alias}"),
        };
    }
}
