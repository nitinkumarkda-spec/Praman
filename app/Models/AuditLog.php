<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

/**
 * Read-only access to the insert-only audit trail.
 */
final class AuditLog extends Model
{
    public const SORTABLE = [
        'id'     => 'a.log_id',
        'time'   => 'a.performed_at',
        'action' => 'a.action',
        'entity' => 'a.entity_type',
        'user'   => 'a.performed_by_name',
    ];

    public function paginateList(Paginator $p): Paginator
    {
        $from = ' FROM {audit_logs} a WHERE 1 = 1';
        $params = [];
        if ($p->search !== '') {
            $from .= ' AND (a.action LIKE :q1 OR a.performed_by_name LIKE :q2 OR a.ip_address LIKE :q3 OR a.new_value LIKE :q4)';
            $like = self::like($p->search);
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }
        $entity = $p->filter('entity');
        if ($entity !== null && preg_match('/^[A-Z_]{2,40}$/', $entity) === 1) {
            $from .= ' AND a.entity_type = :entity';
            $params['entity'] = $entity;
        }
        $from_date = $p->filter('from');
        if ($from_date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) === 1) {
            $from .= ' AND a.performed_at >= :fd';
            $params['fd'] = $from_date . ' 00:00:00';
        }
        $to_date = $p->filter('to');
        if ($to_date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date) === 1) {
            $from .= ' AND a.performed_at <= :td';
            $params['td'] = $to_date . ' 23:59:59';
        }
        return $this->paginate($p, 'SELECT a.*', $from, $params);
    }

    public function entityTypes(): array
    {
        return array_column($this->fetchAll('SELECT DISTINCT entity_type FROM {audit_logs} ORDER BY entity_type'), 'entity_type');
    }
}
