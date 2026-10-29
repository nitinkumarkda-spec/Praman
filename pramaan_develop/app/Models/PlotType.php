<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Plot / property type master. */
final class PlotType extends Model
{
    protected static string $table = 'plot_types';
    protected static string $pk = 'plot_type_id';
    protected static array $columns = ['code', 'name', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
