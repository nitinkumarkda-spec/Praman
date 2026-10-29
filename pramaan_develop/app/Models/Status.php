<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Property status master (system rows drive the workflow). */
final class Status extends Model
{
    protected static string $table = 'statuses';
    protected static string $pk = 'status_id';
    protected static array $columns = ['code', 'name', 'name_hi', 'color', 'icon', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
