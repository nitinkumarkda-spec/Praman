<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Clock;
use App\Core\Request;
use App\Models\AuditLog;

/**
 * Insert-only audit trail: who, what, when, IP, user agent, old/new values.
 */
final class Audit
{
    private const SECRET_KEYS = ['password', 'password_hash', 'new_password', 'confirm_password', 'current_password', 'reset_password', 'reset_confirm'];

    /** Actor override used by the seeder / system jobs: ['user_id','name','designation']. */
    private static ?array $actor = null;

    public static function as(?array $actor, callable $fn): mixed
    {
        $prev = self::$actor;
        self::$actor = $actor;
        try {
            return $fn();
        } finally {
            self::$actor = $prev;
        }
    }

    public static function system(): array
    {
        return ['user_id' => null, 'name' => 'System', 'designation' => 'Automatic'];
    }

    public static function log(string $entity, ?int $entityId, string $action, ?array $old = null, ?array $new = null, ?int $propertyId = null, ?array $actor = null): void
    {
        $a = $actor ?? self::$actor ?? Auth::actor();
        if (!empty($a['impersonator_id'])) {
            $new = array_merge($new ?? [], ['_impersonated_by_admin_id' => $a['impersonator_id']]);
        }
        $req = Request::current();
        AuditLog::insert([
            'entity_type'              => $entity,
            'entity_id'                => $entityId,
            'property_id'              => $propertyId,
            'action'                   => $action,
            'old_value'                => self::json($old),
            'new_value'                => self::json($new),
            'performed_by'             => $a['user_id'] ?? null,
            'performed_by_name'        => mb_substr((string) ($a['name'] ?? 'System'), 0, 120),
            'performed_by_designation' => isset($a['designation']) ? mb_substr((string) $a['designation'], 0, 100) : null,
            'ip_address'               => PHP_SAPI === 'cli' ? '127.0.0.1' : $req->ip(),
            'user_agent'               => PHP_SAPI === 'cli' ? 'PRAMAAN installer' : $req->userAgent(),
            'performed_at'             => Clock::now(),
        ]);
    }

    private static function json(?array $v): ?string
    {
        if ($v === null) {
            return null;
        }
        foreach (self::SECRET_KEYS as $k) {
            if (array_key_exists($k, $v)) {
                $v[$k] = '***';
            }
        }
        return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
}
