<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Scheme (yojana). */
final class Scheme extends Model
{
    protected static string $table = 'schemes';
    protected static string $pk = 'scheme_id';
    protected static array $columns = ['scheme_code', 'scheme_name', 'scheme_type', 'zone_id', 'year_of_scheme', 'is_active', 'created_by', 'updated_by'];
}
