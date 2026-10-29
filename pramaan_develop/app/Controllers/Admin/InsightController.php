<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Audit;
use App\Services\Notify;

/** Super Admin → Activity & Performance Insights: publish a direction to Data Entry + OIC. */
final class InsightController extends Controller
{
    public function publishDirection(): void
    {
        $areas = array_values(array_filter(array_map('trim', explode('|', $this->request->str('areas'))), 'strlen'));
        $areas = array_slice(array_map(static fn ($a) => mb_substr(strip_tags($a), 0, 60), $areas), 0, 10);
        if (!$areas) {
            $this->fail('No focus area selected.');
        }
        Audit::log('SYSTEM', null, 'DIRECTION_PUBLISHED', null, ['areas' => $areas]);
        Notify::send(array_merge(Notify::panelUsers('DATA_ENTRY'), Notify::panelUsers('OIC')), [
            'type' => 'SYSTEM', 'tone' => 'warning', 'icon' => 'fa-bullhorn',
            'title' => 'New direction from Super Admin', 'message' => 'Focus areas: ' . implode(', ', $areas) . '.', 'link' => '/notifications',
        ]);
        $this->done('Direction published for: ' . implode(', ', $areas) . '.');
    }
}
