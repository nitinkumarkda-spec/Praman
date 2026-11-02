<?php
declare(strict_types=1);

namespace App\Codes;

/** Site inspection lists — site_inspections.variance / encroachment_type / road_width_unit. */
final class SiteCode
{
    public const SAME = 'SAME';
    public const LESS = 'LESS';
    public const MORE = 'MORE';

    public const PARTIAL = 'PARTIAL';
    public const COMPLETE = 'COMPLETE';
    public const ENCROACHMENT_TYPES = [self::PARTIAL => 'Partial encroachment', self::COMPLETE => 'Complete encroachment'];

    public const FEET = 'FT';
    public const METER = 'M';
    public const ROAD_UNITS = [self::FEET => 'Feet', self::METER => 'Meter'];
}
