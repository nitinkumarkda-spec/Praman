<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Snapshot of the scheme role users on a property. */
final class PropertyUser extends Model
{
    protected static string $table = 'property_users';
    protected static string $pk = 'pru_id';
    protected static array $columns = ['property_id', 'role_id', 'user_id', 'is_active', 'created_by'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;
}
