<?php
declare(strict_types=1);

namespace App\Codes;

/** One user's exception to the role's permissions — user_permissions.effect. */
final class PermissionEffect
{
    public const ALLOW = 'ALLOW';
    public const DENY = 'DENY';
    /** Form value meaning "no exception: same as the role" (never stored). */
    public const INHERIT = 'inherit';

    public const ALL = [self::ALLOW, self::DENY];
}
