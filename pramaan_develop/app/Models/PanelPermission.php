<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Model;
use App\Core\Permissions;

/** One permission given to a panel (Super Admin → Permissions). */
final class PanelPermission extends Model
{
    protected static string $table = 'panel_permissions';
    protected static string $pk = 'grant_id';
    protected static array $columns = ['panel', 'permission', 'created_by'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;

    /** @return string[] */
    public static function codesFor(string $panel): array
    {
        return static::pluck('permission', ['panel' => $panel], 'permission');
    }

    /** Replace the codes of one panel. */
    public static function replace(string $panel, array $codes, ?int $by): void
    {
        Database::transaction(static function () use ($panel, $codes, $by): void {
            static::deleteWhere(['panel' => $panel]);
            foreach ($codes as $code) {
                static::insert(['panel' => $panel, 'permission' => $code, 'created_by' => $by]);
            }
        });
        Permissions::forget();
    }

    /** Settings row (internal, never shown) listing the codes this database already knows. */
    private const SEEN_KEY = 'permission_codes_seen';

    /**
     * First install: every panel gets every permission it can have.
     * Upgrade: a code added to Permissions::MODULES since the last run is given to its panels once;
     * codes Super Admin has already decided on are left as they are.
     */
    public static function seedDefaults(): void
    {
        $all = array_keys(Permissions::all());
        if (static::count() === 0) {
            $new = $all;
        } else {
            $row = Setting::find(self::SEEN_KEY);
            // databases from before this list existed: what the table holds is what was known
            $seen = $row ? (array) json_decode((string) $row['setting_value'], true) : array_unique(static::pluck('permission'));
            $new = array_values(array_diff($all, $seen));
        }
        foreach (Permissions::defaults() as $panel => $codes) {
            $add = array_values(array_intersect($codes, $new));
            if ($add) {
                static::replace($panel, array_values(array_unique(array_merge(static::codesFor($panel), $add))), null);
            }
        }
        Setting::upsert(['setting_key' => self::SEEN_KEY, 'setting_value' => json_encode($all), 'updated_at' => now()], ['setting_value', 'updated_at']);
    }
}
