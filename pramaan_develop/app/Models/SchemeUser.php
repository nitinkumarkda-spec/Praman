<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Role users mapped to a scheme. */
final class SchemeUser extends Model
{
    protected static string $table = 'scheme_users';
    protected static string $pk = 'map_id';
    protected static array $columns = ['scheme_id', 'role_id', 'user_id', 'is_active', 'created_by'];
}
