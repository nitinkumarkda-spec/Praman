<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Models\Assembly;
use App\Models\PlotType;
use App\Models\Property;
use App\Models\Scheme;
use App\Models\Sector;
use App\Models\Status;
use App\Models\Ward;
use App\Models\Zone;
use App\Services\Audit;

/**
 * Super Admin → simple masters (Zone, Ward, Assembly, Plot Type, Status) and the Sector Master.
 * Same rules as the screens in public/assets/js/pages/masters.js.
 */
final class MasterController extends Controller
{

    /** key => [model, entity, one, name(), fields: name => [label, rules, unique, upper, lockOnEdit, number]] */
    private function masters(): array
    {
        return [
            'zones' => [Zone::class, 'ZONE', 'Zone', static fn ($r) => $r['zone_name'], [
                'zone_code'  => ['Zone code', 'required|ucode', true, true, true, false],
                'zone_name'  => ['Zone name', 'required|min:2|max:60|text', true, false, false, false],
                'name_hi'    => ['Name (Hindi)', 'max:60|text', false, false, false, false],
                'area'       => ['Area / description', 'max:150|text', false, false, false, false],
                'sort_order' => ['Display order', 'required|integer|between:0,999', false, false, false, true],
            ]],
            'wards' => [Ward::class, 'WARD', 'Ward', static fn ($r) => 'Ward ' . $r['ward_no'] . ' — ' . $r['ward_name'], [
                'ward_no'    => ['Ward no.', 'required|integer|between:1,999', true, false, false, true],
                'ward_name'  => ['Ward name / locality', 'required|min:2|max:80|text', false, false, false, false],
                'zone_id'    => ['Zone', 'required|integer', false, false, false, true],
                'councillor' => ['Councillor (optional)', 'max:80|text', false, false, false, false],
            ]],
            'assemblies' => [Assembly::class, 'ASSEMBLY', 'Assembly constituency', static fn ($r) => $r['ac_no'] . ' — ' . $r['ac_name'], [
                'ac_no'    => ['AC no.', 'required|integer|between:1,300', true, false, false, true],
                'ac_name'  => ['Constituency name', 'required|min:2|max:80|text', true, false, false, false],
                'name_hi'  => ['Name (Hindi)', 'max:80|text', false, false, false, false],
                'district' => ['District', 'required|min:2|max:60|text', false, false, false, false],
                'mla_name' => ['MLA (optional)', 'max:80|text', false, false, false, false],
            ]],
            'plot-types' => [PlotType::class, 'PLOT_TYPE', 'Plot type', static fn ($r) => $r['name'], [
                'code'        => ['Code', 'required|code', true, true, true, false],
                'name'        => ['Name', 'required|min:2|max:60|text', true, false, false, false],
                'name_hi'     => ['Name (Hindi)', 'max:60|text', false, false, false, false],
                'description' => ['Description', 'max:150|text', false, false, false, false],
                'sort_order'  => ['Display order', 'required|integer|between:0,999', false, false, false, true],
            ]],
            'statuses' => [Status::class, 'STATUS', 'Property status', static fn ($r) => $r['name'], [
                'code'        => ['Status code', 'required|code', true, true, true, false],
                'name'        => ['Display name', 'required|min:2|max:40|text', true, false, false, false],
                'name_hi'     => ['Name (Hindi)', 'max:40|text', false, false, false, false],
                'color'       => ['Badge colour', 'required|in:' . \App\Core\Lookups::keys(\App\Core\Lookups::STATUS_COLORS), false, false, false, false],
                'icon'        => ['Icon', 'required|in:' . \App\Core\Lookups::keys(\App\Core\Lookups::STATUS_ICONS), false, false, false, false],
                'description' => ['Meaning', 'max:150|text', false, false, false, false],
                'sort_order'  => ['Display order', 'required|integer|between:0,999', false, false, false, true],
            ]],
        ];
    }

    private function master(): array
    {
        $key = $this->request->str('key');
        $m = $this->masters()[$key] ?? null;
        if (!$m) {
            $this->fail('Unknown master.');
        }
        return [$key, ...$m];
    }

    public function save(): void
    {
        [$key, $model, $entity, $one, $name, $fields] = $this->master();
        $id = $this->id() ?: null;
        $old = $id ? $model::find($id) : null;
        if ($id && !$old) {
            $this->fail($one . ' not found.');
        }
        $locked = $old && $key === 'statuses' && $old['is_system'];
        $rules = [];
        foreach ($fields as $f => [$label, $rule, , $upper, $lockOnEdit]) {
            if ($id && $lockOnEdit) {
                continue;
            }
            if ($upper) {
                $_POST[$f] = strtoupper($this->request->str($f));
            }
            $rules[$f] = [$label, $rule];
        }
        $d = $this->validate($rules);
        $data = [];
        foreach ($rules as $f => $_) {
            [$label, , $unique, , , $number] = $fields[$f];
            $v = $d[$f];
            $data[$f] = $v === '' ? null : ($number ? (int) $v : $v);
            if ($unique && $v !== '' && $model::valueExists($f, (string) $v, $id)) {
                $this->fail($label . ' "' . $v . '" already exists.', $f);
            }
        }
        if ($key === 'wards') {
            $z = Zone::find($data['zone_id']);
            if (!$z || (!$z['is_active'] && (int) ($old['zone_id'] ?? 0) !== $data['zone_id'])) {
                $this->fail('Please select an active zone.', 'zone_id');
            }
        }
        $data['is_active'] = $locked ? 1 : ($this->request->input('is_active') === '1' ? 1 : 0);
        if ($id && $old['is_active'] && !$data['is_active'] && ($block = $this->blockDeactivate($key, $old))) {
            $this->fail($block);
        }
        if ($id) {
            $model::update($id, $data + ['updated_by' => Auth::id()]);
            Audit::log($entity, $id, 'UPDATED', array_intersect_key($old, $data), $data);
            $this->done($one . ' "' . $name($model::find($id)) . '" updated successfully.', '/admin/masters/' . $key);
        }
        $newId = $model::insert($data + ['created_by' => Auth::id(), 'is_system' => 0]);
        Audit::log($entity, $newId, 'CREATED', null, $data);
        $this->done($one . ' "' . $name($model::find($newId)) . '" created successfully.', '/admin/masters/' . $key);
    }

    public function toggle(): void
    {
        [$key, $model, $entity, $one, $name] = $this->master();
        $r = $model::find($this->id());
        if (!$r) {
            $this->fail('Record not found.');
        }
        $active = $r['is_active'] ? 0 : 1;
        if (!$active && ($block = $this->blockDeactivate($key, $r))) {
            $this->fail($block);
        }
        $model::update($r[$model::pk()], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log($entity, $r[$model::pk()], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done($one . ' "' . $name($r) . '" ' . ($active ? 'activated.' : 'deactivated. It is hidden from new entries.'));
    }

    public function delete(): void
    {
        [$key, $model, $entity, $one, $name, $fields] = $this->master();
        $r = $model::find($this->id());
        if (!$r) {
            $this->fail('Record not found.');
        }
        if (($key === 'statuses' && $r['is_system']) || $this->usage($key, $r) > 0) {
            $this->fail('This ' . strtolower($one) . ' is in use and cannot be deleted. Deactivate it instead.');
        }
        $model::delete($r[$model::pk()]);
        Audit::log($entity, $r[$model::pk()], 'DELETED', array_intersect_key($r, $fields), null);
        $this->done($one . ' "' . $name($r) . '" deleted.');
    }

    /** How many records use this master row (delete is allowed only at 0). */
    private function usage(string $key, array $r): int
    {
        return match ($key) {
            'zones'      => Scheme::count(['zone_id' => $r['zone_id']]) + Ward::count(['zone_id' => $r['zone_id']]),
            'wards'      => Property::count(['ward_id' => $r['ward_id']]),
            'assemblies' => Property::count(['assembly_id' => $r['assembly_id']]),
            'plot-types' => Property::count(['property_type' => $r['code']]),
            'statuses'   => Property::count(['current_status' => $r['code']]),
            default      => 1,
        };
    }

    private function blockDeactivate(string $key, array $r): ?string
    {
        if ($key === 'zones' && ($n = Scheme::count(['zone_id' => $r['zone_id'], 'is_active' => 1]))) {
            return 'Zone "' . $r['zone_name'] . '" is used by ' . $n . ' active scheme(s). Move or deactivate those schemes first.';
        }
        if ($key === 'plot-types' && PlotType::count(['is_active' => 1]) <= 1) {
            return 'At least one plot type must remain active.';
        }
        if ($key === 'assemblies' && Assembly::count(['is_active' => 1]) <= 1) {
            return 'At least one assembly constituency must remain active.';
        }
        if ($key === 'statuses' && $r['is_system']) {
            return 'System statuses cannot be deactivated.';
        }
        return null;
    }

    /* ================= Sector Master ================= */

    public function sectorSave(): void
    {
        $id = $this->id() ?: null;
        if ($id) {
            $r = Sector::find($id);
            if (!$r) {
                $this->fail('Sector not found.');
            }
            $d = $this->validate([
                'sector_name' => ['Sector name', 'required|max:80|text'],
                'sort_order'  => ['Display order', 'required|integer|between:0,999'],
            ]);
            if (Sector::valueExists('sector_name', $d['sector_name'], $id, ['scheme_id' => $r['scheme_id']])) {
                $this->fail('Sector "' . $d['sector_name'] . '" already exists in this scheme.', 'sector_name');
            }
            $active = $this->request->input('is_active') === '1' ? 1 : 0;
            if (!$active && $this->lastActiveSector($r)) {
                $this->fail('This is the last active sector of the scheme. A scheme needs at least one active sector.');
            }
            $new = ['sector_name' => $d['sector_name'], 'sort_order' => (int) $d['sort_order'], 'is_active' => $active];
            Sector::update($id, $new + ['updated_by' => Auth::id()]);
            Audit::log('SECTOR', $id, 'UPDATED', ['sector_name' => $r['sector_name'], 'sort_order' => $r['sort_order'], 'is_active' => $r['is_active']], $new);
            $this->done('Sector "' . $d['sector_name'] . '" updated.', '/admin/masters/sectors');
        }
        $d = $this->validate(['scheme_id' => ['Scheme', 'required|integer'], 'sector_name' => ['Sector name(s)', 'required|max:400|text']]);
        $scheme = Scheme::find((int) $d['scheme_id']);
        if (!$scheme) {
            $this->fail('Please select a scheme.', 'scheme_id');
        }
        $names = array_values(array_filter(array_map('trim', explode(',', $d['sector_name'])), 'strlen'));
        $seen = [];
        foreach ($names as $n) {
            if (mb_strlen($n) > 80) {
                $this->fail('Sector "' . mb_substr($n, 0, 30) . '…" is longer than 80 characters.', 'sector_name');
            }
            $k = mb_strtolower($n);
            if (isset($seen[$k]) || Sector::valueExists('sector_name', $n, null, ['scheme_id' => $scheme['scheme_id']])) {
                $this->fail('Sector "' . $n . '" already exists in this scheme.', 'sector_name');
            }
            $seen[$k] = true;
        }
        Database::transaction(function () use ($names, $scheme): void {
            $order = Sector::max('sort_order', ['scheme_id' => $scheme['scheme_id']]);
            foreach ($names as $n) {
                $sid = Sector::insert(['scheme_id' => $scheme['scheme_id'], 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => Auth::id()]);
                Audit::log('SECTOR', $sid, 'CREATED', null, ['scheme' => $scheme['scheme_name'], 'sector_name' => $n]);
            }
        });
        $this->done(count($names) . ' sector(s) added to ' . $scheme['scheme_name'] . '.', '/admin/masters/sectors');
    }

    public function sectorToggle(): void
    {
        $r = Sector::find($this->id());
        if (!$r) {
            $this->fail('Sector not found.');
        }
        $active = $r['is_active'] ? 0 : 1;
        if (!$active && $this->lastActiveSector($r)) {
            $this->fail('This is the last active sector of the scheme. A scheme needs at least one active sector.');
        }
        Sector::update($r['sector_id'], ['is_active' => $active, 'updated_by' => Auth::id()]);
        Audit::log('SECTOR', $r['sector_id'], $active ? 'ACTIVATED' : 'DEACTIVATED', ['is_active' => 1 - $active], ['is_active' => $active]);
        $this->done('Sector "' . $r['sector_name'] . '" ' . ($active ? 'activated.' : 'deactivated.'));
    }

    public function sectorDelete(): void
    {
        $r = Sector::find($this->id());
        if (!$r) {
            $this->fail('Sector not found.');
        }
        if (Property::count(['sector_id' => $r['sector_id']])) {
            $this->fail('Sector has properties and cannot be deleted. Deactivate it instead.');
        }
        if ($this->lastActiveSector($r)) {
            $this->fail('This is the last active sector of the scheme and cannot be deleted.');
        }
        Sector::delete($r['sector_id']);
        Audit::log('SECTOR', $r['sector_id'], 'DELETED', ['sector_name' => $r['sector_name']], null);
        $this->done('Sector "' . $r['sector_name'] . '" deleted.');
    }

    private function lastActiveSector(array $r): bool
    {
        return $r['is_active'] && Sector::count(['scheme_id' => $r['scheme_id'], 'is_active' => 1]) <= 1;
    }
}
