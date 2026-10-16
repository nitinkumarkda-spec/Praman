<?php
/** @var array|null $role  @var int|null $usage */
$isEdit = $role !== null;
$action = $isEdit ? '/admin/roles/' . $role['role_id'] : '/admin/roles';
$val = static fn (string $k, mixed $default = '') => old($k, $role[$k] ?? $default);
$mandatory = (string) old('is_mandatory', $isEdit ? (string) $role['is_mandatory'] : '1');
$active = (string) old('is_active', $isEdit ? (string) $role['is_active'] : '1');
if (\App\Core\Session::getFlash('errors')) { // unchecked boxes are absent from POST
    $mandatory = (string) old('is_mandatory', '0');
    $active = (string) old('is_active', '0');
}
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-sitemap" aria-hidden="true"></i> <?= $isEdit ? 'Edit Role' : 'Add Role' ?></h2>
        <p class="page-subtitle"><?= $isEdit ? 'Changes apply to new schemes and new verification rounds.' : 'Create a verification section. Map users to it from the User Master.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/admin/roles')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to list</span></a>
    </div>
</div>

<?php if ($isEdit && ($usage ?? 0) > 0): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>This role is mapped in <strong><?= (int) $usage ?></strong> scheme(s). Existing properties keep their snapshot.</span></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="post" action="<?= e(url($action)) ?>" novalidate data-validate>
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="role_name">Role name <span class="required">*</span></label>
                    <input type="text" id="role_name" name="role_name" class="form-control<?= invalid('role_name') ?>" maxlength="100"
                           value="<?= e($val('role_name')) ?>" placeholder="e.g. Engineering Cell" data-rules="required|min:3|max:100|name" data-label="Role name">
                    <div class="invalid-feedback"><?= e(error('role_name')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="role_code">Role code <span class="required">*</span></label>
                    <input type="text" id="role_code" name="role_code" class="form-control mono<?= invalid('role_code') ?>" maxlength="40"
                           value="<?= e($val('role_code')) ?>" placeholder="e.g. ENGINEERING_CELL" data-uppercase
                           <?= $isEdit ? 'readonly' : 'data-rules="required|code" data-label="Role code"' ?>>
                    <div class="form-hint"><?= $isEdit ? 'Code cannot be changed after creation.' : 'CAPITAL letters, digits and underscore. Cannot be changed later.' ?></div>
                    <div class="invalid-feedback"><?= e(error('role_code')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="role_name_hi">Role name (Hindi)</label>
                    <input type="text" id="role_name_hi" name="role_name_hi" class="form-control<?= invalid('role_name_hi') ?>" maxlength="100"
                           value="<?= e($val('role_name_hi')) ?>" placeholder="e.g. अभियांत्रिकी शाखा" data-rules="max:100|text" data-label="Role name (Hindi)">
                    <div class="invalid-feedback"><?= e(error('role_name_hi')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sla_days">SLA (days to respond) <span class="required">*</span></label>
                    <input type="number" id="sla_days" name="sla_days" class="form-control<?= invalid('sla_days') ?>" min="1" max="365"
                           value="<?= e($val('sla_days', '5')) ?>" data-rules="required|integer|between:1,365" data-label="SLA days">
                    <div class="invalid-feedback"><?= e(error('sla_days')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="sort_order">Display order <span class="required">*</span></label>
                    <input type="number" id="sort_order" name="sort_order" class="form-control<?= invalid('sort_order') ?>" min="0" max="999"
                           value="<?= e($val('sort_order', '0')) ?>" data-rules="required|integer|between:0,999" data-label="Display order">
                    <div class="invalid-feedback"><?= e(error('sort_order')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="description">Description</label>
                    <input type="text" id="description" name="description" class="form-control<?= invalid('description') ?>" maxlength="255"
                           value="<?= e($val('description')) ?>" placeholder="What does this section verify?" data-rules="max:255|text" data-label="Description">
                    <div class="invalid-feedback"><?= e(error('description')) ?></div>
                </div>
                <div class="form-group">
                    <label class="check">
                        <input type="checkbox" name="is_mandatory" value="1"<?= $mandatory === '1' ? ' checked' : '' ?>>
                        <span><strong>Mandatory</strong><br><small class="text-muted">Every scheme must map at least one user of this role.</small></span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1"<?= $active === '1' ? ' checked' : '' ?>>
                        <span><strong>Active</strong><br><small class="text-muted">Inactive roles are skipped for new schemes and rounds.</small></span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" data-loading-text="Saving…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span><?= $isEdit ? 'Update Role' : 'Create Role' ?></span></button>
                <a href="<?= e(url('/admin/roles')) ?>" class="btn btn-light"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>Cancel</span></a>
            </div>
        </form>
    </div>
</div>
