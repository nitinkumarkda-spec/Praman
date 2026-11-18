<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Sub plot type master — every row is added / edited / removed from Masters › Sub Plot Type (nothing is fixed in code). */
final class SubPlotType extends Model
{
    protected static string $table = 'sub_plot_types';
    protected static string $pk = 'sub_plot_type_id';
    protected static array $columns = ['name', 'name_hi', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
}
