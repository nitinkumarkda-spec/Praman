<?php
// Script to remove role users and property_users across the codebase

// 1. schema.sql
$schemaFile = __DIR__ . '/../database/schema.sql';
$schema = file_get_contents($schemaFile);
$schema = preg_replace('/-- snapshot of the scheme\'s role users.*?\nCREATE TABLE property_users \(.*?\n\) ENGINE=InnoDB;\n+/s', '', $schema);
file_put_contents($schemaFile, $schema);
echo "1. Updated database/schema.sql\n";

// 2. tables.php
$tablesFile = __DIR__ . '/../config/tables.php';
$tables = file_get_contents($tablesFile);
$tables = preg_replace('/^\s*\'property_users\'\s*=>\s*\'property_users\',\r?\n/m', '', $tables);
file_put_contents($tablesFile, $tables);
echo "2. Updated config/tables.php\n";

// 3. Delete app/Models/PropertyUser.php
$modelFile = __DIR__ . '/../app/Models/PropertyUser.php';
if (file_exists($modelFile)) {
    unlink($modelFile);
    echo "3. Deleted app/Models/PropertyUser.php\n";
}

// 4. Seeder.php
$seederFile = __DIR__ . '/../app/Services/Seeder.php';
$seeder = file_get_contents($seederFile);
$seeder = str_replace("'property_users', ", '', $seeder);
file_put_contents($seederFile, $seeder);
echo "4. Updated app/Services/Seeder.php\n";

// 5. StateService.php
$stateFile = __DIR__ . '/../app/Services/StateService.php';
$state = file_get_contents($stateFile);
$state = str_replace('use App\Models\PropertyUser;', '', $state);
$state = preg_replace('/^\s*\$t\[\'property_users\'\]\s*=.*?;/m', "\$t['property_users'] = [];", $state);
file_put_contents($stateFile, $state);
echo "5. Updated app/Services/StateService.php\n";

// 6. PropertyService.php
$psFile = __DIR__ . '/../app/Services/PropertyService.php';
$ps = file_get_contents($psFile);
$ps = str_replace('use App\Models\PropertyUser;', '', $ps);
$ps = preg_replace('/use App\\\\Models\\\\PropertyUser;\r?\n/', '', $ps);
file_put_contents($psFile, $ps);
echo "6. Updated app/Services/PropertyService.php\n";

// 7. Verification.php
$vfFile = __DIR__ . '/../app/Services/Verification.php';
$vf = file_get_contents($vfFile);
$vf = str_replace("use App\\Models\\PropertyUser;\n", '', str_replace("\r\n", "\n", $vf));
$oldVfRoles = <<<'PHP'
            // active snapshot rows; role active, user active and still in that role
            $roles = [];
            foreach (PropertyUser::where(['property_id' => $p['property_id'], 'is_active' => 1]) as $pru) {
                $r = Role::find($pru['role_id']);
                $u = User::find($pru['user_id']);
                if (!$r || !$r['is_active'] || !$u || !$u['is_active'] || (int) $u['role_id'] !== (int) $r['role_id']) {
                    continue;
                }
                $roles[$r['role_id']] ??= ['role' => $r, 'users' => []];
                if (!in_array($u['user_id'], $roles[$r['role_id']]['users'], true)) {
                    $roles[$r['role_id']]['users'][] = $u['user_id'];
                }
            }
PHP;
$newVfRoles = <<<'PHP'
            // Active roles and active users of those roles
            $roles = [];
            foreach (Role::where(['is_active' => 1], 'sort_order') as $r) {
                $uids = array_column(User::where(['is_active' => 1, 'role_id' => $r['role_id']]), 'user_id');
                if ($uids) {
                    $roles[$r['role_id']] = ['role' => $r, 'users' => $uids];
                }
            }
PHP;
$vf = str_replace(str_replace("\r\n", "\n", $oldVfRoles), str_replace("\r\n", "\n", $newVfRoles), $vf);
file_put_contents($vfFile, $vf);
echo "7. Updated app/Services/Verification.php\n";

// 8. db.js
$dbFile = __DIR__ . '/../public/assets/js/db.js';
$db = file_get_contents($dbFile);
$db = preg_replace('/^\s*property_users:\s*\'pru_id\',\r?\n/m', '', $db);
$oldRoleUsersDb = <<<'JS'
        roleUsers: function (propertyId) {
            return DB.where('property_users', { property_id: propertyId, is_active: 1 }).map(function (x) {
                const r = DB.find('roles', x.role_id) || {};
                const u = DB.find('users', x.user_id) || {};
                return {
                    role_id: r.role_id, role_name: r.role_name, sort_order: r.sort_order, is_mandatory: r.is_mandatory,
                    role_active: r.is_active, user_id: u.user_id, name: u.name || '', mobile: u.mobile || '', user_active: u.is_active
                };
            }).sort(function (a, b) { return a.sort_order - b.sort_order || a.name.localeCompare(b.name); });
        }
JS;
$newRoleUsersDb = <<<'JS'
        roleUsers: function (propertyId) {
            return [];
        }
JS;
$db = str_replace(str_replace("\r\n", "\n", $oldRoleUsersDb), str_replace("\r\n", "\n", $newRoleUsersDb), str_replace("\r\n", "\n", $db));
file_put_contents($dbFile, $db);
echo "8. Updated public/assets/js/db.js\n";

// 9. reports.js
$repFile = __DIR__ . '/../public/assets/js/pages/reports.js';
$rep = file_get_contents($repFile);
$rep = str_replace("\r\n", "\n", $rep);
$rep = preg_replace('/^\s*\{\s*id:\s*\'roles\'.*?\},\n/m', '', $rep);
$rep = str_replace('/ 9 Columns selected', '/ 8 Columns selected', $rep);
$rep = str_replace('Select All (9)', 'Select All (8)', $rep);
$rep = preg_replace('/^\s*\}\s*else\s+if\s*\(c\.id\s*===\s*\'roles\'\)\s*\{.*?\n\s*\}\s*/ms', '', $rep);
$rep = preg_replace('/^\s*else\s+if\s*\(c\.id\s*===\s*\'roles\'\)\s*r\.push\(.*?\);\n/m', '', $rep);
$rep = preg_replace('/^\s*else\s+if\s*\(c\.id\s*===\s*\'roles\'\)\s*cells\s*\+=\s*\'<td>\'.*?;\n/m', '', $rep);
file_put_contents($repFile, $rep);
echo "9. Updated public/assets/js/pages/reports.js\n";

// 10. dataentry.js
$deFile = __DIR__ . '/../public/assets/js/pages/dataentry.js';
$de = file_get_contents($deFile);
$de = str_replace("\r\n", "\n", $de);

// in P.propertyRows()
$oldPropRows = <<<'JS'
            const sc = DB.find('schemes', p.scheme_id);
            const se = DB.find('sectors', p.sector_id);
            const ru = DB.Property.roleUsers(p.property_id);
            const cu = DB.find('users', p.created_by);
            const round = p.current_round_id ? DB.find('rounds', p.current_round_id) : null;
            const tasks = round ? DB.where('tasks', { round_id: round.round_id }) : [];
            return Object.assign({}, p, {
                scheme_name: sc ? sc.scheme_name : '—', sector_name: se ? se.sector_name : '—',
                type_name: L.PROPERTY_TYPES[p.plot_type_id] || '—', status_name: P.statusName(p.status_id),
                area: sc ? DB.areaName(sc.area_id) : '—',
                role_count: new Set(ru.map(function (x) { return x.role_id; })).size,
                role_users: ru.map(function (x) { return x.role_name + ': ' + x.name; }).join(', '),
                created_by_name: cu ? cu.name : '', round_no: round ? round.round_no : null,
                tasks_total: tasks.length, tasks_approved: tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length
            });
JS;
$newPropRows = <<<'JS'
            const sc = DB.find('schemes', p.scheme_id);
            const se = DB.find('sectors', p.sector_id);
            const cu = DB.find('users', p.created_by);
            const round = p.current_round_id ? DB.find('rounds', p.current_round_id) : null;
            const tasks = round ? DB.where('tasks', { round_id: round.round_id }) : [];
            return Object.assign({}, p, {
                scheme_name: sc ? sc.scheme_name : '—', sector_name: se ? se.sector_name : '—',
                type_name: L.PROPERTY_TYPES[p.plot_type_id] || '—', status_name: P.statusName(p.status_id),
                area: sc ? DB.areaName(sc.area_id) : '—',
                created_by_name: cu ? cu.name : '', round_no: round ? round.round_no : null,
                tasks_total: tasks.length, tasks_approved: tasks.filter(function (t) { return t.task_status === 'APPROVED'; }).length
            });
JS;
$de = str_replace($oldPropRows, $newPropRows, $de);

// Remove openRoleUsers function and click listener
$oldOpenRu = <<<'JS'
    function initials(name) {
        return String(name || '').split(/\s+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0); }).join('').toUpperCase();
    }
    function openRoleUsers(id, opener) {
        const prop = DB.find('properties', id);
        if (!prop) { return; }
        const sc = DB.find('schemes', prop.scheme_id) || {}, se = DB.find('sectors', prop.sector_id) || {};
        const groups = [];
        DB.Property.roleUsers(id).forEach(function (x) {
            let g = groups[groups.length - 1];
            if (!g || g.role_id !== x.role_id) {
                g = { role_id: x.role_id, role_name: x.role_name, role_active: x.role_active, users: [] };
                groups.push(g);
            }
            g.users.push(x);
        });
        const userCount = groups.reduce(function (n, g) { return n + g.users.length; }, 0);
        const body = groups.length ? '<ol class="ru-list">' + groups.map(function (g, i) {
            return '<li class="ru-item"><span class="ru-no">' + (i + 1) + '</span>' +
                '<div class="ru-role"><strong>' + e(g.role_name) + '</strong>' +
                (g.role_active ? '' : '<span class="badge badge-muted">role inactive</span>') + '</div>' +
                '<div class="ru-users">' + g.users.map(function (u) {
                    return '<div class="ru-user"><span class="ru-avatar" aria-hidden="true">' + e(initials(u.name)) + '</span>' +
                        '<span class="ru-name"><strong>' + e(u.name) + '</strong>' + (u.mobile ? '<small>' + e(u.mobile) + '</small>' : '') + '</span>' +
                        (u.user_active ? '' : '<span class="badge badge-danger">inactive</span>') + '</div>';
                }).join('') + '</div></li>';
        }).join('') + '</ol>' : '<p class="ru-empty">No role users assigned.</p>';

        const old = document.getElementById('ru-modal');
        if (old) { old.remove(); }
        const m = document.createElement('div');
        m.id = 'ru-modal';
        m.className = 'modal-backdrop ru-modal';
        m.innerHTML = '<div class="modal" role="dialog" aria-modal="true" aria-labelledby="ru-modal-title"><div class="modal-header">' +
            '<span class="modal-icon">' + icon('fa-user-tie') + '</span><div><h2 class="modal-title" id="ru-modal-title">Assigned role users</h2>' +
            '<p>Plot <strong>' + e(prop.plot_no) + '</strong> · ' + e(sc.scheme_name) + (se.sector_name ? ' / ' + e(se.sector_name) : '') + ' · ' + groups.length + ' roles · ' + userCount + ' users</p></div>' +
            '<button type="button" class="btn btn-light btn-sm btn-icon-only ru-close" data-ru-close aria-label="Close">' + icon('fa-xmark') + '</button></div>' +
            '<div class="modal-body">' + body + '</div>' +
            '<div class="modal-footer"><button type="button" class="btn btn-light" data-ru-close>' + icon('fa-xmark') + ' <span>Close</span></button></div></div>';
        document.body.appendChild(m);
        document.body.classList.add('modal-open');
        void m.offsetWidth;
        m.classList.add('is-open');
        m.querySelector('[data-ru-close]').focus();
        function close() {
            m.classList.remove('is-open');
            document.body.classList.remove('modal-open');
            document.removeEventListener('keydown', onKey);
            window.removeEventListener('praman:navigate', close);
            window.setTimeout(function () { m.remove(); }, 180);
            if (opener && opener.isConnected) { opener.focus(); }
        }
        function onKey(ev) { if (ev.key === 'Escape') { close(); } }
        m.addEventListener('click', function (ev) { if (ev.target === m || ev.target.closest('[data-ru-close]')) { close(); } });
        document.addEventListener('keydown', onKey);
        window.addEventListener('praman:navigate', close);
    }
    document.addEventListener('click', function (ev) {
        const b = ev.target.closest('[data-role-users]');
        if (b) { openRoleUsers(Number(b.getAttribute('data-role-users')), b); }
    });
JS;
$de = str_replace($oldOpenRu, '', $de);

// In propertyListPage table row:
$oldRuTd = <<<'JS'
                '<td data-label="Area" class="nowrap">' + P.areaCell(r) + '</td>' +
                '<td data-label="Role users"><button type="button" class="badge badge-muted ru-trigger" data-role-users="' + r.property_id + '" aria-haspopup="dialog" title="View assigned role users">' +
                icon('fa-user-tie') + ' ' + r.role_count + ' roles ' + icon('fa-chevron-down', 'ru-caret') + '</button></td>' +
                '<td data-label="Verification" class="cell-vf">' + verificationCell(r) + '</td>' +
JS;
$newRuTd = <<<'JS'
                '<td data-label="Area" class="nowrap">' + P.areaCell(r) + '</td>' +
                '<td data-label="Verification" class="cell-vf">' + verificationCell(r) + '</td>' +
JS;
$de = str_replace($oldRuTd, $newRuTd, $de);

// In propertyListPage table headers:
$oldRuTh = "P.th_sort(p, 'type', 'Plot type') + P.th_sort(p, 'area', 'Area') + '<th>Role users</th><th class=\"cell-vf\">Verification</th>' +";
$newRuTh = "P.th_sort(p, 'type', 'Plot type') + P.th_sort(p, 'area', 'Area') + '<th class=\"cell-vf\">Verification</th>' +";
$de = str_replace($oldRuTh, $newRuTh, $de);

// Col counts
$de = str_replace('const colCount = canSend ? 12 : 11;', 'const colCount = canSend ? 11 : 10;', $de);

// Confirm texts in propertyListPage
$de = str_replace(
    'data-confirm="Send the selected properties for verification? A new round will be created for each and all mapped role users will get a task."',
    'data-confirm="Send the selected properties for verification? A new round will be created for each."',
    $de
);
$de = str_replace(
    'e(\'Send plot \' + r.plot_no + \' (\' + r.scheme_name + (r.sector_id ? \' / \' + r.sector_name : \'\') + \') for verification to \' + r.role_count + \' role(s)?\')',
    'e(\'Send plot \' + r.plot_no + \' (\' + r.scheme_name + (r.sector_id ? \' / \' + r.sector_name : \'\') + \') for verification?\')',
    $de
);

// In propertyForm:
$oldPfRu = <<<'JS'
        const roleUsers = DB.Property.roleUsers(prop.property_id);
        const optional = ' <span class="pb-opt">(optional)</span>';
JS;
$newPfRu = <<<'JS'
        const optional = ' <span class="pb-opt">(optional)</span>';
JS;
$de = str_replace($oldPfRu, $newPfRu, $de);
$de = str_replace('<div class="form-hint">Scheme cannot be changed after creation (role users were assigned from it).</div>', '<div class="form-hint">Scheme cannot be changed after creation.</div>', $de);

$oldPfSnapCard = <<<'JS'
            (roleUsers.length ? '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-user-tie') + ' Assigned role users (snapshot)</h3></div><div class="card-body"><div class="chip-list">' +
                roleUsers.map(function (ru) { return '<span class="chip"><strong>' + e(ru.role_name) + ':</strong> ' + e(ru.name) + '</span>'; }).join('') + '</div></div></div>' : '') +
JS;
$de = str_replace($oldPfSnapCard, '', $de);

// In propertyDetail:
$oldPdRu = <<<'JS'
        const roleUsers = DB.Property.roleUsers(prop.property_id);
        const rounds = DB.where('rounds', { property_id: prop.property_id }).sort(function (a, b) { return b.round_no - a.round_no; }).map(function (r) {
JS;
$newPdRu = <<<'JS'
        const rounds = DB.where('rounds', { property_id: prop.property_id }).sort(function (a, b) { return b.round_no - a.round_no; }).map(function (r) {
JS;
$de = str_replace($oldPdRu, $newPdRu, $de);

$de = str_replace('data-confirm="Send this property for verification to all mapped role users?"', 'data-confirm="Send this property for verification?"', $de);

$oldPdCard = <<<'JS'
            '<div class="card"><div class="card-header"><h3 class="card-title">' + icon('fa-user-tie') + ' Assigned role users</h3><span class="text-muted small">Snapshot taken when property was created</span></div>' +
            '<div class="card-body p-0"><div class="table-wrap"><table class="table table-stack"><thead><tr><th>Role</th><th>User</th><th>Mobile</th></tr></thead><tbody>' +
            (roleUsers.length ? roleUsers.map(function (ru) {
                return '<tr><td data-label="Role">' + e(ru.role_name) + (ru.role_active ? '' : ' <span class="badge badge-muted">role inactive</span>') + '</td>' +
                    '<td data-label="User">' + e(ru.name) + (ru.user_active ? '' : ' <span class="badge badge-danger">inactive</span>') + '</td><td data-label="Mobile">' + e(ru.mobile || '—') + '</td></tr>';
            }).join('') : P.empty_state(3, false, 'No role users assigned')) + '</tbody></table></div></div></div></div>';
JS;
$newPdCard = "</div>";
$de = str_replace($oldPdCard, $newPdCard, $de);

file_put_contents($deFile, $de);
echo "10. Updated public/assets/js/pages/dataentry.js\n";
echo "ALL ROLE USERS REMOVAL TASKS COMPLETED!\n";

