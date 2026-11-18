<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Developer type master — every row is added / edited / removed from Masters › Developer Type (nothing is fixed in code). */
final class DeveloperType extends Model
{
    protected static string $table = 'developer_types';
    protected static string $pk = 'developer_type_id';
    protected static array $columns = ['name', 'default_developer', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
