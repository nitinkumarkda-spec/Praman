<?php
declare(strict_types=1);

namespace App\Services;

use App\Codes\AttachmentContext;
use App\Codes\Panel;
use App\Codes\PropertyStatus;
use App\Core\Database;
use App\Models\Attachment;
use App\Models\Property;
use App\Models\PropertyUser;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\Unit;
use App\Models\User;
use Throwable;

/**
 * Property register rules shared by the Data Entry screens and the seeder.
 */
final class PropertyService
{
    /** Site plan PDF uploaded by Data Entry (latest one is current; older uploads stay as history). */
    public const SITE_PLAN = AttachmentContext::SITE_PLAN;

    /** Area in square metres, using the factor from the Unit Master. */
    public static function toSqm(float $value, string $unit): float
    {
        return round($value * (Unit::factor($unit) ?? 1.0), 4);
    }

    /** Value in unit $from expressed in unit $to. */
    public static function convert(float $value, string $from, string $to): float
    {
        return $from === $to ? $value : $value * (Unit::factor($from) ?? 1.0) / (Unit::factor($to) ?? 1.0);
    }

    /** Active unit code for a new entry (or the unit the record already has); null when invalid. */
    public static function validUnit(string $code, ?string $current = null): ?string
    {
        $u = Unit::first(['code' => $code]);
        return $u && ($u['is_active'] || $code === $current) ? $u['code'] : null;
    }

    /** Latest uploaded document of a kind (SITE_PLAN …). */
    public static function doc(int $propertyId, string $context): ?array
    {
        return Attachment::first(['property_id' => $propertyId, 'context' => $context], 'attachment_id DESC');
    }

    /** Mandatory active roles that have no active, correctly-mapped user in the scheme. */
    public static function missingMandatoryRoles(int $schemeId): array
    {
        $missing = [];
        foreach (Role::where(['is_active' => 1, 'is_mandatory' => 1], 'sort_order') as $r) {
            $ok = false;
            foreach (SchemeUser::where(['scheme_id' => $schemeId, 'role_id' => $r['role_id'], 'is_active' => 1]) as $m) {
                $u = User::find($m['user_id']);
                if ($u && $u['is_active'] && $u['panel'] === Panel::ROLE_USER && (int) $u['role_id'] === (int) $r['role_id']) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $missing[] = $r['role_name'];
            }
        }
        return $missing;
    }

    /**
     * Create a Listed property and copy the scheme's role users onto it (snapshot).
     * @param array|null $sitePlan checked site plan PDF (Uploads::checkPdf)
     * @return array{property: array, snap: int}
     */
    public static function create(array $d, int $by, ?array $sitePlan = null): array
    {
        $stored = [];
        try {
            return Database::transaction(static function () use ($d, $by, $sitePlan, &$stored): array {
                $id = Property::insert($d + ['remarks' => null, 'current_status' => PropertyStatus::LISTED, 'is_active' => 1, 'created_by' => $by]);
                $code = 'PRM-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
                Property::update($id, ['property_code' => $code]);
                if ($sitePlan) {
                    $stored[] = Uploads::store($sitePlan, ['property_id' => $id, 'context' => self::SITE_PLAN, 'by' => $by])['path'];
                }

                $snap = 0;
                foreach (SchemeUser::where(['scheme_id' => $d['scheme_id'], 'is_active' => 1]) as $m) {
                    $r = Role::find($m['role_id']);
                    $u = User::find($m['user_id']);
                    if ($r && $r['is_active'] && $u && $u['is_active'] && $u['panel'] === Panel::ROLE_USER && (int) $u['role_id'] === (int) $m['role_id']
                        && !PropertyUser::exists(['property_id' => $id, 'role_id' => $m['role_id'], 'user_id' => $m['user_id']])) {
                        PropertyUser::insert(['property_id' => $id, 'role_id' => $m['role_id'], 'user_id' => $m['user_id'], 'is_active' => 1, 'created_by' => $by]);
                        $snap++;
                    }
                }
                $p = Property::find($id);
                $scheme = Scheme::find($d['scheme_id']);
                Notify::send(Notify::panelUsers(Panel::OIC), [
                    'type' => 'PROPERTY', 'icon' => 'fa-building-circle-check', 'title' => 'New property listed — Plot ' . $p['plot_no'],
                    'message' => $scheme['scheme_name'] . ' · ' . $code . ' is ready to be sent for verification.', 'link' => '/oic/properties/' . $id,
                ]);
                return ['property' => $p, 'snap' => $snap];
            });
        } catch (Throwable $e) {
            Uploads::discard($stored);
            throw $e;
        }
    }
}
