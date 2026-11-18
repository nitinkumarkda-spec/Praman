<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Scheme type master — every row is added / edited / removed from Masters › Scheme Type (nothing is fixed in code). */
final class SchemeType extends Model
{
    protected static string $table = 'scheme_types';
    protected static string $pk = 'scheme_type_id';
    protected static array $columns = ['name', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
