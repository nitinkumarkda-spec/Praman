<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Models\Attachment;
use App\Models\TaskAssignee;
use App\Services\Uploads;

/** Streams an attachment to a user who may see its property. */
final class AttachmentController extends Controller
{
    public function show(): void
    {
        $a = Attachment::find($this->request->params['id'] ?? 0);
        if (!$a || !$this->canView($a)) {
            throw new HttpException(404, 'Attachment not found.');
        }
        $path = Uploads::path($a);
        if (!$path) {
            throw new HttpException(404, 'The file is missing on the server.');
        }
        $name = str_replace(['"', "\r", "\n"], '', $a['file_name']);
        header('Content-Type: ' . $a['mime']);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($a['file_name']));
        header('Cache-Control: private, no-store');
        readfile($path);
        exit;
    }

    private function canView(array $a): bool
    {
        $u = Auth::user();
        if (!$u) {
            return false;
        }
        if ($u['panel'] !== 'ROLE_USER') {
            return true;
        }
        // role users: only properties they have (had) a task on
        return TaskAssignee::userOnProperty((int) $u['user_id'], (int) $a['property_id']);
    }
}
