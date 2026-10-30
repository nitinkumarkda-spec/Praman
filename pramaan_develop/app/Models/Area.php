<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Area master. */
final class Area extends Model
{
    protected static string $table = 'areas';
    protected static string $pk = 'area_id';
    protected static array $columns = ['area_code', 'area_name', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
