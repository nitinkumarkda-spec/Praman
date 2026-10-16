<?php
declare(strict_types=1);

namespace App\Controllers\Oic;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Session;
use App\Models\Property;
use App\Models\Scheme;
use App\Models\Verification;

/**
 * OIC Auction: property list, send for verification (single / bulk), verification status.
 */
final class PropertyController extends Controller
{
    private const MAX_BULK = 200;

    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, Property::SORTABLE, 'id', 'desc', ['scheme_id', 'sector_id', 'status', 'type']);
        (new Property())->paginateList($p);
        $scheme = new Scheme();
        $schemeId = $p->filter('scheme_id');
        $this->view('oic/properties/index', [
            'pageTitle' => 'Properties – Verification',
            'p'         => $p,
            'schemes'   => $scheme->options(),
            'sectors'   => ($schemeId !== null && ctype_digit($schemeId)) ? $scheme->sectorOptions((int) $schemeId) : [],
        ]);
    }

    public function show(): void
    {
        $property = (new Property())->find($this->idParam());
        if ($property === null) {
            throw new HttpException(404, 'Property not found.');
        }
        $v = new Verification();
        $rounds = $v->rounds((int) $property['property_id']);
        foreach ($rounds as &$round) {
            $round['tasks'] = $v->tasks((int) $round['round_id']);
        }
        unset($round);
        $this->view('oic/properties/show', [
            'pageTitle'       => 'Verification Status',
            'property'        => $property,
            'roleUsers'       => (new Property())->roleUsers((int) $property['property_id']),
            'rounds'          => $rounds,
            'simulateEnabled' => Config::get('verification.simulate_enabled') === true,
        ]);
    }

    /** POST ids[] (bulk) — single "Send" buttons post one id. */
    public function send(): void
    {
        $ids = [];
        foreach ($this->request->inputArray('ids') as $id) {
            if (is_string($id) && ctype_digit($id) && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }
        $reason = $this->request->input('reason');
        if ($reason !== null && (mb_strlen($reason) > 255 || preg_match('/[<>]/', $reason) === 1)) {
            $this->flash('error', 'Reason may be at most 255 characters and may not contain < or >.');
            $this->back('/oic/properties');
        }
        if ($ids === []) {
            $this->flash('warning', 'Select at least one property to send for verification.');
            $this->back('/oic/properties');
        }
        if (count($ids) > self::MAX_BULK) {
            $this->flash('error', 'You can send at most ' . self::MAX_BULK . ' properties at once.');
            $this->back('/oic/properties');
        }

        $engine = new Verification();
        $ok = [];
        $failed = [];
        foreach ($ids as $id) {
            $res = $engine->send($id, (int) Auth::id(), $reason !== '' ? $reason : null);
            if ($res['ok']) {
                $ok[] = $res['message'];
            } else {
                $failed[] = $res['message'];
            }
        }
        if ($ok !== []) {
            $this->flash('success', count($ok) === 1 ? $ok[0] : count($ok) . ' properties sent for verification.');
        }
        if ($failed !== []) {
            Session::flash('error', count($failed) . ' not sent: ' . implode(' | ', array_slice($failed, 0, 10)) . (count($failed) > 10 ? ' …' : ''));
        }
        $this->back('/oic/properties');
    }
}
