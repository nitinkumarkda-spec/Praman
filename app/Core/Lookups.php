<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Fixed option lists (must match the ENUMs in praman_db.sql).
 */
final class Lookups
{
    public const SCHEME_TYPES = [
        'RESIDENTIAL' => 'Residential',
        'COMMERCIAL'  => 'Commercial',
        'INDUSTRIAL'  => 'Industrial',
        'MIXED'       => 'Mixed',
        'FARMHOUSE'   => 'Farmhouse',
    ];

    /** Stored as-is in schemes.zone */
    public const ZONES = [
        'Zone 1' => 'Zone 1',
        'Zone 2' => 'Zone 2',
        'Zone 3' => 'Zone 3',
        'Zone 4' => 'Zone 4',
    ];

    public const PROPERTY_TYPES = [
        'PLOT'      => 'Plot',
        'SHOP'      => 'Shop',
        'KIOSK'     => 'Kiosk',
        'FARMHOUSE' => 'Farmhouse',
        'FLAT'      => 'Flat',
        'OTHER'     => 'Other',
    ];

    public const AREA_UNITS = [
        'SQM'  => 'Square Meter',
        'SQFT' => 'Square Feet',
    ];

    /** 1 sq ft = 0.092903 sq m */
    public const SQFT_TO_SQM = 0.092903;

    public const PROPERTY_STATUSES = [
        'LISTED'             => 'Listed',
        'UNDER_VERIFICATION' => 'Under Verification',
        'AUCTION_READY'      => 'Auction Ready',
        'NOT_READY'          => 'Not Ready',
        'SENT_FOR_AUCTION'   => 'Sent for Auction',
        'SOLD'               => 'Sold',
        'WITHHELD'           => 'Withheld',
        'ARCHIVED'           => 'Archived',
    ];

    public static function toSqm(float $value, string $unit): float
    {
        return round($unit === 'SQFT' ? $value * self::SQFT_TO_SQM : $value, 4);
    }

    /** Build <option> tags. $selected compared as string. Labels/values are escaped. */
    public static function options(array $map, ?string $selected, string $placeholder = ''): string
    {
        $html = $placeholder !== '' ? '<option value="">' . e($placeholder) . '</option>' : '';
        foreach ($map as $value => $text) {
            $sel = (string) $value === (string) $selected ? ' selected' : '';
            $html .= '<option value="' . e($value) . '"' . $sel . '>' . e($text) . '</option>';
        }
        return $html;
    }
}
