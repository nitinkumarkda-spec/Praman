<?php
declare(strict_types=1);

namespace App\Codes;

/** Fixed ids of the Status Master rows the workflow depends on — properties.status_id (same as DB.ST in js/db.js). */
final class PropertyStatus
{
    public const LISTED = 1;
    public const UNDER_VERIFICATION = 2;
    public const AUCTION_READY = 3;
    public const NOT_READY = 4;
    public const EXPIRED = 5;
    public const SENT_FOR_AUCTION = 6;
    public const SOLD = 7;
    public const WITHHELD = 8;
    public const ARCHIVED = 9;

    /** May be sent (again) for verification by the OIC. */
    public const SENDABLE = [self::LISTED, self::NOT_READY, self::EXPIRED];

    /** Former status codes → id (database/migrate.php). */
    public const IDS = ['LISTED' => self::LISTED, 'UNDER_VERIFICATION' => self::UNDER_VERIFICATION, 'AUCTION_READY' => self::AUCTION_READY,
        'NOT_READY' => self::NOT_READY, 'EXPIRED' => self::EXPIRED, 'SENT_FOR_AUCTION' => self::SENT_FOR_AUCTION, 'SOLD' => self::SOLD,
        'WITHHELD' => self::WITHHELD, 'ARCHIVED' => self::ARCHIVED];
}
