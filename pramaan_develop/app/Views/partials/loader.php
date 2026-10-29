<?php
/**
 * App loader (css/loader.css + js/loader.js). Rendered by the server so it is
 * on screen before any script runs. Signed-in users ($hidden = true) skip it on page refresh;
 * it is still used (Praman.loader.show) when signing out or switching user.
 * Name, tagline and organisation come from App Settings.
 */
use App\Services\Settings;

$name = Settings::get('app_name');
$letters = preg_split('//u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
?>
<div class="pl-loader" id="app-loader"<?= !empty($hidden) ? ' hidden' : '' ?> role="status" aria-live="polite" aria-label="<?= e($name) ?> is loading">
    <div class="pl-gate pl-gate-l" aria-hidden="true"></div>
    <div class="pl-gate pl-gate-r" aria-hidden="true"></div>
    <div class="pl-stage">
        <div class="pl-seal" aria-hidden="true">
            <span class="pl-ring pl-ring-1"></span>
            <span class="pl-ring pl-ring-2"></span>
            <span class="pl-ring pl-ring-3"></span>
            <span class="pl-emblem"><img src="<?= e(asset('img/kda-emblem.png')) ?>" alt="" width="74" height="74"></span>
            <span class="pl-tick"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></span>
        </div>
        <h1 class="pl-name" aria-label="<?= e($name) ?>"><?php foreach ($letters as $ch): ?><span class="pl-ch" aria-hidden="true"><span><?= e($ch) ?></span></span><?php endforeach; ?></h1>
        <p class="pl-tag"><i aria-hidden="true"></i><span><?= e(Settings::get('app_tagline')) ?></span><i aria-hidden="true"></i></p>
        <p class="pl-org"><?= e(Settings::get('org_name')) ?></p>
        <div class="pl-progress" aria-hidden="true">
            <div class="pl-bar"><span data-pl-bar></span></div>
            <div class="pl-status"><span class="pl-msg" data-pl-msg>Starting secure session…</span><b data-pl-pct>0%</b></div>
        </div>
    </div>
</div>
