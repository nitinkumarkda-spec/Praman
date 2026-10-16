<?php
/** Small "Back to Admin" button in the topbar while a Super Admin is logged in as another user. */
use App\Core\Auth;

$imp = Auth::impersonator();
if ($imp === null) {
    return;
}
$user = auth_user();
?>
<form method="post" action="<?= e(url('/impersonate/stop')) ?>" class="back-admin-form">
    <?= csrf_field() ?>
    <button type="submit" class="btn-back-admin"
            title="<?= e('Logged in as ' . ($user['name'] ?? '') . ' — return to ' . $imp['name']) ?>">
        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> <span>Back to Admin</span>
    </button>
</form>
