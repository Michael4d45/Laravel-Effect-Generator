<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Interface for customizing import section generation.
 */
interface ImportWriter
{
    /**
     * Build the imports section.
     *
     * @param array<string, array<string, string>> $imports Import map: path => [name => alias]
     * @param string $currentFilePath Current file path
     * @return string The imports section
     */
    public function writeImports(
        array $imports,
        string $currentFilePath,
    ): string;
}
