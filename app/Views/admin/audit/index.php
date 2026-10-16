<?php
/** @var \App\Core\Paginator $p  @var string[] $entities */
use App\Core\Lookups;

ob_start(); ?>
<select name="entity" class="form-select" aria-label="Entity" data-auto-submit>
    <?= Lookups::options(array_combine($entities, array_map('label', $entities)) ?: [], $p->filter('entity'), 'All entities') ?>
</select>
<input type="date" name="from" class="form-control" value="<?= e($p->filter('from', '')) ?>" aria-label="From date" title="From date">
<input type="date" name="to" class="form-control" value="<?= e($p->filter('to', '')) ?>" aria-label="To date" title="To date">
<?php $filtersHtml = (string) ob_get_clean(); ?>

<div class="page-header">
    <div>
        <h2 class="page-title"><i class="fa-solid fa-fingerprint" aria-hidden="true"></i> Audit Log</h2>
        <p class="page-subtitle">Insert-only trail of every action — who, what, when and from where.</p>
    </div>
</div>

<div class="card">
    <?= partial('partials/list_toolbar', ['p' => $p, 'placeholder' => 'Search action, user, IP, value…', 'filtersHtml' => $filtersHtml]) ?>
    <div class="table-wrap">
        <table class="table table-stack">
            <thead>
            <tr>
                <?= th_sort($p, 'id', '#') ?>
                <?= th_sort($p, 'time', 'Date & time') ?>
                <?= th_sort($p, 'user', 'User') ?>
                <?= th_sort($p, 'action', 'Action') ?>
                <?= th_sort($p, 'entity', 'Entity') ?>
                <th>Details</th>
                <th>IP</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($p->items === []): ?>
                <?= partial('partials/empty_state', ['colspan' => 7, 'filtered' => $p->hasFilters(), 'message' => 'No audit entries']) ?>
            <?php else: foreach ($p->items as $a): ?>
                <tr>
                    <td data-label="#"><?= (int) $a['log_id'] ?></td>
                    <td data-label="Date & time" class="nowrap"><?= e(fmt_datetime($a['performed_at'])) ?></td>
                    <td data-label="User"><?= e($a['performed_by_name'] ?? 'System') ?><span class="cell-sub"><?= e($a['performed_by_designation'] ?? '') ?></span></td>
                    <td data-label="Action"><span class="badge badge-primary"><?= e(label($a['action'])) ?></span></td>
                    <td data-label="Entity"><?= e(label($a['entity_type'])) ?><?= $a['entity_id'] !== null ? ' #' . (int) $a['entity_id'] : '' ?>
                        <?php if ($a['property_id'] !== null): ?><span class="cell-sub">Property #<?= (int) $a['property_id'] ?></span><?php endif; ?></td>
                    <td data-label="Details">
                        <?php if ($a['old_value'] || $a['new_value']): ?>
                            <details class="audit-details">
                                <summary>View</summary>
                                <?php if ($a['old_value']): ?><p class="small text-muted mt-1">Before</p><pre class="audit-json"><?= e(json_encode(json_decode((string) $a['old_value']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
                                <?php if ($a['new_value']): ?><p class="small text-muted mt-1">After</p><pre class="audit-json"><?= e(json_encode(json_decode((string) $a['new_value']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre><?php endif; ?>
                            </details>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td data-label="IP" class="mono"><?= e($a['ip_address']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <?= partial('partials/pagination', ['p' => $p]) ?>
</div>
