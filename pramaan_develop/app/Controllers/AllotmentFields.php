<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Codes\AttachmentContext;
use App\Codes\YesNo;
use App\Core\Validator;
use App\Models\PropertyAllotment;
use App\Models\PropertyTransfer;
use App\Services\Audit;
use App\Services\PropertyService;
use App\Services\Uploads;

/**
 * Allotment details of a property — filled by the Sale section in "Update Property Status" (TaskController)
 * and corrected on Property Register → Allotment (AllotmentController).
 * Is allottee? Yes → allotment date, allottee, possession date, annual lease rate (per unit of the Unit Master),
 * allotment price, every sale (date + transferee) and the lease deed: issued = OTLC ref. no., date, amount, PDF; not issued = amount + date.
 * Only for controllers (uses fail() / validate()); method names are prefixed so they never clash with the controller's own.
 */
trait AllotmentFields
{
    /** Read and check the posted allotment fields. @return array{property_id:int, old:?array, row:array, sales:array, otlc:?array} */
    private function allotmentInput(int $propertyId): array
    {
        $old = PropertyAllotment::first(['property_id' => $propertyId]);
        $row = ['is_allottee' => $this->allotYesNo('is_allottee', 'Select Yes or No for “Is allottee”.')] + array_fill_keys(['allotment_date', 'allottee_name',
            'possession_date', 'annual_lease_rate', 'lease_unit_id', 'allotment_price', 'is_lease_deed_issued', 'otlc_ref_no', 'otlc_issue_date',
            'otlc_amount', 'lease_amount', 'lease_amount_date'], null) + ['times_sold' => 0];
        $sales = [];
        $otlc = null;
        if ($row['is_allottee']) {
            $row['allotment_date'] = $this->allotDate('allotment_date', 'Date of allotment');
            $row['allottee_name'] = $this->allotPerson('allottee_name', 'Name of allottee');
            $row['possession_date'] = $this->allotDate('possession_date', 'Date of possession');
            if ($row['possession_date'] < $row['allotment_date']) {
                $this->fail('Date of possession cannot be before the date of allotment.', 'possession_date');
            }
            $row['annual_lease_rate'] = $this->allotAmount('annual_lease_rate', 'Annual lease rate');
            $row['lease_unit_id'] = PropertyService::validUnit($this->id('lease_unit_id'), $old['lease_unit_id'] ?? null);
            if (!$row['lease_unit_id']) {
                $this->fail('Please select an active unit for the annual lease rate.', 'lease_unit_id');
            }
            $row['allotment_price'] = $this->allotAmount('allotment_price', 'Allotment price');
            $row['times_sold'] = (int) $this->validate(['times_sold' => ['Number of times sold', 'required|between:0,' . AllotmentController::MAX_SALES]])['times_sold'];
            $prev = $row['allotment_date'];
            for ($n = 1; $n <= $row['times_sold']; $n++) {
                $label = self::ordinal($n) . ' sold on';
                $date = $this->allotDate('sold_on_' . $n, $label);
                if ($date < $prev) {
                    $this->fail($label . ' cannot be before ' . ($n === 1 ? 'the date of allotment' : 'the ' . self::ordinal($n - 1) . ' sale') . '.', 'sold_on_' . $n);
                }
                $prev = $date;
                $sales[] = ['sale_no' => $n, 'sold_on' => $date, 'transferee_name' => $this->allotPerson('transferee_' . $n, 'Name of transferee (' . self::ordinal($n) . ' sale)')];
            }
            $row['is_lease_deed_issued'] = $this->allotYesNo('is_lease_deed_issued', 'Select Yes or No for “Lease deed issued”.');
            if ($row['is_lease_deed_issued']) {
                $row['otlc_ref_no'] = $this->validate(['otlc_ref_no' => ['OTLC reference number', 'required|max:50|text']])['otlc_ref_no'];
                $row['otlc_issue_date'] = $this->allotDate('otlc_issue_date', 'OTLC issued date');
                $row['otlc_amount'] = $this->allotAmount('otlc_amount', 'OTLC amount');
                $f = $this->request->file('otlc_document');
                if (!$f && !PropertyService::latestAttachment($propertyId, AttachmentContext::OTLC)) {
                    $this->fail('Upload the OTLC document (PDF, max 5 MB).', 'otlc_document');
                }
                if ($f) {
                    $otlc = Uploads::checkPdf($f, 'OTLC document');
                    if (is_string($otlc)) {
                        $this->fail($otlc, 'otlc_document');
                    }
                }
            } else {
                $row['lease_amount'] = $this->allotAmount('lease_amount', 'Amount');
                $row['lease_amount_date'] = $this->allotDate('lease_amount_date', 'Date');
            }
        }
        return ['property_id' => $propertyId, 'old' => $old, 'row' => $row, 'sales' => $sales, 'otlc' => $otlc];
    }

    /** Save what allotmentInput() read (call inside the transaction). The stored OTLC file's path is added to $stored. */
    private static function storeAllotment(array $in, int $by, array &$stored, ?int $taskId = null): void
    {
        $id = $in['property_id'];
        if ($in['old']) {
            PropertyAllotment::update($in['old']['allotment_id'], $in['row'] + ['updated_by' => $by]);
        } else {
            PropertyAllotment::insert($in['row'] + ['property_id' => $id, 'created_by' => $by]);
        }
        PropertyTransfer::deleteWhere(['property_id' => $id]);
        foreach ($in['sales'] as $s) {
            PropertyTransfer::insert($s + ['property_id' => $id, 'created_by' => $by]);
        }
        if ($in['otlc']) {
            $stored[] = Uploads::store($in['otlc'], ['property_id' => $id, 'task_id' => $taskId, 'context' => AttachmentContext::OTLC, 'by' => $by])['path'];
        }
        Audit::log('PROPERTY', $id, 'ALLOTMENT_SAVED', $in['old'] ? array_intersect_key($in['old'], $in['row']) : null,
            $in['row'] + ['sales' => $in['sales']] + ($in['otlc'] ? ['otlc_document' => $in['otlc']['name']] : []), $id);
    }

    /** "1st", "2nd", "3rd", "4th" … */
    private static function ordinal(int $n): string
    {
        return $n . (in_array($n % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
    }

    private function allotYesNo(string $field, string $message): int
    {
        $v = $this->request->str($field);
        if (!in_array($v, YesNo::ALL, true)) {
            $this->fail($message, $field);
        }
        return $v === YesNo::YES ? 1 : 0;
    }

    /** Name of a person: letters, spaces and . ' ( ) / - */
    private function allotPerson(string $field, string $label): string
    {
        $_POST[$field] = preg_replace('/\s+/u', ' ', $this->request->str($field));
        return $this->validate([$field => [$label, 'required|min:3|max:100|name']])[$field];
    }

    /** Rupees: greater than zero, at most 2 decimals. */
    private function allotAmount(string $field, string $label): float
    {
        $_POST[$field] = str_replace(',', '', $this->request->str($field));
        return round((float) $this->validate([$field => [$label, 'required|decimal:2|positive']])[$field], 2);
    }

    /** dd/mm/yyyy, not in the future, not before 1950 → Y-m-d. */
    private function allotDate(string $field, string $label): string
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
