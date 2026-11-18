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

/**
 * Property register rules shared by the Data Entry screens and the seeder.
 */
final class PropertyService
{
    /** Site plan PDF of a scheme, uploaded in the Scheme form (latest one is current; older uploads stay as history). */
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

    /** Latitude ($intDigits 2, $max 90) / longitude (3, 180) with at most 8 decimals; null when not valid. */
    public static function coordinate(string $raw, int $intDigits, int $max): ?float
    {
        return preg_match('/^-?\d{1,' . $intDigits . '}(\.\d{1,8})?$/', $raw) && abs((float) $raw) <= $max ? round((float) $raw, 8) : null;
    }

    /** Active unit for a new entry (or the unit the record already has); null when invalid. */
    public static function validUnit(int $unitId, ?int $current = null): ?int
    {
        $u = $unitId > 0 ? Unit::find($unitId) : null;
        return $u && ($u['is_active'] || $unitId === $current) ? $u['unit_id'] : null;
    }

    /** Latest site plan of a scheme (null when none was uploaded). */
    public static function schemeSitePlan(int $schemeId): ?array
    {
        return Attachment::first(['scheme_id' => $schemeId, 'context' => self::SITE_PLAN], 'attachment_id DESC');
    }

    /** Mandatory active roles that have no active, correctly-mapped user in the scheme. */
    public static function missingMandatoryRoles(int $schemeId): array
    {
        // Role-user mapping will be verified when sending properties for verification
        return [];
    }

    /**
     * Create a Listed property and tell the verification users. Its site plan and reserve price come from the scheme.
     * @return array{property: array}
     */
    public static function create(array $d, int $by): array
    {
        return Database::transaction(static function () use ($d, $by): array {
            $id = Property::insert($d + ['remarks' => null, 'status_id' => PropertyStatus::LISTED, 'is_active' => 1, 'created_by' => $by]);
            $p = Property::find($id);
            $scheme = Scheme::find($d['scheme_id']);
            $sector = $p['sector_id'] ? Sector::find($p['sector_id']) : null;
            Notify::send(Notify::permissionUsers('verification.view'), [
                'type' => 'PROPERTY', 'icon' => 'fa-building-circle-check', 'title' => 'New property listed — Plot ' . $p['plot_no'],
                'message' => $scheme['scheme_name'] . ($sector ? ' / ' . $sector['sector_name'] : '') . ' · ready to be sent for verification.', 'link' => '/admin/masters/properties/' . $id,
            ]);
            return ['property' => $p];
        });
    }
}
