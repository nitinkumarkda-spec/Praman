<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Services\Audit;

/** Screen-level access-denied logging. (Sample data is reloaded only from the command line: database/install.php.) */
final class SystemController extends Controller
{
    /** The UI blocked a screen of another panel: keep it in the audit trail. */
    public function accessDenied(): void
    {
        $path = mb_substr(preg_replace('~[^A-Za-z0-9/_?=&.-]~', '', $this->request->str('path')) ?? '', 0, 150);
        Audit::log('ROUTE', null, 'ACCESS_DENIED', null, ['path' => $path]);
        Response::json(['ok' => true]);
    }
}
