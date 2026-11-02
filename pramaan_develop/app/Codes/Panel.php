<?php
declare(strict_types=1);

namespace App\Codes;

/** Login panels — users.panel / panel_permissions.panel. */
final class Panel
{
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const DATA_ENTRY = 'DATA_ENTRY';
    public const OIC = 'OIC';
    public const ROLE_USER = 'ROLE_USER';

    public const LABELS = [self::SUPER_ADMIN => 'Super Admin', self::DATA_ENTRY => 'Data Entry', self::OIC => 'OIC Auction', self::ROLE_USER => 'Role User'];

    /** First screen after login. */
    public const HOME = [self::SUPER_ADMIN => '/admin/dashboard', self::DATA_ENTRY => '/data-entry/dashboard', self::OIC => '/oic/dashboard', self::ROLE_USER => '/role/dashboard'];
}
