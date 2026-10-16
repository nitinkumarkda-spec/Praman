<?php
/** @var bool $forced */
use App\Core\Config;

$min = (int) Config::get('security.password_min_length', 10);
?>
<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-key" aria-hidden="true"></i> Change Password</h2>
        <p class="page-subtitle">Keep your account secure with a strong password.</p>
    </div>
</div>

<?php if ($forced): ?>
    <div class="alert alert-warning">
        <i class="fa-solid fa-triangle-exclamation fa-beat" aria-hidden="true"></i>
        <span>You are using a temporary / default password. You must set a new password before you can use the system.</span>
    </div>
<?php endif; ?>

<div class="grid-2">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Set a new password</h3></div>
        <div class="card-body">
            <form method="post" action="<?= e(url('/change-password')) ?>" novalidate data-validate autocomplete="off">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label class="form-label" for="current_password">Current password <span class="required">*</span></label>
                    <div class="input-icon input-password">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input type="password" id="current_password" name="current_password" maxlength="72"
                               class="form-control<?= invalid('current_password') ?>" autocomplete="current-password"
                               data-rules="required" data-label="Current password">
                        <button type="button" class="toggle-password" data-toggle-password="current_password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="invalid-feedback"><?= e(error('current_password')) ?></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="new_password">New password <span class="required">*</span></label>
                    <div class="input-icon input-password">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <input type="password" id="new_password" name="new_password" maxlength="72"
                               class="form-control<?= invalid('new_password') ?>" autocomplete="new-password"
                               data-rules="required|password" data-label="New password" data-strength-meter="pw-strength">
                        <button type="button" class="toggle-password" data-toggle-password="new_password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="strength" id="pw-strength" aria-live="polite">
                        <div class="strength-bar"><span class="strength-fill"></span></div>
                        <small class="strength-text">Enter a new password</small>
                    </div>
                    <div class="invalid-feedback"><?= e(error('new_password')) ?></div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm new password <span class="required">*</span></label>
                    <div class="input-icon input-password">
                        <i class="fa-solid fa-key" aria-hidden="true"></i>
                        <input type="password" id="confirm_password" name="confirm_password" maxlength="72"
                               class="form-control<?= invalid('confirm_password') ?>" autocomplete="new-password"
                               data-rules="required|same:new_password" data-label="Confirm password">
                        <button type="button" class="toggle-password" data-toggle-password="confirm_password" aria-label="Show password"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                    </div>
                    <div class="invalid-feedback"><?= e(error('confirm_password')) ?></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" data-loading-text="Saving…">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> <span>Update password</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h3 class="card-title">Password rules</h3></div>
        <div class="card-body">
            <ul class="check-list" id="pw-rules">
                <li data-rule="length"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> At least <?= $min ?> characters</li>
                <li data-rule="upper"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> One uppercase letter (A–Z)</li>
                <li data-rule="lower"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> One lowercase letter (a–z)</li>
                <li data-rule="digit"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> One digit (0–9)</li>
                <li data-rule="special"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> One special character (e.g. @ # $ %)</li>
            </ul>
            <p class="text-muted mt-2">Your new password must differ from the current one and must not contain your username.</p>
        </div>
    </div>
</div>
