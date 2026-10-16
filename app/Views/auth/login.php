<?php
use App\Core\Config;
?>
<div class="auth-card anim-fade-up">
    <section class="auth-side" aria-hidden="true">
        <img src="<?= e(asset('img/kda-logo.svg')) ?>" alt="" class="auth-logo" width="72" height="72">
        <h2><?= e(Config::get('app.name')) ?></h2>
        <p><?= e(Config::get('app.full_name')) ?></p>
        <ul class="auth-points">
            <li><i class="fa-solid fa-layer-group"></i> Single source of truth for vacant property</li>
            <li><i class="fa-solid fa-users-gear"></i> Multi-section verification rounds</li>
            <li><i class="fa-solid fa-gavel"></i> Auction-readiness at a glance</li>
            <li><i class="fa-solid fa-fingerprint"></i> Complete audit trail</li>
        </ul>
        <span class="auth-org"><?= e(Config::get('app.org')) ?></span>
    </section>

    <section class="auth-form">
        <div class="auth-form-head">
            <img src="<?= e(asset('img/kda-logo.svg')) ?>" alt="" class="auth-logo-sm" width="44" height="44">
            <div>
                <h1>Sign in</h1>
                <p class="text-muted">Use your PRAMAN username and password.</p>
            </div>
        </div>

        <?= partial('partials/flash') ?>

        <form method="post" action="<?= e(url('/login')) ?>" novalidate data-validate autocomplete="off">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="username">Username <span class="required">*</span></label>
                <div class="input-icon">
                    <i class="fa-solid fa-user" aria-hidden="true"></i>
                    <input type="text" id="username" name="username" class="form-control<?= invalid('username') ?>"
                           value="<?= e(old('username')) ?>" maxlength="50" autocomplete="username" autofocus
                           data-rules="required|max:50|alpha_dash" data-label="Username">
                </div>
                <div class="invalid-feedback"><?= e(error('username')) ?></div>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password <span class="required">*</span></label>
                <div class="input-icon input-password">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" class="form-control<?= invalid('password') ?>"
                           maxlength="72" autocomplete="current-password" data-rules="required" data-label="Password">
                    <button type="button" class="toggle-password" data-toggle-password="password" aria-label="Show password">
                        <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="invalid-feedback"><?= e(error('password')) ?></div>
            </div>

            <button type="submit" class="btn btn-primary btn-block btn-lg" data-loading-text="Signing in…">
                <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> <span>Sign in</span>
            </button>
        </form>

        <p class="auth-note">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            Authorised KDA personnel only. All activity is logged with IP address.
        </p>
    </section>
</div>
