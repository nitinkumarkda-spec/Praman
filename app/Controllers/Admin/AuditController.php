<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Paginator;
use App\Models\AuditLog;

final class AuditController extends Controller
{
    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, AuditLog::SORTABLE, 'id', 'desc', ['entity', 'from', 'to']);
        $model = new AuditLog();
        $model->paginateList($p);
        $this->view('admin/audit/index', [
            'pageTitle' => 'Audit Log',
            'p'         => $p,
            'entities'  => $model->entityTypes(),
        ]);
    }
}
