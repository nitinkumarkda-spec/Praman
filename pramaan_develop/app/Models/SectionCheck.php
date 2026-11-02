<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Sale / Auction / General section answers given with a task decision. */
final class SectionCheck extends Model
{
    protected static string $table = 'section_checks';
    protected static string $pk = 'check_id';
    protected static array $columns = ['task_id', 'is_sale_allotted', 'allottee_name', 'allotment_date',
        'is_auctioned', 'auction_date', 'is_inst_allotted', 'is_inst_in_process', 'inst_name', 'inst_allotment_date', 'checked_by', 'checked_at'];
    protected static bool $timestamps = false;
}
