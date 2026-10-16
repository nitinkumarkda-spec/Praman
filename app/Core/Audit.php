<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Insert-only audit trail: who, what, when, from where.
 * Audit failures are logged but never break the user's action.
 */
final class Audit
{
    public static function log(
        string $entityType,
        ?int $entityId,
        string $action,
        ?array $old = null,
        ?array $new = null,
        ?int $propertyId = null
    ): void {
        try {
            $user = Auth::user();
            $req = Request::current();
            // While a Super Admin is logged in as someone else, record who really acted.
            $imp = Auth::impersonator();
            if ($imp !== null && $user !== null) {
                $user['name'] = mb_substr($user['name'] . ' [by Super Admin: ' . $imp['name'] . ']', 0, 120);
                $new = ($new ?? []) + ['_impersonated_by_admin_id' => $imp['id']];
            }
            $stmt = Database::prepare(
                'INSERT INTO {audit_logs} (entity_type, entity_id, property_id, action, old_value, new_value,
                    performed_by, performed_by_name, performed_by_designation, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $entityType,
                $entityId,
                $propertyId,
                $action,
                $old === null ? null : json_encode(self::scrub($old), JSON_UNESCAPED_UNICODE),
                $new === null ? null : json_encode(self::scrub($new), JSON_UNESCAPED_UNICODE),
                $user['user_id'] ?? null,
                $user['name'] ?? null,
                $user['designation'] ?? null,
                $req->ip(),
                $req->userAgent(),
            ]);
        } catch (Throwable $e) {
            Logger::exception($e);
        }
    }

    /** Never store secrets in the audit trail. */
    private static function scrub(array $data): array
    {
        foreach (['password', 'password_hash', 'new_password', 'confirm_password', 'current_password', '_csrf'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = '***';
            }
        }
        return $data;
    }
}
