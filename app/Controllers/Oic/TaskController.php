<?php
declare(strict_types=1);

namespace App\Controllers\Oic;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Verification;

/**
 * ⚠ TEMPORARY — "Simulate response" for testing the round logic before the Role User panel exists.
 * Remove this controller + its route (app/routes.php) and set verification.simulate_enabled = false
 * when role users respond from their own panel. The real panel will call Verification::respond().
 */
final class TaskController extends Controller
{
    public function simulate(): void
    {
        if (Config::get('verification.simulate_enabled') !== true) {
            throw new HttpException(404, 'Simulation is disabled.');
        }
        $taskId = $this->idParam();
        $decision = (string) $this->request->input('decision', '');
        $remarks = $this->request->input('remarks');
        if ($remarks !== null && (mb_strlen($remarks) > 1000 || preg_match('/[<>]/', $remarks) === 1)) {
            $this->flash('error', 'Remark may be at most 1000 characters and may not contain < or >.');
            $this->back('/oic/properties');
        }

        $res = (new Verification())->respond($taskId, $decision, $remarks, (int) Auth::id(), true);
        $this->flash($res['ok'] ? 'success' : 'error', ($res['ok'] ? '[Simulated] ' : '') . $res['message']);
        $this->back('/oic/properties');
    }
}
