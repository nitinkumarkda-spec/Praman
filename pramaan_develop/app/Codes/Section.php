<?php
declare(strict_types=1);

namespace App\Codes;

/**
 * The verification form a role (section) fills — roles.verification_form, chosen in the Role master.
 * Sale, Auction and Site (Engineering) have their own form; every other section answers the General check.
 */
final class Section
{
    public const GENERAL = 'GENERAL';
    public const SALE = 'SALE';
    public const AUCTION = 'AUCTION';
    public const SITE = 'SITE';

    public const OWN_FORM = [self::SALE, self::AUCTION, self::SITE];

    public const FORMS = [
        self::GENERAL => 'General check (allotment to an organisation / institution)',
        self::SALE    => 'Sale register (allotted / mutation)',
        self::AUCTION => 'Auction record (auctioned)',
        self::SITE    => 'Site inspection (area, encroachment, road, photos)',
    ];
}
