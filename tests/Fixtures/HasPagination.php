<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Tests\Fixtures;

trait HasPagination
{
    public null|int $per_page = 15;
    /** @var null|array<string> */
    public null|array $columns = ['*'];
    public null|string $page_name = 'page';
    public null|int $page = null;
    public null|int $total = null;
    public null|SortDirection $sort_direction = null;
    public null|string $sort_by = null;
}
