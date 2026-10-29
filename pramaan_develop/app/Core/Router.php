<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router: METHOD + path pattern ({id} = digits) -> [Controller, method],
 * with a middleware list per route ("auth", "guest", "csrf", "panel:A,B").
 */
final class Router
{
    private array $routes = [];
    private array $groupMiddleware = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** Routes registered inside $fn share $middleware. */
    public function group(array $middleware, callable $fn): void
    {
        $prev = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($prev, $middleware);
        $fn($this);
        $this->groupMiddleware = $prev;
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $keys = [];
        $regex = preg_replace_callback('/\{(\w+)\}/', static function ($m) use (&$keys) {
            $keys[] = $m[1];
            return '(\d{1,10})';
        }, $path);
        $this->routes[] = [
            'method' => $method, 'regex' => '#^' . $regex . '$#', 'keys' => $keys,
            'handler' => $handler, 'middleware' => array_merge($this->groupMiddleware, $middleware),
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $allowed = false;
        foreach ($this->routes as $r) {
            if (!preg_match($r['regex'], $path, $m)) {
                continue;
            }
            if ($r['method'] !== $request->method()) {
                $allowed = true;
                continue;
            }
            $request->params = [];
            foreach ($r['keys'] as $i => $k) {
                $request->params[$k] = (int) $m[$i + 1];
            }
            foreach ($r['middleware'] as $mw) {
                [$name, $arg] = array_pad(explode(':', $mw, 2), 2, '');
                $class = 'App\\Middleware\\' . ucfirst($name) . 'Middleware';
                (new $class())->handle($request, $arg === '' ? [] : explode(',', $arg));
            }
            [$class, $method] = $r['handler'];
            (new $class($request))->$method();
            return;
        }
        throw new HttpException($allowed ? 405 : 404, $allowed ? 'Method not allowed.' : 'Page not found.');
    }
}
