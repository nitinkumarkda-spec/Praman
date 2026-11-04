<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Audit;
use App\Services\Notify;

/** Activity & Performance Insights: publish a direction to property and verification officers. */
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
        Notify::send(Notify::permissionUsers('property.view', 'verification.view'), [
            'type' => 'SYSTEM', 'tone' => 'warning', 'icon' => 'fa-bullhorn',
            'title' => 'New direction from Administrator', 'message' => 'Focus areas: ' . implode(', ', $areas) . '.', 'link' => '/notifications',
        ]);
        $this->done('Direction published for: ' . implode(', ', $areas) . '.');
    }
}
