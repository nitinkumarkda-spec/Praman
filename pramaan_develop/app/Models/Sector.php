<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Sector of a scheme. */
final class Sector extends Model
{
    protected static string $table = 'sectors';
    protected static string $pk = 'sector_id';
    protected static array $columns = ['scheme_id', 'sector_name', 'sort_order', 'is_active', 'created_by', 'updated_by'];
}
