<?php
/** @var array|null $user  @var array $roles  @var int|null $mappings  @var bool|null $isSelf */
use App\Core\Auth;
use App\Core\Session;

$isEdit = $user !== null;
$isSelf = $isSelf ?? false;
$action = $isEdit ? '/admin/users/' . $user['user_id'] : '/admin/users';
$val = static fn (string $k, mixed $default = '') => old($k, $user[$k] ?? $default);
$hasErrors = (bool) Session::getFlash('errors');
$active = $hasErrors ? (string) old('is_active', '0') : ($isEdit ? (string) $user['is_active'] : '1');
$mustChange = $hasErrors ? (string) old('must_change_password', '0') : '1';
$panel = (string) $val('panel', 'ROLE_USER');
$roleId = (string) $val('role_id');
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-user-gear" aria-hidden="true"></i> <?= $isEdit ? 'Edit User' : 'Add User' ?></h2>
        <p class="page-subtitle"><?= $isEdit ? e($user['name'] . ' · ' . $user['username']) : 'Create a login for a panel user or a role user.' ?></p>
    </div>
    <div class="page-actions">
        <a href="<?= e(url('/admin/users')) ?>" class="btn btn-light"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to list</span></a>
    </div>
</div>

<?php if ($isEdit && ($mappings ?? 0) > 0): ?>
    <div class="alert alert-info"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>This user is mapped in <strong><?= (int) $mappings ?></strong> scheme role mapping(s). Changing the role or deactivating removes them from <em>new</em> properties only.</span></div>
<?php endif; ?>

<div class="<?= $isEdit ? 'grid-2' : '' ?>">
<div class="card">
    <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-id-card" aria-hidden="true"></i> User details</h3></div>
    <div class="card-body">
        <form method="post" action="<?= e(url($action)) ?>" novalidate data-validate autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="name">Full name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" class="form-control<?= invalid('name') ?>" maxlength="120" value="<?= e($val('name')) ?>"
                           data-rules="required|min:3|max:120|name" data-label="Full name">
                    <div class="invalid-feedback"><?= e(error('name')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="designation">Designation <span class="required">*</span></label>
                    <input type="text" id="designation" name="designation" class="form-control<?= invalid('designation') ?>" maxlength="100" value="<?= e($val('designation')) ?>"
                           placeholder="e.g. Junior Engineer" data-rules="required|min:2|max:100|text" data-label="Designation">
                    <div class="invalid-feedback"><?= e(error('designation')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="username">Username (login id) <span class="required">*</span></label>
                    <input type="text" id="username" name="username" class="form-control mono<?= invalid('username') ?>" maxlength="50" value="<?= e($val('username')) ?>"
                           placeholder="e.g. eng3" autocomplete="off" data-rules="required|min:3|max:50|alpha_dash" data-label="Username">
                    <div class="invalid-feedback"><?= e(error('username')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="emp_code">Employee code <span class="required">*</span></label>
                    <input type="text" id="emp_code" name="emp_code" class="form-control<?= invalid('emp_code') ?>" maxlength="30" value="<?= e($val('emp_code')) ?>"
                           placeholder="e.g. KDA-ENG-003" data-uppercase data-rules="required|ucode" data-label="Employee code">
                    <div class="invalid-feedback"><?= e(error('emp_code')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="mobile">Mobile <span class="required">*</span></label>
                    <input type="tel" id="mobile" name="mobile" class="form-control<?= invalid('mobile') ?>" maxlength="10" inputmode="numeric" value="<?= e($val('mobile')) ?>"
                           placeholder="10-digit mobile" data-rules="required|mobile" data-label="Mobile">
                    <div class="invalid-feedback"><?= e(error('mobile')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="email">Email <span class="required">*</span></label>
                    <input type="email" id="email" name="email" class="form-control<?= invalid('email') ?>" maxlength="150" value="<?= e($val('email')) ?>"
                           data-rules="required|max:150|email" data-label="Email">
                    <div class="invalid-feedback"><?= e(error('email')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="panel">Panel type <span class="required">*</span></label>
                    <select id="panel" name="panel" class="form-select<?= invalid('panel') ?>" data-rules="required" data-label="Panel type" data-panel-select<?= $isSelf ? ' disabled' : '' ?>>
                        <?php foreach (Auth::PANELS as $code => $text): ?>
                            <option value="<?= e($code) ?>"<?= $panel === $code ? ' selected' : '' ?>><?= e($text) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($isSelf): ?><input type="hidden" name="panel" value="<?= e($user['panel']) ?>"><div class="form-hint">You cannot change your own panel.</div><?php endif; ?>
                    <div class="invalid-feedback"><?= e(error('panel')) ?></div>
                </div>
                <div class="form-group" data-role-group<?= $panel === 'ROLE_USER' ? '' : ' hidden' ?>>
                    <label class="form-label" for="role_id">Role / Section <span class="required">*</span></label>
                    <select id="role_id" name="role_id" class="form-select<?= invalid('role_id') ?>" data-label="Role" <?= $panel === 'ROLE_USER' ? 'data-rules="required"' : '' ?>>
                        <option value="">Select role</option>
                        <?php $found = false; foreach ($roles as $r): $found = $found || (string) $r['role_id'] === $roleId; ?>
                            <option value="<?= (int) $r['role_id'] ?>"<?= (string) $r['role_id'] === $roleId ? ' selected' : '' ?>><?= e($r['role_name']) ?></option>
                        <?php endforeach; ?>
                        <?php if (!$found && $isEdit && $user['role_id'] !== null): ?>
                            <option value="<?= (int) $user['role_id'] ?>" selected><?= e($user['role_name']) ?> (inactive)</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-hint">The verification section this user works for.</div>
                    <div class="invalid-feedback"><?= e(error('role_id')) ?></div>
                </div>

                <?php $forceCfg = \App\Core\Config::get('security.force_password_change') === true; if (!$isEdit): ?>
                    <div class="form-group">
                        <label class="form-label" for="password">Password <span class="required">*</span></label>
                        <div class="input-icon input-password">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" class="form-control<?= invalid('password') ?>" maxlength="72" autocomplete="new-password"
                                   data-rules="required|password" data-label="Password">
                            <button type="button" class="toggle-password" data-toggle-password="password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        </div>
                        <div class="form-hint">≥ 10 chars with upper, lower, digit &amp; special character.</div>
                        <div class="invalid-feedback"><?= e(error('password')) ?></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm password <span class="required">*</span></label>
                        <div class="input-icon input-password">
                            <i class="fa-solid fa-key" aria-hidden="true"></i>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control<?= invalid('confirm_password') ?>" maxlength="72" autocomplete="new-password"
                                   data-rules="required|same:password" data-label="Confirm password">
                            <button type="button" class="toggle-password" data-toggle-password="confirm_password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                        </div>
                        <div class="invalid-feedback"><?= e(error('confirm_password')) ?></div>
                    </div>
                    <?php if ($forceCfg): ?>
                    <div class="form-group">
                        <label class="check">
                            <input type="checkbox" name="must_change_password" value="1"<?= $mustChange === '1' ? ' checked' : '' ?>>
                            <span><strong>Force password change on first login</strong><br><small class="text-muted">Recommended.</small></span>
                        </label>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="form-group">
                    <label class="check">
                        <input type="checkbox" name="is_active" value="1"<?= $active === '1' ? ' checked' : '' ?><?= $isSelf ? ' disabled checked' : '' ?>>
                        <span><strong>Active</strong><br><small class="text-muted">Inactive users cannot login.</small></span>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" data-loading-text="Saving…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span><?= $isEdit ? 'Update User' : 'Create User' ?></span></button>
                <a href="<?= e(url('/admin/users')) ?>" class="btn btn-light"><i class="fa-solid fa-xmark" aria-hidden="true"></i> <span>Cancel</span></a>
            </div>
        </form>
    </div>
</div>

<?php if ($isEdit): ?>
<div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i> Reset password</h3></div>
        <div class="card-body">
            <p class="text-muted mb-3">Set a temporary password. The user must change it on next login. This also unlocks the account.</p>
            <form method="post" action="<?= e(url('/admin/users/' . $user['user_id'] . '/reset-password')) ?>" novalidate data-validate autocomplete="off"
                  data-confirm="<?= e('Reset the password of "' . $user['name'] . '"?') ?>" data-confirm-variant="warning">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="reset_password">New temporary password <span class="required">*</span></label>
                    <div class="input-icon input-password">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <input type="password" id="reset_password" name="reset_password" class="form-control<?= invalid('reset_password') ?>" maxlength="72" autocomplete="new-password"
                               data-rules="required|password" data-label="New password">
                        <button type="button" class="toggle-password" data-toggle-password="reset_password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="invalid-feedback"><?= e(error('reset_password')) ?></div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="reset_confirm">Confirm password <span class="required">*</span></label>
                    <input type="password" id="reset_confirm" name="reset_confirm" class="form-control<?= invalid('reset_confirm') ?>" maxlength="72" autocomplete="new-password"
                           data-rules="required|same:reset_password" data-label="Confirm new password">
                    <div class="invalid-feedback"><?= e(error('reset_confirm')) ?></div>
                </div>
                <button type="submit" class="btn btn-warning" data-loading-text="Resetting…"><i class="fa-solid fa-rotate" aria-hidden="true"></i> <span>Reset password</span></button>
            </form>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><h3 class="card-title"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Account info</h3></div>
        <div class="card-body">
            <dl class="detail-list">
                <dt>Status</dt><dd><?= active_badge($user['is_active']) ?></dd>
                <dt>Last login</dt><dd><?= e(fmt_datetime($user['last_login_at'])) ?> <?= $user['last_login_ip'] ? '· ' . e($user['last_login_ip']) : '' ?></dd>
                <dt>Password changed</dt><dd><?= e(fmt_datetime($user['password_changed_at'])) ?></dd>
                <dt>Locked until</dt><dd><?= e(fmt_datetime($user['locked_until'])) ?></dd>
                <dt>Created</dt><dd><?= e(fmt_datetime($user['created_at'])) ?></dd>
            </dl>
        </div>
    </div>
</div>
<?php endif; ?>
</div>
