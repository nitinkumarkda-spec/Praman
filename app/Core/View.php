<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Plain-PHP templates in app/Views. Every dynamic value in a view must go through e().
 */
final class View
{
    private static string $viewPath = '';

    public static function setPath(string $path): void
    {
        self::$viewPath = rtrim($path, '/\\');
    }

    /** Render a view inside a layout ("layouts/main" by default, null = no layout). */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/main'): string
    {
        $content = self::partial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, $data + ['content' => $content]);
    }

    public static function partial(string $view, array $data = []): string
    {
        $file = self::$viewPath . '/' . str_replace(['..', '\\'], ['', '/'], $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
