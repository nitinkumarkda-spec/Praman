<?php
/**
 * @var int         $colspan
 * @var bool|null   $filtered  true when a search/filter produced no rows
 * @var string|null $message
 */
$filtered = $filtered ?? false;
?>
<tr class="empty-row">
    <td colspan="<?= (int) $colspan ?>">
        <div class="empty-state">
            <i class="fa-solid <?= $filtered ? 'fa-magnifying-glass' : 'fa-folder-open' ?> fa-bounce empty-icon" aria-hidden="true"></i>
            <p class="empty-title"><?= e($filtered ? 'No matching records found' : ($message ?? 'No records yet')) ?></p>
            <p class="text-muted"><?= e($filtered ? 'Try a different search term or reset the filters.' : 'Records you add will appear here.') ?></p>
        </div>
    </td>
</tr>
