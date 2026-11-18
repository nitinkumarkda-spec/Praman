<?php
declare(strict_types=1);

namespace App\Core;

use App\Codes\Section;

/**
 * Fixed code lists (they match ENUM columns in database/schema.sql).
 * The UI receives them in the state, so screens and server validation use one list.
 */
final class Lookups
{
    public const PANELS = [];
    /** Direction a plot faces (properties.facing) — a fixed list, not a master. */
    public const FACINGS = ['EAST' => 'East', 'NORTH' => 'North', 'NORTH_EAST' => 'North-East', 'NORTH_WEST' => 'North-West',
        'SOUTH' => 'South', 'SOUTH_EAST' => 'South-East', 'SOUTH_WEST' => 'South-West', 'WEST' => 'West'];
    public const STATUS_COLORS = ['info' => 'Blue', 'warning' => 'Amber', 'success' => 'Green', 'danger' => 'Red', 'primary' => 'Navy', 'gold' => 'Gold', 'dark' => 'Dark', 'muted' => 'Grey'];
    public const STATUS_ICONS = ['fa-list' => 'List', 'fa-hourglass-half' => 'Hourglass', 'fa-circle-check' => 'Check', 'fa-circle-xmark' => 'Cross', 'fa-gavel' => 'Gavel',
        'fa-handshake' => 'Handshake', 'fa-pause' => 'Pause', 'fa-box-archive' => 'Archive', 'fa-flag' => 'Flag', 'fa-clock' => 'Clock', 'fa-ban' => 'Ban', 'fa-star' => 'Star'];

    public static function all(): array
    {
        return [
            'panels' => self::PANELS, 'facings' => self::FACINGS,
            'status_colors' => self::STATUS_COLORS, 'status_icons' => self::STATUS_ICONS,
            'verification_forms' => Section::FORMS,
        ];
    }

    /** "A,B,C" for the Validator "in:" rule. */
    public static function keys(array $list): string
    {
        return implode(',', array_keys($list));
    }
}
