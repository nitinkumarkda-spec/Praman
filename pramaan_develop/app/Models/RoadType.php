<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Road type master — every row is added / edited / removed from Masters › Road Type (nothing is fixed in code). */
final class RoadType extends Model
{
    protected static string $table = 'road_types';
    protected static string $pk = 'road_type_id';
    protected static array $columns = ['name', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
