<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Ward master (belongs to a zone). */
final class Ward extends Model
{
    protected static string $table = 'wards';
    protected static string $pk = 'ward_id';
    protected static array $columns = ['ward_no', 'ward_name', 'zone_id', 'councillor', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
