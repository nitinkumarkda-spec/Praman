<?php
declare(strict_types=1);

namespace App\Controllers\RoleUser;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Validator;
use App\Models\Scheme;
use App\Models\Task;
use App\Models\Verification;

/**
 * Role User: my verification tasks → open → Approve / Reject (remark mandatory on reject).
 */
final class TaskController extends Controller
{
    public function index(): void
    {
        $p = Paginator::fromRequest($this->request, Task::SORTABLE, 'due', 'asc', ['status', 'scheme_id']);
        (new Task())->paginateForUser($p, (int) Auth::id());
        $this->view('roleuser/tasks/index', [
            'pageTitle' => 'My Tasks',
            'p'         => $p,
            'schemes'   => (new Scheme())->options(),
        ]);
    }

    public function show(): void
    {
        $task = $this->findOr404();
        $this->view('roleuser/tasks/show', [
            'pageTitle' => 'Verify Property',
            'task'      => $task,
            'history'   => (new Task())->previousResponses((int) $task['property_id'], (int) $task['role_id'], (int) $task['round_no']),
        ]);
    }

    public function respond(): void
    {
        $task = $this->findOr404();
        $back = '/role/tasks/' . $task['task_id'];

        $v = Validator::make($this->request->all(), [
            'decision' => 'required|in:APPROVED,REJECTED',
            'remarks'  => 'nullable|max:1000|text',
        ], ['decision' => 'Decision (Approve / Reject)', 'remarks' => 'Remark']);
        $decision = (string) $this->request->input('decision', '');
        $remarks = (string) $this->request->input('remarks', '');
        if ($decision === 'REJECTED' && mb_strlen($remarks) < 5) {
            $v->addError('remarks', 'Rejection reason is mandatory (at least 5 characters).');
        }
        if ($v->fails()) {
            $this->failValidation($v->errors(), $back);
        }

        $res = (new Verification())->respond((int) $task['task_id'], $decision, $remarks !== '' ? $remarks : null, (int) Auth::id(), false);
        $this->flash($res['ok'] ? 'success' : 'error', $res['message']);
        $this->redirect($res['ok'] ? '/role/tasks?status=pending' : $back);
    }

    /** 404 also when the task belongs to someone else — never reveal other users' tasks. */
    private function findOr404(): array
    {
        $task = (new Task())->findForUser($this->idParam(), (int) Auth::id());
        if ($task === null) {
            throw new HttpException(404, 'Task not found or not assigned to you.');
        }
        // Answered by another user of the same section → it is no longer this user's task.
        if ($task['task_status'] !== 'PENDING' && (int) $task['responded_by'] !== Auth::id()) {
            $this->flash('info', "Plot {$task['plot_no']} ({$task['scheme_name']}) was already "
                . strtolower(label($task['task_status'])) . " by {$task['responded_by_name']} on "
                . fmt_datetime($task['responded_at']) . ' — it has been removed from your tasks.');
            $this->redirect('/role/tasks?status=pending');
        }
        return $task;
    }
}
