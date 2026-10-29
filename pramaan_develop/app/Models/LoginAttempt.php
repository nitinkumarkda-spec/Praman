<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** One row per sign-in attempt (brute-force protection + insights). */
final class LoginAttempt extends Model
{
    protected static string $table = 'login_attempts';
    protected static string $pk = 'attempt_id';
    protected static array $columns = ['username', 'ip_address', 'success', 'attempted_at'];
    protected static bool $timestamps = false;
}
