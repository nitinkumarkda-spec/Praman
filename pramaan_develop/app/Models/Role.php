<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Verification section (Engineering, Legal …). */
final class Role extends Model
{
    protected static string $table = 'roles';
    protected static string $pk = 'role_id';
    protected static array $columns = ['role_code', 'role_name', 'role_name_hi', 'description', 'sla_days', 'is_mandatory', 'sort_order', 'is_active', 'created_by', 'updated_by'];
}
