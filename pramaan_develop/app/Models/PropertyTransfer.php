<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** One sale of an allotted property: sale_no (1st, 2nd …), date and the transferee. */
final class PropertyTransfer extends Model
{
    protected static string $table = 'property_transfers';
    protected static string $pk = 'transfer_id';
    protected static array $columns = ['property_id', 'sale_no', 'sold_on', 'transferee_name', 'created_by'];
    protected static array $ints = ['sale_no'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;
}
