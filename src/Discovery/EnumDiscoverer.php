<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;

/**
 * Contract for discovering enum class names.
 */
interface EnumDiscoverer
{
    /**
     * @return Collection<string>
     */
    public function discover(): Collection;
}
