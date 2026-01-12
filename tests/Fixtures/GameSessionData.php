<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Tests\Fixtures;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class GameSessionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public Carbon $created_at,
        public Carbon $updated_at,
    ) {}
}
