<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Area unit master (sq m, sq ft, sq yard …); to_sqm converts a value to square metres. The base unit (sq m) is the system row. */
final class Unit extends Model
{
    /** Default rows: name, Hindi name, symbol, to_sqm, description. The first one (sq m) is the base (system) unit. */
    public const DEFAULTS = [
        ['Square Meter', 'वर्ग मीटर', 'sq m', 1, 'Base unit — every area is also stored in sq m'],
        ['Square Feet', 'वर्ग फुट', 'sq ft', 0.09290304, '1 sq ft = 0.09290304 sq m'],
        ['Square Yard', 'वर्ग गज', 'sq yd', 0.83612736, '1 sq yd (gaj) = 0.83612736 sq m = 9 sq ft'],
    ];

    protected static string $table = 'units';
    protected static string $pk = 'unit_id';
    protected static array $columns = ['name', 'name_hi', 'symbol', 'to_sqm', 'description', 'sort_order', 'is_active', 'is_system', 'created_by', 'updated_by'];
    protected static array $floats = ['to_sqm'];

    /** @var array<int, float> */
    private static array $factors = [];

    /** Square metres in 1 unit (null for an unknown unit). */
    public static function factor(int $unitId): ?float
    {
        if (!isset(self::$factors[$unitId])) {
            $u = static::find($unitId);
            if (!$u) {
                return null;
            }
            self::$factors[$unitId] = (float) $u['to_sqm'];
        }
        return self::$factors[$unitId];
    }

    /** The base unit (sq m). */
    public static function baseId(): ?int
    {
        return static::first(['is_system' => 1], 'unit_id')['unit_id'] ?? null;
    }

    /** Fill the default units when the table is empty. */
    public static function seedDefaults(): void
    {
        if (static::count() > 0) {
            return;
        }
        foreach (self::DEFAULTS as $i => $u) {
            static::insert(['name' => $u[0], 'name_hi' => $u[1], 'symbol' => $u[2], 'to_sqm' => $u[3], 'description' => $u[4],
                'sort_order' => $i + 1, 'is_active' => 1, 'is_system' => $i === 0 ? 1 : 0, 'created_by' => null]);
        }
    }
}
