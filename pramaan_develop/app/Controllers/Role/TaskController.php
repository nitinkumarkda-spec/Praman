<?php
declare(strict_types=1);

namespace App\Controllers\Role;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Validator;
use App\Models\Property;
use App\Models\Role;
use App\Models\Round;
use App\Models\SaleCheck;
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
    private const SITE_ROLE = 'ENGINEERING_CELL';
    private const SALE_ROLE = 'SALE_CELL';
    private const MAX_WORDS = 100;
    private const NOT_VACANT_MSG = 'A property that is not vacant cannot get “No Objection” — choose “Objection”.';

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
        $decision = $this->request->str('decision');
        if ($decision !== 'APPROVED' && $decision !== 'REJECTED') {
            $this->fail('Choose No Objection or Objection.', 'decision');
        }
        $round = Round::find($t['round_id']);
        if ($round['round_status'] !== 'OPEN' || $t['task_status'] !== 'PENDING') {
            $this->fail('This task is no longer pending.');
        }
        if ($decision === 'REJECTED' && mb_strlen($remarks) < 5) {
            $this->fail('Objection reason is mandatory (at least 5 characters).', 'remarks');
        }
        $role = Role::find($t['role_id']);
        $site = $role['role_code'] === self::SITE_ROLE ? $this->siteCheck($round['property_id'], $me) : null;
        $sale = $role['role_code'] === self::SALE_ROLE ? $this->saleCheck($decision, $me) : null;

        $files = $this->request->files('attachments');
        $checked = Uploads::check($files);
        if (is_string($checked)) {
            $this->fail($checked);
        }

        $stored = [];
        try {
            $res = Database::transaction(function () use ($t, $round, $decision, $remarks, $me, $site, $sale, $checked, &$stored) {
                $saved = [];
                foreach ($checked as $f) {
                    $a = Uploads::store($f, ['task_id' => $t['task_id'], 'round_id' => $t['round_id'], 'property_id' => $round['property_id'], 'by' => $me]);
                    $stored[] = $a['path'];
                    $saved[] = $a['file_name'];
                }
                $res = Verification::respond($t['task_id'], $decision, $remarks !== '' ? $remarks : null, $me, ['attachments' => $saved]);
                if (!$res['ok']) {
                    throw new \DomainException($res['message']);
                }
                if ($site) {
                    SiteInspection::insert(['task_id' => $t['task_id']] + $site);
                }
                if ($sale) {
                    SaleCheck::insert(['task_id' => $t['task_id']] + $sale);
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
        $this->done($res['message'] . ($res['files'] ? ' ' . $res['files'] . ' attachment(s) uploaded.' : ''), '/role/tasks?status=pending');
    }

    /** Engineering Cell: actual measured area + encroachment. */
    private function siteCheck(int $propertyId, int $by): array
    {
        $raw = $this->request->str('site_area_value');
        if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $raw) || (float) $raw <= 0) {
            $this->fail('Enter the actual measured area — a positive number with at most 2 decimals.', 'site_area_value');
        }
        $unit = $this->request->str('site_area_unit');
        if ($unit !== 'SQM' && $unit !== 'SQFT') {
            $this->fail('Select the unit of the measured area.', 'site_area_unit');
        }
        $enc = $this->request->str('encroachment');
        if ($enc !== 'YES' && $enc !== 'NO') {
            $this->fail('Select Yes or No for encroachment.', 'encroachment');
        }
        $text = $enc === 'YES' ? $this->request->str('encroachment_details') : '';
        if ($enc === 'YES') {
            if ($text === '') {
                $this->fail('Describe the encroachment (required when the answer is "Yes").', 'encroachment_details');
            }
            if (count(preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY)) > self::MAX_WORDS || mb_strlen($text) > 1000) {
                $this->fail('Keep the description within ' . self::MAX_WORDS . ' words.', 'encroachment_details');
            }
            if (preg_match('/[<>]/', $text)) {
                $this->fail('Description may not contain < or >.', 'encroachment_details');
            }
        }
        $p = Property::find($propertyId);
        $value = (float) $raw;
        $inUnit = $unit === $p['area_unit'] ? $value : ($p['area_unit'] === 'SQM' ? $value * PropertyService::SQFT_TO_SQM : $value / PropertyService::SQFT_TO_SQM);
        $actual = round($inUnit, 2);
        $diff = round($actual - (float) $p['area_value'], 2);
        return [
            'area_value' => $value, 'area_unit' => $unit, 'area_sqm' => PropertyService::toSqm($value, $unit),
            'recorded_value' => $p['area_value'], 'recorded_unit' => $p['area_unit'], 'difference' => abs($diff),
            'difference_pct' => $p['area_value'] ? round(abs($diff) / (float) $p['area_value'] * 100, 2) : 0,
            'variance' => abs($diff) < 0.01 ? 'SAME' : ($diff < 0 ? 'LESS' : 'MORE'),
            'encroachment' => $enc, 'encroachment_details' => $text !== '' ? $text : null, 'measured_by' => $by, 'measured_at' => now(),
        ];
    }

    /** Sale Cell: vacant, or allotment details (then only Objection is allowed). */
    private function saleCheck(string $decision, int $by): array
    {
        $vacant = $this->request->str('vacant');
        if ($vacant !== 'YES' && $vacant !== 'NO') {
            $this->fail('Select Yes or No for vacancy.', 'vacant');
        }
        if ($vacant === 'YES') {
            return ['vacant' => 'YES', 'checked_by' => $by, 'checked_at' => now()];
        }
        $mode = $this->request->str('allotment_mode');
        if (!isset(\App\Core\Lookups::ALLOTMENT_MODES[$mode])) {
            $this->fail('Select the mode of allotment.', 'allotment_mode');
        }
        $date = $this->request->str('allotment_date');
        if (!Validator::isDate($date)) {
            $this->fail('Enter a valid date of allotment.', 'allotment_date');
        }
        if ($date > today()) {
            $this->fail('Date of allotment cannot be in the future.', 'allotment_date');
        }
        $name = $this->request->str('allottee_name');
        if (mb_strlen($name) < 3 || mb_strlen($name) > 100 || !preg_match("/^[\p{L}\p{M} .'()\/-]+$/u", $name)) {
            $this->fail('Enter the name of the allottee (3–100 letters).', 'allottee_name');
        }
        if ($decision !== 'REJECTED') {
            $this->fail(self::NOT_VACANT_MSG);
        }
        return ['vacant' => 'NO', 'allotment_mode' => $mode, 'allotment_date' => $date, 'allottee_name' => $name, 'checked_by' => $by, 'checked_at' => now()];
    }
}
