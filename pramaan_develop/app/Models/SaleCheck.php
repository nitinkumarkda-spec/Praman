<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Sale Cell vacancy check. */
final class SaleCheck extends Model
{
    protected static string $table = 'sale_checks';
    protected static string $pk = 'check_id';
    protected static array $columns = ['task_id', 'vacant', 'allotment_mode', 'allotment_date', 'allottee_name', 'checked_by', 'checked_at'];
    protected static bool $timestamps = false;
}
