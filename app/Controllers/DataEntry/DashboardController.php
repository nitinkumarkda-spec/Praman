<?php
declare(strict_types=1);

namespace App\Controllers\DataEntry;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $dash = new Dashboard();
        $uid = (int) Auth::id();
        $this->view('dataentry/dashboard', [
            'pageTitle'   => 'Data Entry Dashboard',
            'counts'      => $dash->dataEntryCounts($uid),
            'statusCount' => $dash->propertyStatusCounts($uid),
            'activity'    => $dash->recentActivity(8, $uid),
        ]);
    }
}
