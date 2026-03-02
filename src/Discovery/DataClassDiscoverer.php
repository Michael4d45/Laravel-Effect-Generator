<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;

/**
 * Contract for discovering Spatie data class names.
 */
interface DataClassDiscoverer
{
    /**
     * @return Collection<string>
     */
    public function discover(): Collection;
}
