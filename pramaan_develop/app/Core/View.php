<?php
declare(strict_types=1);

namespace App\Core;

/**
 * PHP templates in app/Views. Data keys become variables; output is escaped with e().
 */
final class View
{
    public static function render(string $template, array $data = []): string
    {
        $file = BASE_PATH . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
