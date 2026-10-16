<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Thrown to stop the request with an HTTP status (403, 404, 405, 419 ...).
 * The message is safe to show to the user.
 */
final class HttpException extends RuntimeException
{
    public function __construct(public readonly int $status, string $message = '')
    {
        parent::__construct($message, $status);
    }
}
