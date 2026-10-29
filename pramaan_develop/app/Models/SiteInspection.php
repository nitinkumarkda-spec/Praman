<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Engineering Cell site measurement + encroachment. */
final class SiteInspection extends Model
{
    protected static string $table = 'site_inspections';
    protected static string $pk = 'inspection_id';
    protected static array $columns = ['task_id', 'area_value', 'area_unit', 'area_sqm', 'recorded_value', 'recorded_unit', 'difference', 'difference_pct',
        'variance', 'encroachment', 'encroachment_details', 'measured_by', 'measured_at'];
    protected static bool $timestamps = false;
}
