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
     * @param  array<string>  $paths  Legacy paths fallback for backward compatibility
     * @param  list<DataClassDiscoverer>  $dataClassDiscoverers
     * @param  list<EnumDiscoverer>  $enumDiscoverers
     */
    public function __construct(
        private array $paths = [],
        private array $dataClassDiscoverers = [],
        private array $enumDiscoverers = [],
        private ?PhpClassCandidateScanner $scanner = null,
    ) {}

    /**
     * Discover all Spatie Data classes.
     *
     * @return Collection<string>
     */
    public function discoverDataClasses(): Collection
    {
        if (empty($this->dataClassDiscoverers)) {
            $legacy = new SpatieDataClassDiscoverer(
                paths: $this->paths,
                scanner: $this->scanner(),
            );

            return $legacy->discover()->unique()->values();
        }

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
        if (empty($this->enumDiscoverers)) {
            $legacy = new NativeEnumDiscoverer(
                paths: $this->paths,
                scanner: $this->scanner(),
            );

            return $legacy->discover()->unique()->values();
        }

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

    private function scanner(): PhpClassCandidateScanner
    {
        return $this->scanner ??= new PhpClassCandidateScanner;
    }
}
