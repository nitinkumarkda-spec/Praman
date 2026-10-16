<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Dashboard;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $dash = new Dashboard();
        $this->view('admin/dashboard', [
            'pageTitle'   => 'Super Admin Dashboard',
            'counts'      => $dash->adminCounts(),
            'byRole'      => $dash->usersByRole(),
            'statusCount' => $dash->propertyStatusCounts(),
            'activity'    => $dash->recentActivity(10),
        ]);
    }
}
