<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Server-side pagination + search + whitelisted sort for every table.
 *
 * Usage:
 *   $p = Paginator::fromRequest($request, ['name' => 'u.name', 'created' => 'u.created_at'], 'created', 'desc', ['status']);
 *   $model->list($p);           // model calls $this->paginate($p, ...)
 *   view: partials/list_toolbar + table + partials/pagination
 */
final class Paginator
{
    public int $page;
    public int $total = 0;
    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /**
     * @param array<string, string> $sortable  public key => SQL expression (whitelist)
     * @param array<string, string> $filters   extra query params kept across page links
     */
    private function __construct(
        int $page,
        public readonly int $perPage,
        public readonly string $search,
        public readonly string $sort,
        public readonly string $dir,
        private readonly array $sortable,
        public readonly array $filters,
        private readonly string $path,
    ) {
        $this->page = max(1, $page);
    }

    /**
     * @param array<string, string> $sortable
     * @param string[] $filterKeys query keys to preserve (e.g. ['scheme_id','status'])
     */
    public static function fromRequest(Request $r, array $sortable, string $defaultSort, string $defaultDir = 'desc', array $filterKeys = []): self
    {
        $allowed = (array) Config::get('pagination.allowed_per_page', [10, 25, 50]);
        $perPage = $r->queryInt('per_page', (int) Config::get('pagination.default_per_page', 10));
        if (!in_array($perPage, $allowed, true)) {
            $perPage = (int) Config::get('pagination.default_per_page', 10);
        }
        $sort = (string) $r->query('sort', $defaultSort);
        if (!array_key_exists($sort, $sortable)) {
            $sort = $defaultSort;
        }
        $dir = strtolower((string) $r->query('dir', $defaultDir)) === 'asc' ? 'asc' : 'desc';
        $search = mb_substr((string) $r->query('q', ''), 0, 100);

        $filters = [];
        foreach ($filterKeys as $key) {
            $v = $r->query($key);
            if ($v !== null && $v !== '') {
                $filters[$key] = mb_substr($v, 0, 50);
            }
        }
        return new self($r->queryInt('page', 1), $perPage, $search, $sort, $dir, $sortable, $filters, $r->path());
    }

    public function filter(string $key, ?string $default = null): ?string
    {
        return $this->filters[$key] ?? $default;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
        $last = $this->totalPages();
        if ($this->page > $last) {
            $this->page = $last;
        }
    }

    public function setItems(array $items): void
    {
        $this->items = $items;
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** Safe ORDER BY clause — only whitelisted expressions. Adds a stable tiebreaker. */
    public function orderBy(): string
    {
        $expr = $this->sortable[$this->sort];
        $first = $this->sortable[array_key_first($this->sortable)];
        $tiebreak = $first !== $expr ? ', ' . $first . ' DESC' : '';
        return $expr . ' ' . strtoupper($this->dir) . $tiebreak;
    }

    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->offset() + $this->perPage);
    }

    /** Current query params (for hidden inputs / links). */
    public function params(array $override = []): array
    {
        $params = $this->filters + [
            'q'        => $this->search,
            'sort'     => $this->sort,
            'dir'      => $this->dir,
            'per_page' => (string) $this->perPage,
            'page'     => (string) $this->page,
        ];
        $params = array_merge($params, $override);
        return array_filter($params, static fn ($v) => $v !== '' && $v !== null);
    }

    public function url(array $override = []): string
    {
        return url($this->path, $this->params($override));
    }

    public function pageUrl(int $page): string
    {
        return $this->url(['page' => (string) $page]);
    }

    public function sortUrl(string $key): string
    {
        $dir = ($this->sort === $key && $this->dir === 'asc') ? 'desc' : 'asc';
        return $this->url(['sort' => $key, 'dir' => $dir, 'page' => '1']);
    }

    public function sortIcon(string $key): string
    {
        if ($this->sort !== $key) {
            return 'fa-sort';
        }
        return $this->dir === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
    }

    /** Compact page window: 1 … 4 5 [6] 7 8 … 20   (0 = ellipsis) */
    public function window(int $around = 2): array
    {
        $last = $this->totalPages();
        $pages = [1];
        for ($i = max(2, $this->page - $around); $i <= min($last - 1, $this->page + $around); $i++) {
            $pages[] = $i;
        }
        if ($last > 1) {
            $pages[] = $last;
        }
        $out = [];
        $prev = 0;
        foreach ($pages as $pg) {
            if ($pg - $prev > 1) {
                $out[] = 0;
            }
            $out[] = $pg;
            $prev = $pg;
        }
        return $out;
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->filters !== [];
    }
}
