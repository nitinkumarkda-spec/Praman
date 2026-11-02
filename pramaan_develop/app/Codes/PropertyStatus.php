<?php
declare(strict_types=1);

namespace App\Codes;

/** System codes of the Status Master the workflow depends on — properties.current_status. */
final class PropertyStatus
{
    public const LISTED = 'LISTED';
    public const UNDER_VERIFICATION = 'UNDER_VERIFICATION';
    public const AUCTION_READY = 'AUCTION_READY';
    public const NOT_READY = 'NOT_READY';
    public const EXPIRED = 'EXPIRED';

    /** May be sent (again) for verification by the OIC. */
    public const SENDABLE = [self::LISTED, self::NOT_READY, self::EXPIRED];
}
