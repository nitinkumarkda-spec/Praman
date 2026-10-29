<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use Throwable;

/**
 * Daily jobs, run by the first signed-in request of each day:
 * Auction Ready past validity -> Validity Expired, and old login attempts pruned.
 */
final class Housekeeping
{
    public static function run(): void
    {
        $dir = BASE_PATH . '/storage/cache';
        $marker = $dir . '/housekeeping.txt';
        $today = today();
        if (is_file($marker) && trim((string) @file_get_contents($marker)) === $today) {
            return;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $fh = @fopen($marker, 'c+');
        if (!$fh || !flock($fh, LOCK_EX | LOCK_NB)) {
            return; // another request is doing it
        }
        try {
            if (trim((string) stream_get_contents($fh)) !== $today) {
                Verification::expireDue();
                \App\Models\LoginAttempt::deleteWhere(['attempted_at <' => now(-90 * 1440)]);
                // notifications the user deleted long ago (reminders are no longer due by then)
                \App\Models\Notification::deleteWhere(['deleted_at <' => now(-180 * 1440)]);
                ftruncate($fh, 0);
                rewind($fh);
                fwrite($fh, $today);
            }
        } catch (Throwable $e) {
            Logger::exception($e);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }
}
