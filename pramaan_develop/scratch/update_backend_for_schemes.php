<?php
// 1. Update MasterController.php
$mcPath = __DIR__ . '/../app/Controllers/MasterController.php';
$mc = file_get_contents($mcPath);

// Replace lines 128-167:
$oldSection = <<<'PHP'
                    // role-user mapping: every mandatory active role needs at least one valid user
                    $map = [];
                    foreach (Role::where(['is_active' => 1], 'sort_order') as $r) {
                        $valid = array_column(User::where(['is_active' => 1, 'role_id' => $r['role_id']]), 'user_id');
                        $ids = array_values(array_unique(array_map('intval', $in->list('role_users_' . $r['role_id']))));
                        if (array_diff($ids, $valid)) {
                            return ['fail' => ['Only active users of "' . $r['role_name'] . '" can be mapped to it.', null]];
                        }
                        if ($ids) {
                            $map[$r['role_id']] = $ids;
                        } elseif ($r['is_mandatory']) {
                            return ['fail' => [$valid ? 'Select at least one user for "' . $r['role_name'] . '" (mandatory role).'
                                : '"' . $r['role_name'] . '" is mandatory but has no active user. Ask the Super Admin to add one.', null]];
                        }
                    }
                    return ['existing' => $existing, 'fresh' => $fresh, 'map' => $map];
                },
                'saved' => static function (int $sid, array $x, ?int $by): void {
                    foreach ($x['existing'] as $e) {
                        Sector::update($e['s']['sector_id'], ['sector_name' => $e['name'], 'is_active' => $e['active'], 'updated_by' => $by]);
                    }
                    $order = Sector::max('sort_order', ['scheme_id' => $sid]);
                    foreach ($x['fresh'] as $n) {
                        Sector::insert(['scheme_id' => $sid, 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => $by]);
                    }
                    foreach (SchemeUser::where(['scheme_id' => $sid, 'is_active' => 1]) as $m) {
                        if (!in_array((int) $m['user_id'], $x['map'][$m['role_id']] ?? [], true)) {
                            SchemeUser::update($m['map_id'], ['is_active' => 0]);
                        }
                    }
                    foreach ($x['map'] as $rid => $uids) {
                        foreach ($uids as $uid) {
                            if (!SchemeUser::exists(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1])) {
                                SchemeUser::insert(['scheme_id' => $sid, 'role_id' => $rid, 'user_id' => $uid, 'is_active' => 1, 'created_by' => $by]);
                            }
                        }
                    }
                },
                'audit' => static fn ($x) => ['sectors' => $x['fresh'], 'mapping' => $x['map']],
                'note' => static fn ($x, $isNew) => $isNew ? ' — ' . count($x['fresh']) . ' sector(s).' : ' Changes apply to new properties only.',
PHP;

$newSection = <<<'PHP'
                    return ['existing' => $existing, 'fresh' => $fresh];
                },
                'saved' => static function (int $sid, array $x, ?int $by): void {
                    foreach ($x['existing'] as $e) {
                        Sector::update($e['s']['sector_id'], ['sector_name' => $e['name'], 'is_active' => $e['active'], 'updated_by' => $by]);
                    }
                    $order = Sector::max('sort_order', ['scheme_id' => $sid]);
                    foreach ($x['fresh'] as $n) {
                        Sector::insert(['scheme_id' => $sid, 'sector_name' => $n, 'sort_order' => ++$order, 'is_active' => 1, 'created_by' => $by]);
                    }
                },
                'audit' => static fn ($x) => ['sectors' => $x['fresh']],
                'note' => static fn ($x, $isNew) => $isNew ? ' — ' . count($x['fresh']) . ' sector(s).' : ' Changes apply to new properties only.',
PHP;

$mc = str_replace(str_replace("\r\n", "\n", $oldSection), str_replace("\r\n", "\n", $newSection), str_replace("\r\n", "\n", $mc));
file_put_contents($mcPath, $mc);
echo "Updated MasterController.php\n";

// 2. Update PropertyController.php (remove missingMandatoryRoles check on scheme)
$pcPath = __DIR__ . '/../app/Controllers/PropertyController.php';
$pc = file_get_contents($pcPath);
$oldCheck = <<<'PHP'
        if (!$prop && ($missing = PropertyService::missingMandatoryRoles($schemeId))) {
            $this->fail('Cannot create properties: no active user is mapped in this scheme for mandatory role(s): ' . implode(', ', $missing) . '. Update the scheme\'s role mapping first.', 'scheme_id');
        }
PHP;
$pc = str_replace(str_replace("\r\n", "\n", $oldCheck), '// Role-user mapping is bound when sending properties for verification', str_replace("\r\n", "\n", $pc));
file_put_contents($pcPath, $pc);
echo "Updated PropertyController.php\n";

// 3. Update PropertyService.php
$psPath = __DIR__ . '/../app/Services/PropertyService.php';
$ps = file_get_contents($psPath);
$oldMissing = <<<'PHP'
    public static function missingMandatoryRoles(int $schemeId): array
    {
        $missing = [];
        foreach (Role::where(['is_active' => 1, 'is_mandatory' => 1], 'sort_order') as $r) {
            $ok = false;
            foreach (SchemeUser::where(['scheme_id' => $schemeId, 'role_id' => $r['role_id'], 'is_active' => 1]) as $m) {
                $u = User::find($m['user_id']);
                if ($u && $u['is_active'] && (int) $u['role_id'] === (int) $r['role_id']) {
                    $ok = true;
                    break;
                }
            }
            if (!$ok) {
                $missing[] = $r['role_name'];
            }
        }
        return $missing;
    }
PHP;
$newMissing = <<<'PHP'
    public static function missingMandatoryRoles(int $schemeId): array
    {
        // Role-user mapping will be verified when sending properties for verification
        return [];
    }
PHP;
$ps = str_replace(str_replace("\r\n", "\n", $oldMissing), str_replace("\r\n", "\n", $newMissing), str_replace("\r\n", "\n", $ps));
file_put_contents($psPath, $ps);
echo "Updated PropertyService.php\n";

// 4. Update forms.js
$formsPath = __DIR__ . '/../public/assets/js/forms.js';
$forms = file_get_contents($formsPath);
$oldFormsCheck = <<<'JS'
            V().addCheck(form, function () {
                let first = null;
                form.querySelectorAll('[data-mapping-role][data-mandatory="1"]').forEach(function (card) {
                    if (card.querySelector('input:checked')) { return; }
                    card.classList.add('has-error');
                    const err = card.querySelector('[data-mapping-error]');
                    const msg = 'Select at least one user for "' + card.getAttribute('data-role-name') + '" (mandatory role).';
                    if (err) { err.textContent = msg; }
                    if (!first) { first = { field: null, message: msg }; card.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                });
                return first;
            });
JS;
$forms = str_replace(str_replace("\r\n", "\n", $oldFormsCheck), '// Role-user mapping is bound when sending properties for verification', str_replace("\r\n", "\n", $forms));
file_put_contents($formsPath, $forms);
echo "Updated forms.js\n";

