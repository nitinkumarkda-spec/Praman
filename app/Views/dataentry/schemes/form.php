<?php
/**
 * @var array|null $scheme
 * @var array $sectors      existing sectors (edit)
 * @var array $mapping      role_id => user_ids (edit)
 * @var array $roleOptions  role_id => {role, users}
 */
use App\Core\Lookups;
use App\Core\Session;

$isEdit = $scheme !== null;
$action = $isEdit ? '/data-entry/schemes/' . $scheme['scheme_id'] : '/data-entry/schemes';
$val = static fn (string $k, mixed $default = '') => old($k, $scheme[$k] ?? $default);
$hasErrors = (bool) Session::getFlash('errors');

$oldExisting = $hasErrors ? (array) old('sectors_existing', []) : null;
$newRows = $hasErrors ? array_values(array_filter((array) old('sectors_new', []), static fn ($x) => is_string($x))) : [];
if (!$isEdit && $newRows === []) {
    $newRows = [''];
}
$selectedMap = $hasErrors ? (array) old('role_users', []) : $mapping;
$doneChecked = $hasErrors ? old('sectors_done') === '1' : false;
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-map" aria-hidden="true"></i> <?= $isEdit ? 'Edit Scheme' : 'Create Scheme' ?></h2>
        <p class="page-subtitle"><?= $isEdit
            ? 'Changes to sectors and role mapping apply to NEW properties only — existing properties keep their snapshot.'
            : 'Fill scheme details, add all its sectors, tick "Done" and map a user for every role.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/data-entry/schemes')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to list</span></a>
    </div>
</div>

<form method="post" action="<?= e(url($action)) ?>" novalidate data-validate data-scheme-form
      data-confirm="<?= $isEdit ? 'Save changes to this scheme?' : 'Create this scheme with the sectors and role mapping shown?' ?>">
    <?= csrf_field() ?>

    <!-- 1. Scheme details -->
    <div class="card">
        <div class="card-header"><h3 class="card-title"><span class="step-no">1</span> Scheme details</h3></div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="scheme_name">Scheme name <span class="required">*</span></label>
                    <input type="text" id="scheme_name" name="scheme_name" class="form-control<?= invalid('scheme_name') ?>" maxlength="150"
                           value="<?= e($val('scheme_name')) ?>" placeholder="e.g. Ravindra Nagar Awasiya Yojana" data-rules="required|min:3|max:150|text" data-label="Scheme name">
                    <div class="invalid-feedback"><?= e(error('scheme_name')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scheme_code">Scheme code <span class="required">*</span></label>
                    <input type="text" id="scheme_code" name="scheme_code" class="form-control mono<?= invalid('scheme_code') ?>" maxlength="30"
                           value="<?= e($val('scheme_code')) ?>" placeholder="e.g. RNY-2019" data-uppercase
                           <?= $isEdit ? 'readonly' : 'data-rules="required|ucode" data-label="Scheme code"' ?>>
                    <div class="form-hint"><?= $isEdit ? 'Code cannot be changed.' : 'CAPITAL letters, digits, / _ - . Cannot be changed later.' ?></div>
                    <div class="invalid-feedback"><?= e(error('scheme_code')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="scheme_type">Scheme type <span class="required">*</span></label>
                    <select id="scheme_type" name="scheme_type" class="form-select<?= invalid('scheme_type') ?>" data-rules="required" data-label="Scheme type">
                        <?= Lookups::options(Lookups::SCHEME_TYPES, (string) $val('scheme_type'), 'Select type') ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('scheme_type')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="zone">Zone <span class="required">*</span></label>
                    <?php
                    $zones = Lookups::ZONES;
                    $curZone = (string) $val('zone');
                    if ($curZone !== '' && !isset($zones[$curZone])) {
                        $zones[$curZone] = $curZone . ' (old value)';
                    } ?>
                    <select id="zone" name="zone" class="form-select<?= invalid('zone') ?>" data-rules="required" data-label="Zone">
                        <?= Lookups::options($zones, $curZone, 'Select zone') ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('zone')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="year_of_scheme">Year of scheme <span class="required">*</span></label>
                    <input type="number" id="year_of_scheme" name="year_of_scheme" class="form-control<?= invalid('year_of_scheme') ?>" min="1950" max="2100"
                           value="<?= e($val('year_of_scheme')) ?>" placeholder="e.g. 2019" data-rules="required|year" data-label="Year of scheme">
                    <div class="invalid-feedback"><?= e(error('year_of_scheme')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Sectors -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="step-no">2</span> Sectors <span class="required">*</span></h3>
            <span class="badge badge-info"><span data-sector-count>0</span> active</span>
        </div>
        <div class="card-body">
            <?php if (error('sectors') !== ''): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i><span><?= e(error('sectors')) ?></span></div>
            <?php endif; ?>

            <?php if ($isEdit && $sectors !== []): ?>
                <p class="form-section"><i class="fa-solid fa-list" aria-hidden="true"></i> Existing sectors — rename or deactivate</p>
                <div class="sector-list">
                    <?php foreach ($sectors as $s):
                        $sid = (int) $s['sector_id'];
                        $o = is_array($oldExisting[$sid] ?? null) ? $oldExisting[$sid] : null;
                        $name = $o !== null ? (string) ($o['name'] ?? '') : $s['sector_name'];
                        $act = $o !== null ? (($o['active'] ?? '0') === '1') : (int) $s['is_active'] === 1; ?>
                        <div class="sector-row">
                            <div class="sector-input">
                                <input type="text" name="sectors_existing[<?= $sid ?>][name]" class="form-control" maxlength="80" value="<?= e($name) ?>"
                                       aria-label="Sector name" data-sector-name>
                            </div>
                            <label class="check sector-active">
                                <input type="checkbox" name="sectors_existing[<?= $sid ?>][active]" value="1"<?= $act ? ' checked' : '' ?> data-sector-active>
                                <span>Active</span>
                            </label>
                            <span class="badge badge-muted" title="Properties in this sector"><i class="fa-solid fa-building" aria-hidden="true"></i> <?= (int) $s['property_count'] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="form-hint mb-3">Deactivated sectors keep their existing properties but cannot be used for new ones.</p>
            <?php endif; ?>

            <p class="form-section"><i class="fa-solid fa-plus" aria-hidden="true"></i> <?= $isEdit ? 'Add new sectors' : 'Add sectors' ?></p>
            <div class="sector-list" data-repeater>
                <?php foreach ($newRows as $i => $row): ?>
                    <div class="sector-row" data-repeater-item>
                        <div class="sector-input">
                            <input type="text" name="sectors_new[]" class="form-control" maxlength="80" value="<?= e($row) ?>"
                                   placeholder="Sector name, e.g. Sector-4" aria-label="New sector name" data-sector-name data-sector-new>
                        </div>
                        <button type="button" class="btn btn-light btn-sm text-danger" data-repeater-remove aria-label="Remove sector">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> <span>Remove</span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <template data-repeater-template>
                <div class="sector-row anim-fade-up" data-repeater-item>
                    <div class="sector-input">
                        <input type="text" name="sectors_new[]" class="form-control" maxlength="80" placeholder="Sector name, e.g. Sector-4"
                               aria-label="New sector name" data-sector-name data-sector-new>
                    </div>
                    <button type="button" class="btn btn-light btn-sm text-danger" data-repeater-remove aria-label="Remove sector">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i> <span>Remove</span>
                    </button>
                </div>
            </template>
            <button type="button" class="btn btn-outline btn-sm mt-2" data-repeater-add>
                <i class="fa-solid fa-plus" aria-hidden="true"></i> <span>Add another sector</span>
            </button>

            <div class="form-group done-box mt-3">
                <label class="check">
                    <input type="checkbox" name="sectors_done" value="1" id="sectors_done"<?= $doneChecked ? ' checked' : '' ?>
                           class="<?= invalid('sectors_done') ?>" data-rules="required" data-label="Sector add karna complete / Done">
                    <span><strong>Sector add karna complete / Done</strong> <span class="required">*</span><br>
                    <small class="text-muted">Tick after adding all sectors. The scheme is saved only with at least one sector and this box ticked.</small></span>
                </label>
                <div class="invalid-feedback"><?= e(error('sectors_done')) ?></div>
            </div>
        </div>
    </div>

    <!-- 3. Role → user mapping -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><span class="step-no">3</span> Role – user mapping</h3>
            <span class="text-muted small">These users will verify every property created in this scheme.</span>
        </div>
        <div class="card-body">
            <?php if ($roleOptions === []): ?>
                <div class="alert alert-warning"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>No active roles exist. Ask the Super Admin to create roles.</span></div>
            <?php endif; ?>
            <div class="mapping-grid">
                <?php foreach ($roleOptions as $roleId => $opt):
                    $role = $opt['role'];
                    $picked = array_map('intval', (array) ($selectedMap[$roleId] ?? []));
                    $err = error('role_users_' . $roleId); ?>
                    <fieldset class="mapping-card<?= $err !== '' ? ' has-error' : '' ?>" data-mapping-role data-mandatory="<?= (int) $role['is_mandatory'] ?>" data-role-name="<?= e($role['role_name']) ?>">
                        <legend class="mapping-head">
                            <span><i class="fa-solid fa-user-tie" aria-hidden="true"></i> <?= e($role['role_name']) ?></span>
                            <?= (int) $role['is_mandatory'] === 1 ? '<span class="badge badge-gold">Mandatory</span>' : '<span class="badge badge-muted">Optional</span>' ?>
                        </legend>
                        <?php if ($opt['users'] === []): ?>
                            <p class="text-muted small"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> No active user in this role.</p>
                        <?php else: foreach ($opt['users'] as $u): ?>
                            <label class="check mapping-user">
                                <input type="checkbox" name="role_users[<?= (int) $roleId ?>][]" value="<?= (int) $u['user_id'] ?>"<?= in_array((int) $u['user_id'], $picked, true) ? ' checked' : '' ?>>
                                <span><?= e($u['name']) ?><br><small class="text-muted"><?= e($u['designation'] . ' · ' . $u['emp_code']) ?></small></span>
                            </label>
                        <?php endforeach; endif; ?>
                        <div class="invalid-feedback" data-mapping-error><?= e($err) ?></div>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="form-actions sticky-actions">
        <button type="submit" class="btn btn-primary btn-lg" data-loading-text="Saving…">
            <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span><?= $isEdit ? 'Update Scheme' : 'Create Scheme' ?></span>
        </button>
        <a href="<?= e(url('/data-entry/schemes')) ?>" class="btn btn-light btn-lg"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>Cancel</span></a>
    </div>
</form>
