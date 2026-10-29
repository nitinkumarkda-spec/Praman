<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** One App Settings value (key / value). */
final class Setting extends Model
{
    protected static string $table = 'settings';
    protected static string $pk = 'setting_key';
    protected static array $columns = ['setting_key', 'setting_value', 'updated_by', 'updated_at'];
    protected static bool $timestamps = false;
}
