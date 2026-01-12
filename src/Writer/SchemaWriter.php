<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\SchemaIR;

/**
 * Interface for customizing schema (interface) generation.
 */
interface SchemaWriter
{
    /**
     * Generate TypeScript interface content for a schema.
     *
     * @param SchemaIR $schema The schema to generate
     * @param string $currentFilePath The current file path
     * @param array<string, SchemaIR> $localSchemas Schemas in the same file
     * @param array<string, EnumIR> $localEnums Enums in the same file
     * @param array<string, array<string, string>> $imports Import collection (by reference)
     * @return string The TypeScript interface content
     */
    public function writeSchema(
        SchemaIR $schema,
        string $currentFilePath,
        array $localSchemas,
        array $localEnums,
        array &$imports,
    ): string;
}
