<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Allotment details of a property (one row per property); its sales are PropertyTransfer rows, the OTLC PDF an attachment (context OTLC). */
final class PropertyAllotment extends Model
{
    protected static string $table = 'property_allotments';
    protected static string $pk = 'allotment_id';
    protected static array $columns = ['property_id', 'is_allottee', 'allotment_date', 'allottee_name', 'possession_date', 'annual_lease_rate', 'lease_unit_id',
        'allotment_price', 'times_sold', 'is_lease_deed_issued', 'otlc_ref_no', 'otlc_issue_date', 'otlc_amount', 'lease_amount', 'lease_amount_date',
        'created_by', 'updated_by'];
    protected static array $ints = ['times_sold'];
    protected static array $floats = ['annual_lease_rate', 'allotment_price', 'otlc_amount', 'lease_amount'];
}
