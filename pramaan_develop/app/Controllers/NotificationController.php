<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Notification;

/** Bell + notifications page: read / unread / delete — always only the user's own rows. */
final class NotificationController extends Controller
{
    public function read(): void
    {
        $read = $this->request->input('read', '1') === '1';
        Notification::updateWhere(['is_read' => $read ? 1 : 0, 'read_at' => $read ? now() : null], $this->mine(['notification_id' => $this->id()]));
        $this->done();
    }

    public function readAll(): void
    {
        Notification::updateWhere(['is_read' => 1, 'read_at' => now()], $this->mine(['is_read' => 0]));
        $this->done();
    }

    public function delete(): void
    {
        // soft delete: the row stays so the same reminder (ref) is not created again
        Notification::updateWhere(['deleted_at' => now()], $this->mine(['notification_id' => $this->id()]));
        $this->done();
    }

    public function deleteRead(): void
    {
        $n = Notification::updateWhere(['deleted_at' => now()], $this->mine(['is_read' => 1]));
        $this->done($n . ' read notification(s) deleted.');
    }

    /** Only the signed-in user's own, not deleted rows. */
    private function mine(array $where): array
    {
        return $where + ['user_id' => Auth::id(), 'deleted_at' => null];
    }
}
