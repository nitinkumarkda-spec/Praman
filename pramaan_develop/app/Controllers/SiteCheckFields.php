<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\SiteCode;
use App\Codes\YesNo;
use App\Models\Property;
use App\Services\PropertyService;
use App\Services\Uploads;

/**
 * Physical site check — length × width (area, compared with the register), encroachment (partial / complete + details),
 * road size, corner plot, geo coordinates and site photos. Filled when a property is sent for verification
 * (VerificationController) and, on older rounds, by the Engineering Cell on its task page (TaskController).
 * Only for controllers (uses fail()); method names are prefixed so they never clash with the controller's own.
 */
trait SiteCheckFields
{
    private static int $encroachMax = 300;

    /** @return array the site_inspections row without task_id / round_id */
    private function siteCheck(int $propertyId, int $by): array
    {
        $len = $this->siteMeasure('site_length', 'Length');
        $wid = $this->siteMeasure('site_width', 'Width');
        $unit = PropertyService::validUnit($this->id('site_area_unit'));
        if (!$unit) {
            $this->fail('Select an active unit from the Unit Master.', 'site_area_unit');
        }
        $value = round($len * $wid, 2);
        if ($value > 9999999999.99) {
            $this->fail('Length × width is too large — check the measurement.', 'site_length');
        }

        $enc = $this->request->str('encroachment');
        if (!in_array($enc, YesNo::ALL, true)) {
            $this->fail('Select Yes or No for encroachment.', 'encroachment');
        }
        $type = null;
        $text = '';
        if ($enc === YesNo::YES) {
            $type = $this->request->str('encroachment_type');
            if (!isset(SiteCode::ENCROACHMENT_TYPES[$type])) {
                $this->fail('Select partial or complete encroachment.', 'encroachment_type');
            }
            $text = (string) preg_replace('/\s+/u', ' ', $this->request->str('encroachment_details'));
            if (mb_strlen($text) < 5) {
                $this->fail('Enter the details of the encroachment (at least 5 characters).', 'encroachment_details');
            }
            if (mb_strlen($text) > self::$encroachMax) {
                $this->fail('Details of encroachment may be at most ' . self::$encroachMax . ' characters.', 'encroachment_details');
            }
            if (preg_match('/[<>]/', $text)) {
                $this->fail('Details of encroachment may not contain < or >.', 'encroachment_details');
            }
        }

        $road = $this->request->str('road_width');
        if (!preg_match('/^\d{1,3}$/', $road) || (int) $road < 1) {
            $this->fail('Enter the road size as a whole number (1–999, no decimals).', 'road_width');
        }
        $roadUnit = $this->request->str('road_width_unit');
        if (!isset(SiteCode::ROAD_UNITS[$roadUnit])) {
            $this->fail('Select feet or meter for the road size.', 'road_width_unit');
        }
        $c = $this->request->str('is_corner');
        if (!in_array($c, YesNo::ALL, true)) {
            $this->fail('Select Yes or No for “Corner plot”.', 'is_corner');
        }
        $lat = $this->siteCoordinate('latitude', 'Latitude', 2, 90);
        $lng = $this->siteCoordinate('longitude', 'Longitude', 3, 180);

        $p = Property::find($propertyId);
        // a property entered without an area has nothing to compare with: difference 0, variance empty
        $recorded = $p['area_value'] !== null;
        $diff = $recorded ? round(round(PropertyService::convert($value, $unit, $p['unit_id']), 2) - (float) $p['area_value'], 2) : 0.0;
        return [
            'length_value' => $len, 'width_value' => $wid,
            'area_value' => $value, 'unit_id' => $unit, 'area_sqm' => PropertyService::toSqm($value, $unit),
            'recorded_value' => $p['area_value'], 'recorded_unit_id' => $p['unit_id'], 'difference' => abs($diff),
            'difference_pct' => $p['area_value'] ? round(abs($diff) / (float) $p['area_value'] * 100, 2) : 0,
            'variance' => !$recorded ? null : (abs($diff) < 0.01 ? SiteCode::SAME : ($diff < 0 ? SiteCode::LESS : SiteCode::MORE)),
            'encroachment' => $enc, 'encroachment_type' => $type, 'encroachment_details' => $text !== '' ? $text : null,
            'road_width' => (int) $road, 'road_width_unit' => $roadUnit, 'is_corner' => $c === YesNo::YES ? 1 : 0,
            'latitude' => $lat, 'longitude' => $lng, 'measured_by' => $by, 'measured_at' => now(),
        ];
    }

    /** Site photos: at least one JPG / JPEG / PNG (max 5 MB each, up to 10). */
    private function sitePhotos(): array
    {
        $files = $this->request->files('site_photos');
        if (!$files) {
            $this->fail('Upload at least one site photo (JPG, JPEG or PNG).', 'site_photos');
        }
        $checked = Uploads::checkImages($files, 'Site photos');
        if (is_string($checked)) {
            $this->fail($checked, 'site_photos');
        }
        return $checked;
    }

    /** Positive number with at most 2 decimals. */
    private function siteMeasure(string $field, string $label): float
    {
        $raw = $this->request->str($field);
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) {
            $this->fail($label . ': enter a positive number with at most 2 decimals.', $field);
        }
        return round((float) $raw, 2);
    }

    /** Decimal degrees, up to 8 digits after the decimal point, within ±$max. */
    private function siteCoordinate(string $field, string $label, int $intDigits, int $max): float
    {
        $raw = $this->request->str($field);
        if (!preg_match('/^-?\d{1,' . $intDigits . '}(\.\d{1,8})?$/', $raw) || abs((float) $raw) > $max) {
            $this->fail($label . ': enter a number between -' . $max . ' and ' . $max . ' with at most 8 decimals.', $field);
        }
        return round((float) $raw, 8);
    }
}
