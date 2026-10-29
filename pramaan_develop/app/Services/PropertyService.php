<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Property;
use App\Models\PropertyUser;
use App\Models\Role;
use App\Models\Scheme;
use App\Models\SchemeUser;
use App\Models\User;

/**
 * Property register rules shared by the Data Entry screens and the seeder.
 */
final class PropertyService
{
    public const SQFT_TO_SQM = 0.092903;

    public static function toSqm(float $value, string $unit): float
    {
        return round($unit === 'SQFT' ? $value * self::SQFT_TO_SQM : $value, 4);
    }

    /** Mandatory active roles that have no active, correctly-mapped user in the scheme. */
    public static function missingMandatoryRoles(int $schemeId): array
    {
        $missing = [];
        foreach (Role::where(['is_active' => 1, 'is_mandatory' => 1], 'sort_order') as $r) {
            $ok = false;
            foreach (SchemeUser::where(['scheme_id' => $schemeId, 'role_id' => $r['role_id'], 'is_active' => 1]) as $m) {
                $u = User::find($m['user_id']);
                if ($u && $u['is_active'] && $u['panel'] === 'ROLE_USER' && (int) $u['role_id'] === (int) $r['role_id']) {
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
     * @return array{property: array, snap: int}
     */
    public static function create(array $d, int $by): array
    {
        return Database::transaction(static function () use ($d, $by): array {
            $id = Property::insert([
                'scheme_id' => $d['scheme_id'], 'sector_id' => $d['sector_id'], 'ward_id' => $d['ward_id'] ?? null, 'assembly_id' => $d['assembly_id'] ?? null,
                'plot_no' => $d['plot_no'], 'property_type' => $d['property_type'], 'area_value' => $d['area_value'], 'area_unit' => $d['area_unit'],
                'area_sqm' => $d['area_sqm'], 'remarks' => $d['remarks'] ?? null, 'current_status' => 'LISTED', 'is_active' => 1, 'created_by' => $by,
            ]);
            $code = 'PRM-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            Property::update($id, ['property_code' => $code]);

            $snap = 0;
            foreach (SchemeUser::where(['scheme_id' => $d['scheme_id'], 'is_active' => 1]) as $m) {
                $r = Role::find($m['role_id']);
                $u = User::find($m['user_id']);
                if ($r && $r['is_active'] && $u && $u['is_active'] && $u['panel'] === 'ROLE_USER' && (int) $u['role_id'] === (int) $m['role_id']
                    && !PropertyUser::exists(['property_id' => $id, 'role_id' => $m['role_id'], 'user_id' => $m['user_id']])) {
                    PropertyUser::insert(['property_id' => $id, 'role_id' => $m['role_id'], 'user_id' => $m['user_id'], 'is_active' => 1, 'created_by' => $by]);
                    $snap++;
                }
            }
            $p = Property::find($id);
            $scheme = Scheme::find($d['scheme_id']);
            Notify::send(Notify::panelUsers('OIC'), [
                'type' => 'PROPERTY', 'icon' => 'fa-building-circle-check', 'title' => 'New property listed — Plot ' . $p['plot_no'],
                'message' => $scheme['scheme_name'] . ' · ' . $code . ' is ready to be sent for verification.', 'link' => '/oic/properties/' . $id,
            ]);
            return ['property' => $p, 'snap' => $snap];
        });
    }
}
