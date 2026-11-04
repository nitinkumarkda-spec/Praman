<?php
declare(strict_types=1);

namespace App\Models;

use App\Codes\PropertyStatus;
use App\Core\Model;

/** Vacant property in the register. */
final class Property extends Model
{
    protected static string $table = 'properties';
    protected static string $pk = 'property_id';
    protected static array $columns = ['scheme_id', 'sector_id', 'plot_no', 'plot_type_id', 'area_value', 'unit_id',
        'area_sqm', 'is_corner', 'remarks', 'reserve_price', 'status_id', 'current_round_id', 'ready_round_id', 'valid_till', 'is_active', 'created_by', 'updated_by'];
    protected static array $floats = ['reserve_price'];


    /** Active Auction Ready properties whose validity compares with $date ($op: '<' expired, '<=' due). */
    public static function readyValidTill(string $op, string $date): array
    {
        return static::where(['is_active' => 1, 'status_id' => PropertyStatus::AUCTION_READY, 'valid_till !=' => null, 'valid_till ' . $op => $date]);
    }
}
