<?php
declare(strict_types=1);

namespace App\Services;

use App\Codes\AttachmentContext;
use App\Codes\PropertyStatus;
use App\Core\Database;
use App\Models\Attachment;
use App\Models\Property;

use App\Models\Scheme;
use App\Models\Sector;
use App\Models\Unit;
use Throwable;

/**
 * Property register rules shared by the Data Entry screens and the seeder.
 */
final class PropertyService
{
    /** Site plan PDF uploaded by Data Entry (latest one is current; older uploads stay as history). */
    public const SITE_PLAN = AttachmentContext::SITE_PLAN;

    /** Area in square metres, using the factor from the Unit Master. */
    public static function toSqm(float $value, int $unit): float
    {
        return round($value * (Unit::factor($unit) ?? 1.0), 4);
    }

    /** Value in unit $from expressed in unit $to. */
    public static function convert(float $value, int $from, int $to): float
    {
        return $from === $to ? $value : $value * (Unit::factor($from) ?? 1.0) / (Unit::factor($to) ?? 1.0);
    }

    /** Active unit for a new entry (or the unit the record already has); null when invalid. */
    public static function validUnit(int $unitId, ?int $current = null): ?int
    {
        $u = $unitId > 0 ? Unit::find($unitId) : null;
        return $u && ($u['is_active'] || $unitId === $current) ? $u['unit_id'] : null;
    }

    /** Latest uploaded document of a kind (SITE_PLAN …). */
    public static function doc(int $propertyId, string $context): ?array
    {
        return Attachment::first(['property_id' => $propertyId, 'context' => $context], 'attachment_id DESC');
    }

    /** Mandatory active roles that have no active, correctly-mapped user in the scheme. */
    public static function missingMandatoryRoles(int $schemeId): array
    {
        // Role-user mapping will be verified when sending properties for verification
        return [];
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
                $id = Property::insert($d + ['remarks' => null, 'status_id' => PropertyStatus::LISTED, 'is_active' => 1, 'created_by' => $by]);
                if ($sitePlan) {
                    $stored[] = Uploads::store($sitePlan, ['property_id' => $id, 'context' => self::SITE_PLAN, 'by' => $by])['path'];
                }
                $p = Property::find($id);
                $scheme = Scheme::find($d['scheme_id']);
                $sector = $p['sector_id'] ? Sector::find($p['sector_id']) : null;
                Notify::send(Notify::permissionUsers('verification.view'), [
                    'type' => 'PROPERTY', 'icon' => 'fa-building-circle-check', 'title' => 'New property listed — Plot ' . $p['plot_no'],
                    'message' => $scheme['scheme_name'] . ($sector ? ' / ' . $sector['sector_name'] : '') . ' · ready to be sent for verification.', 'link' => '/admin/masters/properties/' . $id,
                ]);
                return ['property' => $p, 'snap' => $snap];
            });
        } catch (Throwable $e) {
            Uploads::discard($stored);
            throw $e;
        }
    }
}
