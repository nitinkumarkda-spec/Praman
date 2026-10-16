<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use App\Core\Paginator;

final class User extends Model
{
    public const SORTABLE = [
        'id'         => 'u.user_id',
        'name'       => 'u.name',
        'username'   => 'u.username',
        'emp_code'   => 'u.emp_code',
        'panel'      => 'u.panel',
        'role'       => 'r.role_name',
        'last_login' => 'u.last_login_at',
    ];

    public function find(int $id): ?array
    {
        return $this->fetch(
            'SELECT u.*, r.role_name FROM {users} u LEFT JOIN {roles} r ON r.role_id = u.role_id WHERE u.user_id = :id',
            ['id' => $id]
        );
    }

    public function paginateList(Paginator $p): Paginator
    {
        $from = ' FROM {users} u LEFT JOIN {roles} r ON r.role_id = u.role_id WHERE 1 = 1';
        $params = [];
        if ($p->search !== '') {
            $from .= ' AND (u.name LIKE :q1 OR u.username LIKE :q2 OR u.emp_code LIKE :q3 OR u.mobile LIKE :q4 OR u.email LIKE :q5 OR u.designation LIKE :q6)';
            $like = self::like($p->search);
            foreach (['q1', 'q2', 'q3', 'q4', 'q5', 'q6'] as $k) {
                $params[$k] = $like;
            }
        }
        $panel = $p->filter('panel');
        if ($panel !== null && in_array($panel, ['SUPER_ADMIN', 'DATA_ENTRY', 'OIC', 'ROLE_USER'], true)) {
            $from .= ' AND u.panel = :panel';
            $params['panel'] = $panel;
        }
        $role = $p->filter('role_id');
        if ($role !== null && ctype_digit($role)) {
            $from .= ' AND u.role_id = :role';
            $params['role'] = (int) $role;
        }
        $status = $p->filter('status');
        if ($status === 'active' || $status === 'inactive') {
            $from .= ' AND u.is_active = :st';
            $params['st'] = $status === 'active' ? 1 : 0;
        } elseif ($status === 'locked') {
            $from .= ' AND u.locked_until > NOW()';
        }
        return $this->paginate(
            $p,
            'SELECT u.user_id, u.username, u.emp_code, u.name, u.designation, u.mobile, u.email, u.panel, u.role_id,
                    u.is_active, u.must_change_password, u.locked_until, u.last_login_at, r.role_name,
                    (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked',
            $from,
            $params
        );
    }

    public function create(array $d, int $by): int
    {
        return $this->insert('users', [
            'username'             => $d['username'],
            'emp_code'             => $d['emp_code'],
            'name'                 => $d['name'],
            'designation'          => $d['designation'],
            'mobile'               => $d['mobile'],
            'email'                => $d['email'],
            'password_hash'        => self::hash((string) $d['password']),
            'panel'                => $d['panel'],
            'role_id'              => $d['panel'] === 'ROLE_USER' ? (int) $d['role_id'] : null,
            'must_change_password' => (int) $d['must_change_password'],
            'is_active'            => (int) $d['is_active'],
            'created_by'           => $by,
        ]);
    }

    public function update(int $id, array $d, int $by): void
    {
        $this->updateWhere('users', [
            'username'    => $d['username'],
            'emp_code'    => $d['emp_code'],
            'name'        => $d['name'],
            'designation' => $d['designation'],
            'mobile'      => $d['mobile'],
            'email'       => $d['email'],
            'panel'       => $d['panel'],
            'role_id'     => $d['panel'] === 'ROLE_USER' ? (int) $d['role_id'] : null,
            'is_active'   => (int) $d['is_active'],
            'updated_by'  => $by,
        ], 'user_id', $id);
    }

    public function setActive(int $id, bool $active, int $by): void
    {
        $this->updateWhere('users', ['is_active' => $active ? 1 : 0, 'updated_by' => $by], 'user_id', $id);
    }

    /** Admin reset: new temporary password, forced change on next login, unlock. */
    public function resetPassword(int $id, string $password, int $by): void
    {
        $this->run(
            'UPDATE {users} SET password_hash = :h, must_change_password = 1, failed_attempts = 0, locked_until = NULL,
                    password_changed_at = NOW(), updated_by = :by WHERE user_id = :id',
            ['h' => self::hash($password), 'by' => $by, 'id' => $id]
        );
    }

    public function unlock(int $id, int $by): void
    {
        $this->run('UPDATE {users} SET failed_attempts = 0, locked_until = NULL, updated_by = :by WHERE user_id = :id', ['by' => $by, 'id' => $id]);
    }

    public function changePassword(int $userId, string $newPassword): void
    {
        $this->run(
            'UPDATE {users} SET password_hash = :hash, must_change_password = 0, password_changed_at = NOW(), updated_by = :by
              WHERE user_id = :id',
            ['hash' => self::hash($newPassword), 'by' => $userId, 'id' => $userId]
        );
    }

    public function activeSuperAdminCount(): int
    {
        return (int) $this->fetchColumn("SELECT COUNT(*) FROM {users} WHERE panel = 'SUPER_ADMIN' AND is_active = 1");
    }

    /** Active scheme mappings of a user (shown as a warning before deactivation / role change). */
    public function mappingCount(int $id): int
    {
        return (int) $this->fetchColumn('SELECT COUNT(*) FROM {scheme_role_users} WHERE user_id = :id AND is_active = 1', ['id' => $id]);
    }

    private static function hash(string $password): string
    {
        return password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }
}
