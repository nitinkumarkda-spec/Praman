<?php
declare(strict_types=1);

namespace App\Controllers\Oic;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $dash = new Dashboard();
        $this->view('oic/dashboard', [
            'pageTitle'   => 'OIC Auction Dashboard',
            'counts'      => $dash->oicCounts(),
            'statusCount' => $dash->propertyStatusCounts(),
            'activity'    => $dash->recentActivity(8, (int) Auth::id()),
        ]);
    }
}
