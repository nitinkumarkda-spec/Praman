<?php
/** @var \App\Core\Paginator $p */
?>
<div class="pagination-bar">
    <div class="page-info">
        <?php if ($p->total > 0): ?>
            Showing <strong><?= fmt_num($p->from()) ?></strong>–<strong><?= fmt_num($p->to()) ?></strong>
            of <strong><?= fmt_num($p->total) ?></strong> record<?= $p->total === 1 ? '' : 's' ?>
        <?php else: ?>
            No records
        <?php endif; ?>
    </div>
    <?php if ($p->totalPages() > 1): ?>
        <nav aria-label="Pagination">
            <ul class="pagination">
                <li>
                    <?php if ($p->page > 1): ?>
                        <a class="page-link" href="<?= e($p->pageUrl($p->page - 1)) ?>" aria-label="Previous page"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
                    <?php endif; ?>
                </li>
                <?php foreach ($p->window() as $pg): ?>
                    <li>
                        <?php if ($pg === 0): ?>
                            <span class="page-link ellipsis" aria-hidden="true">…</span>
                        <?php elseif ($pg === $p->page): ?>
                            <span class="page-link active" aria-current="page"><?= (int) $pg ?></span>
                        <?php else: ?>
                            <a class="page-link" href="<?= e($p->pageUrl($pg)) ?>"><?= (int) $pg ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li>
                    <?php if ($p->page < $p->totalPages()): ?>
                        <a class="page-link" href="<?= e($p->pageUrl($p->page + 1)) ?>" aria-label="Next page"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                    <?php else: ?>
                        <span class="page-link disabled" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
