<?php
declare(strict_types=1);

/**
 * PSR-4 style autoloader for the App\ namespace + global helpers.
 */
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

// server-specific settings (database, debug, HTTPS …) — see .env.example
App\Core\Env::load(BASE_PATH . '/.env');
