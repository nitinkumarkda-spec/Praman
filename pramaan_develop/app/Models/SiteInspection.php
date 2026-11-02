<?php
declare(strict_types=1);

namespace App\Models;

use App\Codes\SiteCode;
use App\Core\Model;

/** Engineering Cell physical site check: length × width, encroachment, road, corner, geo location. */
final class SiteInspection extends Model
{
    public const ENCROACHMENT_TYPES = SiteCode::ENCROACHMENT_TYPES;
    public const ROAD_UNITS = SiteCode::ROAD_UNITS;

    protected static string $table = 'site_inspections';
    protected static string $pk = 'inspection_id';
    protected static array $columns = ['task_id', 'length_value', 'width_value', 'area_value', 'area_unit', 'area_sqm', 'recorded_value', 'recorded_unit',
        'difference', 'difference_pct', 'variance', 'encroachment', 'encroachment_type', 'encroachment_details', 'road_width', 'road_width_unit',
        'is_corner', 'latitude', 'longitude', 'measured_by', 'measured_at'];
    protected static array $floats = ['length_value', 'width_value', 'latitude', 'longitude'];
    protected static array $ints = ['road_width'];
    protected static bool $timestamps = false;
}
