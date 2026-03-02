<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;

/**
 * Default discoverer for classes extending Spatie Laravel Data.
 */
class SpatieDataClassDiscoverer implements DataClassDiscoverer
{
    /**
     * @param  array<string>  $paths
     */
    public function __construct(
        private array $paths = [],
        private ?PhpClassCandidateScanner $scanner = null,
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
                if (!$this->isDataClass($class)) {
                    continue;
                }

                $result->push($class);
            } catch (\Throwable $e) {
                // Skip classes that cannot be analyzed due to missing dependencies.
                continue;
            }
        }

        return $result->unique()->values();
    }

    private function scanner(): PhpClassCandidateScanner
    {
        return $this->scanner ??= new PhpClassCandidateScanner;
    }

    /**
     * Check if a class is a Spatie Data class.
     */
    private function isDataClass(string $className): bool
    {
        // Surveyor may attempt to load source again during analysis, so prefer
        // direct inheritance checks when the class is already in memory.
        if (class_exists($className, false)) {
            return is_subclass_of($className, 'Spatie\\LaravelData\\Data');
        }

        try {
            $analyzer = app(\Laravel\Surveyor\Analyzer\Analyzer::class);
            /** @var \Laravel\Surveyor\Analyzer\Analyzer $result */
            $result = $analyzer->analyzeClass($className);

            $classResult = $result->result();
            if ($classResult instanceof \Laravel\Surveyor\Analyzed\ClassResult) {
                $extends = $classResult->extends();
                assert(is_array($extends), 'Extends should be an array of strings');

                return in_array('Spatie\\LaravelData\\Data', $extends, true);
            }
        } catch (\Throwable $e) {
            // Fallback to manual inheritance checks when Surveyor fails.
        }

        if (!class_exists($className)) {
            return false;
        }

        return is_subclass_of($className, 'Spatie\\LaravelData\\Data');
    }
}
