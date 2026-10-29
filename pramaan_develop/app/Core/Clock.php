<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Application clock. The seeder moves it into the past so sample history is
 * spread over earlier days; at runtime the offset is always 0.
 */
final class Clock
{
    private static int $shiftMinutes = 0;

    /** Pretend "now" is $minutesAgo minutes in the past (seeding only). */
    public static function travel(float $minutesAgo): void
    {
        self::$shiftMinutes = -(int) round($minutesAgo);
    }

    public static function reset(): void
    {
        self::$shiftMinutes = 0;
    }

    public static function timestamp(int $offsetMinutes = 0): int
    {
        return time() + (self::$shiftMinutes + $offsetMinutes) * 60;
    }

    public static function now(int $offsetMinutes = 0): string
    {
        return date('Y-m-d H:i:s', self::timestamp($offsetMinutes));
    }

    public static function today(int $offsetDays = 0): string
    {
        $base = self::timestamp();
        return date('Y-m-d', strtotime(($offsetDays >= 0 ? '+' : '') . $offsetDays . ' days', $base));
    }
}
