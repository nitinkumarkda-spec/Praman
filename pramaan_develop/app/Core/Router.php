<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Minimal router: METHOD + path pattern ({id} = digits) -> "Controller@method",
 * with a middleware list per route ("auth", "guest", "csrf", "can:a.b,c.d").
 *
 * Handlers are written by name only — "Role@save" runs App\Controllers\RoleController::save().
 * The class is found when the route is used (no imports in routes.php), and only public
 * methods of a Controller subclass can be reached.
 */
final class Router
{
    /** Where controllers live; "Role" -> App\Controllers\RoleController. */
    private const CONTROLLERS = 'App\\Controllers\\';

    private array $routes = [];
    private array $groupMiddleware = [];
    private ?array $pages = null;

    public function get(string $path, string $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, string $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    /** Screens drawn in the browser: GET on any path under these first segments serves $handler. */
    public function pages(array $segments, string $handler): void
    {
        $this->pages = ['regex' => '#^/(' . implode('|', array_map('preg_quote', $segments)) . ')(/|$)#', 'handler' => $handler];
    }

    /** Routes registered inside $fn share $middleware. */
    public function group(array $middleware, callable $fn): void
    {
        $prev = $this->groupMiddleware;
        $this->groupMiddleware = array_merge($prev, $middleware);
        $fn($this);
        $this->groupMiddleware = $prev;
    }

    private function add(string $method, string $path, string $handler, array $middleware): void
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
            self::run($r['handler'], $request);
            return;
        }
        if ($this->pages && $request->method() === 'GET' && preg_match($this->pages['regex'], $path)) {
            self::run($this->pages['handler'], $request);
            return;
        }
        throw new HttpException($allowed ? 405 : 404, $allowed ? 'Method not allowed.' : 'Page not found.');
    }

    /**
     * "Role@save" -> [App\Controllers\RoleController, 'save'].
     * Refuses anything that is not a public action of a controller (a mistyped route is a server error).
     * @return array{0: class-string<Controller>, 1: string}
     */
    public static function resolve(string $handler): array
    {
        if (!preg_match('/^([A-Z][A-Za-z0-9]*)@([a-z][A-Za-z0-9]*)$/', $handler, $m)) {
            throw new \LogicException('Bad route handler "' . $handler . '" — write it as "Name@method".');
        }
        $class = self::CONTROLLERS . $m[1] . 'Controller';
        if (!class_exists($class) || !is_subclass_of($class, Controller::class)) {
            throw new \LogicException('Route handler "' . $handler . '": controller ' . $class . ' not found.');
        }
        $ref = new \ReflectionClass($class);
        if (!$ref->hasMethod($m[2]) || !$ref->getMethod($m[2])->isPublic() || $ref->getMethod($m[2])->isStatic()
            || $ref->getMethod($m[2])->getDeclaringClass()->getName() !== $class) {
            throw new \LogicException('Route handler "' . $handler . '": ' . $class . '::' . $m[2] . '() is not a public action.');
        }
        return [$class, $m[2]];
    }

    /** Every handler of the table, resolved — for a check script / tests. */
    public function handlers(): array
    {
        return array_values(array_unique(array_merge(array_column($this->routes, 'handler'), $this->pages ? [$this->pages['handler']] : [])));
    }

    private static function run(string $handler, Request $request): void
    {
        [$class, $method] = self::resolve($handler);
        (new $class($request))->$method();
    }
}
