<?php
declare(strict_types=1);

namespace App\Codes;

/** Yes / No answers of the section checks and site inspection (site_inspections.encroachment). */
final class YesNo
{
    public const YES = 'YES';
    public const NO = 'NO';

    public const ALL = [self::YES, self::NO];
}
