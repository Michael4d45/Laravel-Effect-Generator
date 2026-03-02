<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Resolves output file paths and relative import paths from namespaces/FQCNs.
 */
class OutputPathResolver
{
    public function schemaFilePath(
        string $namespace,
        string $schemaName,
    ): string {
        return (
            $this->namespaceDirectory($namespace)
            . '/'
            . $schemaName
            . '.ts'
        );
    }

    public function enumFilePath(string $namespace, string $enumName): string
    {
        return $this->namespaceDirectory($namespace) . '/' . $enumName . '.ts';
    }

    public function fqcnFilePath(string $fqcn): string
    {
        $parts = explode('\\', ltrim($fqcn, '\\'));
        $typeName = array_pop($parts);
        $directory = implode('/', $parts);

        if ($directory === '') {
            return $typeName . '.ts';
        }

        return $directory . '/' . $typeName . '.ts';
    }

    public function relativeImportPath(
        string $fromFilePath,
        string $toFilePath,
    ): string {
        $fromDir = dirname($fromFilePath);
        $toDir = dirname($toFilePath);
        $toFile = basename($toFilePath, '.ts');

        $fromDir = rtrim($fromDir, '/');
        $toDir = rtrim($toDir, '/');

        if ($fromDir === $toDir || $fromDir === '.' && $toDir === '.') {
            return './' . $toFile;
        }

        $fromParts =
            $fromDir === '.' || $fromDir === '' ? [] : explode('/', $fromDir);
        $toParts = $toDir === '.' || $toDir === '' ? [] : explode('/', $toDir);

        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] !== $toParts[$i]) {
                break;
            }

            $commonLength++;
        }

        $upLevels = count($fromParts) - $commonLength;
        $downPath = array_slice($toParts, $commonLength);

        $relativePath = '';
        if ($upLevels > 0) {
            $relativePath = str_repeat('../', $upLevels);
        }

        if ($downPath !== []) {
            $relativePath .= implode('/', $downPath) . '/';
        }

        $relativePath .= $toFile;

        if (!str_starts_with($relativePath, '.')) {
            $relativePath = './' . $relativePath;
        }

        return $relativePath;
    }

    private function namespaceDirectory(string $namespace): string
    {
        return str_replace('\\', '/', trim($namespace, '\\'));
    }
}
