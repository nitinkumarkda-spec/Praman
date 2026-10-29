<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Assembly constituency master. */
final class Assembly extends Model
{
    protected static string $table = 'assemblies';
    protected static string $pk = 'assembly_id';
    protected static array $columns = ['ac_no', 'ac_name', 'name_hi', 'district', 'mla_name', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
