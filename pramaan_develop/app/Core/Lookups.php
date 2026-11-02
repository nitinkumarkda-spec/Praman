<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Fixed code lists (they match ENUM columns in database/schema.sql).
 * The UI receives them in the state, so screens and server validation use one list.
 */
final class Lookups
{
    public const PANELS = ['SUPER_ADMIN' => 'Super Admin', 'DATA_ENTRY' => 'Data Entry', 'OIC' => 'OIC Auction', 'ROLE_USER' => 'Role User'];
    public const SCHEME_TYPES = ['RESIDENTIAL' => 'Residential', 'COMMERCIAL' => 'Commercial', 'INDUSTRIAL' => 'Industrial', 'MIXED' => 'Mixed', 'FARMHOUSE' => 'Farmhouse'];
    public const STATUS_COLORS = ['info' => 'Blue', 'warning' => 'Amber', 'success' => 'Green', 'danger' => 'Red', 'primary' => 'Navy', 'gold' => 'Gold', 'dark' => 'Dark', 'muted' => 'Grey'];
    public const STATUS_ICONS = ['fa-list' => 'List', 'fa-hourglass-half' => 'Hourglass', 'fa-circle-check' => 'Check', 'fa-circle-xmark' => 'Cross', 'fa-gavel' => 'Gavel',
        'fa-handshake' => 'Handshake', 'fa-pause' => 'Pause', 'fa-box-archive' => 'Archive', 'fa-flag' => 'Flag', 'fa-clock' => 'Clock', 'fa-ban' => 'Ban', 'fa-star' => 'Star'];

    public static function all(): array
    {
        return [
            'panels' => self::PANELS, 'scheme_types' => self::SCHEME_TYPES,
            'status_colors' => self::STATUS_COLORS, 'status_icons' => self::STATUS_ICONS,
        ];
    }

    /** "A,B,C" for the Validator "in:" rule. */
    public static function keys(array $list): string
    {
        return implode(',', array_keys($list));
    }
}
