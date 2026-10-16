<?php
declare(strict_types=1);

/**
 * PRAMAN — front controller. Every request is routed here by .htaccess.
 */

define('BASE_PATH', dirname(__DIR__));

// PSR-4 autoloader: App\Foo\Bar -> app/Foo/Bar.php
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/Core/helpers.php';

App\Core\App::run(BASE_PATH);
