<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

/**
 * Discovers Spatie Data classes and PHP enums from configured paths.
 */
class ClassDiscoverer
{
    /**
     * @param  array<string>  $paths  Paths to scan for classes
     */
    public function __construct(
        private array $paths = [],
    ) {}

    /**
     * Discover all Spatie Data classes.
     *
     * @return Collection<string>
     */
    public function discoverDataClasses(): Collection
    {
        $classes = $this->discoverClasses();
        /** @var Collection<string> $result */
        $result = collect();
        /** @var string $class */
        foreach ($classes as $class) {
            try {
                if (!$this->isDataClass($class)) {
                    continue;
                }

                $result->push($class);
            } catch (\Throwable $e) {
                // Skip classes that can't be analyzed due to missing dependencies
                continue;
            }
        }

        return $result;
    }

    /**
     * Discover all PHP enums.
     *
     * @return Collection<string>
     */
    public function discoverEnums(): Collection
    {
        $classes = $this->discoverClasses();
        /** @var Collection<string> $result */
        $result = collect();
        /** @var string $class */
        foreach ($classes as $class) {
            try {
                if (!enum_exists($class)) {
                    continue;
                }

                $result->push($class);
            } catch (\Throwable $e) {
                // Skip classes that can't be checked due to missing dependencies
                continue;
            }
        }

        return $result;
    }

    /**
     * Discover all classes in the configured paths.
     *
     * @return Collection<string>
     */
    private function discoverClasses(): Collection
    {
        /** @var Collection<string> $classes */
        $classes = collect();

        foreach ($this->paths as $path) {
            if (!is_dir($path)) {
                continue;
            }

            $finder = new Finder;
            $finder->files()->in($path)->name('*.php')->notName('*.blade.php');

            foreach ($finder as $file) {
                $class = $this->getClassFromFile($file->getPathname());
                if (is_string($class)) {
                    $classes->push($class);
                }
            }
        }

        return $classes;
    }

    /**
     * Extract fully qualified class name from a PHP file.
     */
    private function getClassFromFile(string $filePath): null|string
    {
        $content = file_get_contents($filePath);
        if (!$content) {
            return null;
        }

        // Simple regex to extract namespace and class/enum name
        // This is a basic implementation - in production, you might want to use
        // a more robust PHP parser
        $namespace = null;
        $className = null;

        // Extract namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        // Extract class or enum name
        if (preg_match('/(?:class|enum)\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
        }

        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return null;
    }

    /**
     * Check if a class is a Spatie Data class.
     */
    private function isDataClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        try {
            $analyzer = app(\Laravel\Surveyor\Analyzer\Analyzer::class);
            /** @var \Laravel\Surveyor\Analyzer\Analyzer $result */
            $result = $analyzer->analyzeClass($className);

            $classResult = $result->result();
            if (
                $classResult instanceof \Laravel\Surveyor\Analyzed\ClassResult
            ) {
                $extends = $classResult->extends();
                assert(
                    is_array($extends),
                    'Extends should be an array of strings',
                );

                return in_array('Spatie\\LaravelData\\Data', $extends, true);
            }
        } catch (\Throwable $e) {
            // Fallback to manual check if Surveyor fails
        }

        return is_subclass_of($className, 'Spatie\\LaravelData\\Data');
    }
}
