<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Area unit master (sq m, sq ft, sq yard …); to_sqm converts a value to square metres. */
final class Unit extends Model
{
    public const BASE = 'SQM';
    /** Default rows: code, name, Hindi name, symbol, to_sqm. SQM is the base (system) unit. */
    public const DEFAULTS = [
        ['SQM', 'Square Meter', 'वर्ग मीटर', 'sq m', 1, 'Base unit — every area is also stored in sq m'],
        ['SQFT', 'Square Feet', 'वर्ग फुट', 'sq ft', 0.09290304, '1 sq ft = 0.09290304 sq m'],
        ['SQYD', 'Square Yard', 'वर्ग गज', 'sq yd', 0.83612736, '1 sq yd (gaj) = 0.83612736 sq m = 9 sq ft'],
    ];

    protected static string $table = 'units';
    protected static string $pk = 'unit_id';
    protected static array $columns = ['code', 'name', 'name_hi', 'symbol', 'to_sqm', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
    protected static array $floats = ['to_sqm'];

    /** @var array<string, float> */
    private static array $factors = [];

    /** Square metres in 1 unit (null for an unknown code). */
    public static function factor(string $code): ?float
    {
        if (!isset(self::$factors[$code])) {
            $u = static::first(['code' => $code]);
            if (!$u) {
                return null;
            }
            self::$factors[$code] = (float) $u['to_sqm'];
        }
        return self::$factors[$code];
    }

    /** Fill the default units when the table is empty. */
    public static function seedDefaults(): void
    {
        if (static::count() > 0) {
            return;
        }
        foreach (self::DEFAULTS as $i => $u) {
            static::insert(['code' => $u[0], 'name' => $u[1], 'name_hi' => $u[2], 'symbol' => $u[3], 'to_sqm' => $u[4], 'description' => $u[5],
                'sort_order' => $i + 1, 'is_active' => 1, 'is_system' => $u[0] === self::BASE ? 1 : 0, 'created_by' => null]);
        }
    }
}
