<?php
declare(strict_types=1);

namespace App\Controllers\RoleUser;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Dashboard;
use App\Models\Task;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $uid = (int) Auth::id();
        $task = new Task();
        $this->view('roleuser/dashboard', [
            'pageTitle' => 'My Verification Dashboard',
            'counts'    => $task->countsForUser($uid),
            'urgent'    => $task->urgentForUser($uid, 6),
            'activity'  => (new Dashboard())->recentActivity(8, $uid),
        ]);
    }
}
