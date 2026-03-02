<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Discovery;

use Illuminate\Support\Collection;
use Symfony\Component\Finder\Finder;

/**
 * Scans PHP files and extracts fully qualified class or enum names.
 */
class PhpClassCandidateScanner
{
    /**
     * @param  array<string>  $paths
     * @return Collection<string>
     */
    public function discoverClasses(array $paths): Collection
    {
        /** @var Collection<string> $classes */
        $classes = collect();

        foreach ($paths as $path) {
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

        $namespace = null;
        $className = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
        }

        if (preg_match('/(?:class|enum)\s+(\w+)/', $content, $matches)) {
            $className = $matches[1];
        }

        if ($namespace && $className) {
            return $namespace . '\\' . $className;
        }

        return null;
    }
}
