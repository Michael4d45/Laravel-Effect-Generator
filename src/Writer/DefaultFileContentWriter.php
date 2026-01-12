<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Default implementation of FileContentWriter that generates TypeScript file content.
 */
class DefaultFileContentWriter implements FileContentWriter
{
    public function __construct(
        private SchemaWriter $schemaWriter,
        private EnumWriter $enumWriter,
        private ImportWriter $importWriter,
    ) {}

    public function writeFileContent(
        string $filePath,
        array $namespaces,
    ): string {
        $imports = [];
        $exports = [];

        // Collect all schemas and enums in this file
        $localSchemas = [];
        $localEnums = [];

        foreach ($namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                $localSchemas[$schema->name] = $schema;
            }
            foreach ($namespace->enums as $enum) {
                $localEnums[$enum->name] = $enum;
            }
        }

        // Generate schemas and collect imports
        foreach ($namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                $schemaContent = $this->schemaWriter->writeSchema(
                    $schema,
                    $filePath,
                    $localSchemas,
                    $localEnums,
                    $imports,
                );
                $exports[] = $schemaContent;
            }
        }

        // Generate enums
        foreach ($namespaces as $namespace) {
            foreach ($namespace->enums as $enum) {
                $enumContent = $this->enumWriter->writeEnum($enum);
                $exports[] = $enumContent;
            }
        }

        // Build imports section
        $importsSection = $this->importWriter->writeImports(
            $imports,
            $filePath,
        );

        // Add Effect import if any exports contain Effect Schema code
        $hasEffectSchemas = $this->containsEffectSchemas($exports);

        // Combine imports and exports
        $content = '';
        if ($hasEffectSchemas) {
            $content = "import { Schema as S } from 'effect';\n\n";
        }
        if (!empty($importsSection)) {
            $content .= $importsSection . "\n";
        }
        $content .= implode("\n\n", $exports);

        return $content;
    }

    /**
     * Check if any exports contain Effect Schema code.
     */
    private function containsEffectSchemas(array $exports): bool
    {
        foreach ($exports as $export) {
            if (
                !(
                    str_contains($export, 'S.')
                    || str_contains($export, 'Schema')
                )
            ) {
                continue;
            }

            return true;
        }
        return false;
    }
}
