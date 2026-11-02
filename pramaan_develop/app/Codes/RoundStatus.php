<?php
declare(strict_types=1);

namespace App\Codes;

/** Verification round — rounds.round_status and rounds.result. */
final class RoundStatus
{
    public const OPEN = 'OPEN';
    public const CLOSED = 'CLOSED';
    public const CANCELLED = 'CANCELLED';

    /** rounds.result */
    public const READY = 'READY';
    public const NOT_READY = 'NOT_READY';
}
