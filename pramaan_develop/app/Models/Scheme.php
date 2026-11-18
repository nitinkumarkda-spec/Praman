<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Scheme (yojana). Its site plan PDF is an attachment with scheme_id (context SITE_PLAN). */
final class Scheme extends Model
{
    protected static string $table = 'schemes';
    protected static string $pk = 'scheme_id';
    protected static array $columns = ['scheme_name', 'scheme_landmark', 'scheme_type_id', 'plot_type_id', 'reserve_price', 'developer_type_id', 'developer_name',
        'approve_date', 'rera_registration_no', 'area_id', 'is_active', 'created_by', 'updated_by'];
    protected static array $floats = ['reserve_price'];
}
