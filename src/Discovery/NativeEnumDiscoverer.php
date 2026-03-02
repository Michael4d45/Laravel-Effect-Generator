<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;

/**
 * Default discoverer for native PHP enums.
 */
class NativeEnumDiscoverer implements EnumDiscoverer
{
    /**
     * @param  array<string>  $paths
     */
    public function __construct(
        private array $paths = [],
        private null|PhpClassCandidateScanner $scanner = null,
    ) {}

    /**
     * @return Collection<string>
     */
    public function discover(): Collection
    {
        $classes = $this->scanner()->discoverClasses($this->paths);

        /** @var Collection<string> $result */
        $result = collect();
        foreach ($classes as $class) {
            try {
                if (!enum_exists($class)) {
                    continue;
                }

                $result->push($class);
            } catch (\Throwable $e) {
                // Skip classes that cannot be checked due to missing dependencies.
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
