<?php
declare(strict_types=1);

namespace App\Controllers\Oic;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\Verification;

/** OIC → send properties for verification (single, bulk, re-send). */
final class VerificationController extends Controller
{
    private const MAX_BULK = 100;

    public function send(): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->request->list('ids')))));
        $reason = $this->request->str('reason');
        if (!$ids) {
            $this->fail('Select at least one property to send for verification.');
        }
        if (count($ids) > self::MAX_BULK) {
            $this->fail('You can send at most ' . self::MAX_BULK . ' properties at once.');
        }
        if (mb_strlen($reason) > 255 || preg_match('/[<>]/', $reason)) {
            $this->fail('Reason may be at most 255 characters and may not contain < or >.', 'reason');
        }
        if ($reason !== '' && mb_strlen($reason) < 5) {
            $this->fail('Re-verification reason must be at least 5 characters.', 'reason');
        }
        $ok = [];
        $failed = [];
        foreach ($ids as $id) {
            $res = Verification::send($id, (int) Auth::id(), $reason !== '' ? $reason : null);
            if ($res['ok']) {
                $ok[] = $res['message'];
            } else {
                $failed[] = $res['message'];
            }
        }
        if ($ok) {
            $this->flash('success', count($ok) === 1 ? $ok[0] : count($ok) . ' properties sent for verification.');
        }
        if ($failed) {
            $this->flash('error', count($failed) . ' not sent: ' . implode(' | ', array_slice($failed, 0, 10)));
        }
        $this->done();
    }
}
