<?php
/**
 * Search + filters + page-size toolbar for paginated tables.
 *
 * @var \App\Core\Paginator $p
 * @var string|null $placeholder
 * @var string|null $filtersHtml  extra <select>s (already escaped by the caller view)
 */
use App\Core\Config;

$allowed = (array) Config::get('pagination.allowed_per_page', [10, 25, 50]);
?>
<form method="get" action="<?= e(url(\App\Core\Request::current()->path())) ?>" class="toolbar" role="search" data-auto-filter>
    <div class="toolbar-search">
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= e($p->search) ?>" class="form-control"
               placeholder="<?= e($placeholder ?? 'Search…') ?>" aria-label="Search" maxlength="100">
    </div>

    <?php if (!empty($filtersHtml)): ?>
        <div class="toolbar-filters"><?= $filtersHtml ?></div>
    <?php endif; ?>

    <input type="hidden" name="sort" value="<?= e($p->sort) ?>">
    <input type="hidden" name="dir" value="<?= e($p->dir) ?>">

    <div class="toolbar-actions">
        <label class="per-page">
            <span>Show</span>
            <select name="per_page" class="form-select form-select-sm" data-auto-submit aria-label="Rows per page">
                <?php foreach ($allowed as $n): ?>
                    <option value="<?= (int) $n ?>"<?= $p->perPage === (int) $n ? ' selected' : '' ?>><?= (int) $n ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter" aria-hidden="true"></i> <span>Apply</span></button>
        <?php if ($p->hasFilters()): ?>
            <a href="<?= e(url(\App\Core\Request::current()->path())) ?>" class="btn btn-light btn-sm"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i> <span>Reset</span></a>
        <?php endif; ?>
    </div>
</form>
