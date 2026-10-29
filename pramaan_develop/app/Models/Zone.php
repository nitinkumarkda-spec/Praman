<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Zone master. */
final class Zone extends Model
{
    protected static string $table = 'zones';
    protected static string $pk = 'zone_id';
    protected static array $columns = ['zone_code', 'zone_name', 'name_hi', 'area', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
