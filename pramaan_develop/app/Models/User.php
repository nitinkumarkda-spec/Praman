<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Panel user or role user. password_hash never leaves the server. */
final class User extends Model
{
    protected static string $table = 'users';
    protected static string $pk = 'user_id';
    protected static array $columns = ['username', 'emp_code', 'name', 'designation', 'mobile', 'email', 'password_hash', 'panel', 'role_id', 'is_active',
        'must_change_password', 'failed_attempts', 'locked_until', 'last_login_at', 'last_login_ip', 'password_changed_at', 'created_by', 'updated_by'];
}
