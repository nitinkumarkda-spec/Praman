<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Landing page slider photo (file in public/assets/img/slides). */
final class Slide extends Model
{
    protected static string $table = 'slides';
    protected static string $pk = 'slide_id';
    protected static array $columns = ['image', 'title', 'credit', 'sort_order', 'is_active', 'created_by', 'updated_by'];
}
