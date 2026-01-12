<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\NamespaceIR;

/**
 * Interface for customizing full file content generation.
 */
interface FileContentWriter
{
    /**
     * Generate TypeScript content for a file containing one or more namespaces.
     *
     * @param string $filePath The relative file path
     * @param list<NamespaceIR> $namespaces The namespaces in this file
     * @return string The TypeScript file content
     */
    public function writeFileContent(
        string $filePath,
        array $namespaces,
    ): string;
}
