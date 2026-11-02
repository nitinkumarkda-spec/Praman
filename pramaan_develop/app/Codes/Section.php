<?php
declare(strict_types=1);

namespace App\Codes;

/**
 * Role (section) codes with their own verification form — roles.role_code.
 * Every other section answers the General check.
 */
final class Section
{
    public const ENGINEERING = 'ENGINEERING_CELL';
    public const SALE = 'SALE_CELL';
    public const AUCTION = 'AUCTION_CELL';

    public const OWN_FORM = [self::SALE, self::AUCTION, self::ENGINEERING];
}
