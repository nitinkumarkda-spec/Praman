<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** Uploaded file metadata (bytes live in storage/uploads). */
final class Attachment extends Model
{
    protected static string $table = 'attachments';
    protected static string $pk = 'attachment_id';
    protected static array $columns = ['task_id', 'round_id', 'property_id', 'context', 'file_name', 'stored_name', 'mime', 'size', 'sha256', 'uploaded_by'];
    protected static bool $timestamps = false;
    protected static bool $createdOnly = true;
}
