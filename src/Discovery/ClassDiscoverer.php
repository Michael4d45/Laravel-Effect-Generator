<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;

/**
 * Orchestrates data class and enum discoverers.
 */
class ClassDiscoverer
{
    /**
     * @param  list<DataClassDiscoverer>  $dataClassDiscoverers
     * @param  list<EnumDiscoverer>  $enumDiscoverers
     */
    public function __construct(
        private array $dataClassDiscoverers = [],
        private array $enumDiscoverers = [],
    ) {}

    /**
     * Discover all Spatie Data classes.
     *
     * @return Collection<string>
     */
    public function discoverDataClasses(): Collection
    {
        /** @var Collection<string> $result */
        $result = collect();
        foreach ($this->dataClassDiscoverers as $discoverer) {
            try {
                $result = $result->merge($discoverer->discover());
            } catch (\Throwable $e) {
                // Keep discovery resilient if one configured plugin fails.
                continue;
            }
        }

        return $result->unique()->values();
    }

    /**
     * Discover all PHP enums.
     *
     * @return Collection<string>
     */
    public function discoverEnums(): Collection
    {
        /** @var Collection<string> $result */
        $result = collect();
        foreach ($this->enumDiscoverers as $discoverer) {
            try {
                $result = $result->merge($discoverer->discover());
            } catch (\Throwable $e) {
                // Keep discovery resilient if one configured plugin fails.
                continue;
            }
        }

        return $result->unique()->values();
    }
}
