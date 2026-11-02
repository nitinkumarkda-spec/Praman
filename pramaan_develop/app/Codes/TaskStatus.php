<?php
declare(strict_types=1);

namespace App\Codes;

/** One section's answer in a round — tasks.task_status. */
final class TaskStatus
{
    public const PENDING = 'PENDING';
    public const APPROVED = 'APPROVED';
    public const REJECTED = 'REJECTED';
    public const NO_RESPONSE = 'NO_RESPONSE';

    /** A section may answer with one of these. */
    public const DECISIONS = [self::APPROVED, self::REJECTED];
    /** These make the round Not Ready. */
    public const BLOCKING = [self::REJECTED, self::NO_RESPONSE];
}
