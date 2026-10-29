<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Verification round of a property. */
final class Round extends Model
{
    protected static string $table = 'rounds';
    protected static string $pk = 'round_id';
    protected static array $columns = ['property_id', 'round_no', 'round_status', 'result', 'result_note', 'initiated_by', 'initiated_at', 'due_date',
        'resend_reason', 'closed_by', 'closed_at', 'valid_from', 'valid_till'];
}
