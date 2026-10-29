<?php
declare(strict_types=1);

/**
 * PRAMAN — front controller. Every request (pages + JSON API) enters here.
 */
define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Core/bootstrap.php';

App\Core\App::run(BASE_PATH);
