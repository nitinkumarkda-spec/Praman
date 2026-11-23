<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Property;
use App\Services\Uploads;

/**
 * Property Register → Allotment: correct the allotment details outside a verification round
 * (the Sale section fills them in "Update Property Status" — TaskController). Fields: AllotmentFields.
 */
final class AllotmentController extends Controller
{
    use AllotmentFields;

    /** Most sales one property may record. */
    public const MAX_SALES = 50;
    /** Where the Property screens live; a save returns to the one it came from. */
    private const LISTS = ['/data-entry/properties', '/admin/masters/properties', '/oic/properties'];

    public function save(): void
    {
        $id = $this->id('property_id');
        $prop = $id ? Property::find($id) : null;
        if (!$prop || !$prop['is_active']) {
            $this->fail('Property not found.');
        }
        $back = in_array($this->request->str('back'), self::LISTS, true) ? $this->request->str('back') : self::LISTS[1];
        $in = $this->allotmentInput($id);
        $by = (int) Auth::id();
        $stored = [];
        try {
            Database::transaction(static function () use ($in, $by, &$stored): void {
                self::storeAllotment($in, $by, $stored);
            });
        } catch (\Throwable $e) {
            Uploads::discard($stored);
            throw $e;
        }
        $this->done('Allotment details of plot ' . $prop['plot_no'] . ' saved.', $back . '/' . $id);
    }
}
