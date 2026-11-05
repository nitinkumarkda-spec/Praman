<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\AttachmentContext;
use App\Codes\RoundStatus;
use App\Codes\Section;
use App\Codes\SiteCode;
use App\Codes\TaskStatus;
use App\Codes\YesNo;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Property;
use App\Models\Role;
use App\Models\Round;
use App\Models\SectionCheck;
use App\Models\SiteInspection;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Services\PropertyService;
use App\Services\Uploads;
use App\Services\Verification;
use Throwable;

/** Role User → give No Objection / Objection on a task (+ section checks and attachments). */
final class TaskController extends Controller
{
    private const ENCROACH_MAX = 300;

    public function respond(): void
    {
        $me = (int) Auth::id();
        $t = Task::find($this->id());
        if (!$t || !TaskAssignee::exists(['task_id' => $t['task_id'], 'user_id' => $me])) {
            $this->fail('Task not found or not assigned to you.');
        }
        $remarks = $this->request->str('remarks');
        if (mb_strlen($remarks) > 1000 || preg_match('/[<>]/', $remarks)) {
            $this->fail('Remark may be at most 1000 characters and may not contain < or >.', 'remarks');
        }
        $round = Round::find($t['round_id']);
        if ($round['round_status'] !== RoundStatus::OPEN || $t['task_status'] !== TaskStatus::PENDING) {
            $this->fail('This task is no longer pending.');
        }
        $role = Role::find($t['role_id']);
        $explicitDecision = $this->request->str('decision');
        $site = ($explicitDecision === '' && $role['verification_form'] === Section::SITE) ? $this->siteCheck($round['property_id'], $me) : null;
        $photos = $site ? $this->sitePhotos() : [];
        if ($site) {
            // Engineering gives a site report, not a decision: encroachment = Objection, otherwise No Objection
            $decision = $site['encroachment'] === YesNo::YES ? TaskStatus::REJECTED : TaskStatus::APPROVED;
            if ($decision === TaskStatus::REJECTED) {
                $found = SiteCode::ENCROACHMENT_TYPES[$site['encroachment_type']] . ' found — ' . $site['encroachment_details'];
                $remarks = mb_substr($found . ($remarks !== '' ? ' ' . $remarks : ''), 0, 1000);
            }
            $section = null;
            $letters = [];
        } else {
            $section = $explicitDecision === '' ? $this->sectionCheck($role['verification_form'], $me) : null;
            $letters = $section ? $this->letters($section) : [];
            $taken = $section && (!empty($section['is_sale_allotted']) || !empty($section['is_auctioned']) || !empty($section['is_inst_allotted']) || !empty($section['is_inst_in_process']));
            $decision = $taken ? TaskStatus::REJECTED : TaskStatus::APPROVED;
        }

        $explicitDecision = $this->request->str('decision');
        if (in_array($explicitDecision, TaskStatus::DECISIONS, true)) {
            if (trim($remarks) === '') {
                $this->fail('Please enter a description / remark before submitting.', 'remarks');
            }
            $decision = $explicitDecision;
        }

        $statusId = $this->id('status_id') ?: null;
        if ($statusId) {
            $stObj = \App\Models\Status::find($statusId);
            if (!$stObj || !$stObj['is_active']) {
                $this->fail('Please select an active status.', 'status_id');
            }
        }

        $files = $this->request->files('attachments');
        $checked = Uploads::check($files);
        if (is_string($checked)) {
            $this->fail($checked);
        }

        $stored = [];
        try {
            $res = Database::transaction(function () use ($t, $round, $decision, $remarks, $me, $site, $photos, $section, $letters, $checked, $statusId, &$stored) {
                $saved = [];
                $ctx = ['task_id' => $t['task_id'], 'round_id' => $t['round_id'], 'property_id' => $round['property_id'], 'by' => $me];
                foreach ($photos as $f) {
                    $a = Uploads::store($f, $ctx + ['context' => AttachmentContext::SITE_PHOTO]);
                    $stored[] = $a['path'];
                    $saved[] = $a['file_name'];
                }
                foreach ($letters as $context => $f) {
                    $a = Uploads::store($f, $ctx + ['context' => $context]);
                    $stored[] = $a['path'];
                    $saved[] = $a['file_name'];
                }
                foreach ($checked as $f) {
                    $a = Uploads::store($f, $ctx);
                    $stored[] = $a['path'];
                    $saved[] = $a['file_name'];
                }
                $res = Verification::respond($t['task_id'], $decision, $remarks !== '' ? $remarks : null, $me, ['attachments' => $saved, 'status_id' => $statusId]);
                if (!$res['ok']) {
                    throw new \DomainException($res['message']);
                }
                if ($site) {
                    SiteInspection::insert(['task_id' => $t['task_id']] + $site);
                }
                if ($section) {
                    SectionCheck::insert(['task_id' => $t['task_id']] + $section);
                }
                return $res + ['files' => count($saved)];
            });
        } catch (\DomainException $e) {
            Uploads::discard($stored);
            $this->fail($e->getMessage());
        } catch (Throwable $e) {
            Uploads::discard($stored);
            throw $e;
        }
        $back = $this->request->str('back');
        $target = $back !== '' ? $back : '/admin/masters/properties';
        $this->done($res['message'] . ($res['files'] ? ' ' . $res['files'] . ' attachment(s) uploaded.' : ''), $target);
    }

    /**
     * Engineering Cell: physical site check — length × width (area), encroachment (partial / complete + details),
     * road width, corner plot and geo coordinates. Site photos are checked separately (sitePhotos()).
     */
    private function siteCheck(int $propertyId, int $by): array
    {
        $len = $this->measure('site_length', 'Length');
        $wid = $this->measure('site_width', 'Width');
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
            $text = preg_replace('/\s+/u', ' ', $this->request->str('encroachment_details'));
            if (mb_strlen($text) < 5) {
                $this->fail('Enter the details of the encroachment (at least 5 characters).', 'encroachment_details');
            }
            if (mb_strlen($text) > self::ENCROACH_MAX) {
                $this->fail('Details of encroachment may be at most ' . self::ENCROACH_MAX . ' characters.', 'encroachment_details');
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
        $corner = $this->yesNo('is_corner', 'Select Yes or No for “Corner plot”.');
        $lat = $this->coordinate('latitude', 'Latitude', 2, 90);
        $lng = $this->coordinate('longitude', 'Longitude', 3, 180);

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
            'road_width' => (int) $road, 'road_width_unit' => $roadUnit, 'is_corner' => $corner,
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
    private function measure(string $field, string $label): float
    {
        $raw = $this->request->str($field);
        if (!preg_match('/^\d{1,7}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) {
            $this->fail($label . ': enter a positive number with at most 2 decimals.', $field);
        }
        return round((float) $raw, 2);
    }

    /** Decimal degrees, up to 8 digits after the decimal point, within ±$max. */
    private function coordinate(string $field, string $label, int $intDigits, int $max): float
    {
        $raw = $this->request->str($field);
        if (!preg_match('/^-?\d{1,' . $intDigits . '}(\.\d{1,8})?$/', $raw) || abs((float) $raw) > $max) {
            $this->fail($label . ': enter a number between -' . $max . ' and ' . $max . ' with at most 8 decimals.', $field);
        }
        return round((float) $raw, 8);
    }

    /**
     * Department-wise section: Sale (Sale Cell), Auction (Auction Cell), General (every other cell
     * except Engineering, which has its site inspection). null when the cell has no section.
     * Any "Yes" means the property is not available: only Objection is allowed.
     */
    private function sectionCheck(string $form, int $by): ?array
    {
        $out = ['checked_by' => $by, 'checked_at' => now()];
        if ($form === Section::SALE) {
            $out['is_sale_allotted'] = $this->yesNo('is_sale_allotted', 'Sale: select Yes or No for “Allotted”.');
            if ($out['is_sale_allotted']) {
                $out['allottee_name'] = $this->person('allottee_name', 'Name of allottee / mutation');
                $out['allotment_date'] = $this->pastDate('allotment_date', 'Date of allotment / mutation');
            }
        }
        if ($form === Section::AUCTION) {
            $out['is_auctioned'] = $this->yesNo('is_auctioned', 'Auction: select Yes or No for “Auctioned”.');
            if ($out['is_auctioned']) {
                $out['auction_date'] = $this->pastDate('auction_date', 'Date of auction');
            }
        }
        if (!in_array($form, Section::OWN_FORM, true)) {
            $out['is_inst_allotted'] = $this->yesNo('is_inst_allotted', 'General: select Yes or No for “Allotment to an organisation / institution”.');
            // "Yes" = occupied: allotted, or allotment in progress (no exact date yet)
            $out['is_inst_in_process'] = $out['is_inst_allotted'] && $this->request->input('is_inst_in_process') === '1' ? 1 : 0;
            if ($out['is_inst_allotted']) {
                $out['inst_name'] = $this->validate(['inst_name' => ['Name of organisation / institution', 'required|min:3|max:150|orgname']])['inst_name'];
                if (!$out['is_inst_in_process']) {
                    $out['inst_allotment_date'] = $this->pastDate('inst_allotment_date', 'Date of allotment (organisation / institution)');
                }
            }
        }
        if (count($out) === 2) {
            return null; // Engineering: site inspection only
        }
        return $out;
    }

    /** Allotment / mutation letter PDF, required when the Sale answer is "Yes". */
    private function letters(array $check): array
    {
        if (empty($check['is_sale_allotted'])) {
            return [];
        }
        $f = $this->request->file('allotment_letter');
        if (!$f) {
            $this->fail('Upload the allotment letter / mutation letter (PDF, max 5 MB).', 'allotment_letter');
        }
        $checked = Uploads::checkPdf($f, 'Allotment letter / mutation letter');
        if (is_string($checked)) {
            $this->fail($checked, 'allotment_letter');
        }
        return [AttachmentContext::ALLOTMENT_LETTER => $checked];
    }

    private function yesNo(string $field, string $message): int
    {
        $v = $this->request->str($field);
        if (!in_array($v, YesNo::ALL, true)) {
            $this->fail($message, $field);
        }
        return $v === YesNo::YES ? 1 : 0;
    }

    /** Name of a person: letters and spaces only. */
    private function person(string $field, string $label): string
    {
        $_POST[$field] = preg_replace('/\s+/u', ' ', $this->request->str($field));
        return $this->validate([$field => [$label, 'required|min:3|max:100|alpha_space']])[$field];
    }

    /** dd/mm/yyyy, not in the future → Y-m-d. */
    private function pastDate(string $field, string $label): string
    {
        $iso = (string) Validator::dmy($this->validate([$field => [$label, 'required|dmy']])[$field]);
        if ($iso > today()) {
            $this->fail($label . ' cannot be in the future.', $field);
        }
        if ($iso < '1950-01-01') {
            $this->fail($label . ' must be after 01/01/1950.', $field);
        }
        return $iso;
    }
}
