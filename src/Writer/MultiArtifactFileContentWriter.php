<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * FileContentWriter that supports multiple writers per IR type,
 * allowing generation of multiple artifacts (types, schemas, interfaces) from the same IR.
 */
class MultiArtifactFileContentWriter implements FileContentWriter
{
    private array $schemaWriters;
    private array $enumWriters;

    /**
     * @param list<Transformer> $transformers All available transformers
     * @param ImportWriter $importWriter Writer for imports
     */
    public function __construct(
        private array $transformers,
        private ImportWriter $importWriter,
    ) {
        $this->schemaWriters = array_filter(
            $transformers,
            fn($t) => $t instanceof SchemaWriter,
        );
        $this->enumWriters = array_filter(
            $transformers,
            fn($t) => $t instanceof EnumWriter,
        );

        // Provide defaults if no writers found
        if (empty($this->schemaWriters)) {
            $this->schemaWriters = [
                new DefaultSchemaWriter(
                    new DefaultPropertyWriter(new TypeScriptWriter(
                        $transformers,
                        WriterContext::INTERFACE,
                    )),
                    $transformers,
                ),
                new DefaultSchemaWriter(
                    new DefaultPropertyWriter(new TypeScriptWriter(
                        $transformers,
                        WriterContext::ENCODED_INTERFACE,
                    )),
                    $transformers,
                    suffix: 'Encoded',
                    context: WriterContext::ENCODED_INTERFACE,
                ),
                new EffectSchemaSchemaWriter($transformers),
            ];
        }
        if (empty($this->enumWriters)) {
            $this->enumWriters = [
                new TypeEnumWriter,
                new EffectSchemaEnumWriter,
                new ConstObjectEnumWriter,
            ];
        }
    }

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

        // Generate schemas using all schema writers
        foreach ($namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                foreach ($this->schemaWriters as $schemaWriter) {
                    $schemaContent = $schemaWriter->writeSchema(
                        $schema,
                        $filePath,
                        $localSchemas,
                        $localEnums,
                        $imports,
                    );
                    $exports[] = $schemaContent;
                }
            }
        }

        // Generate enums using all enum writers
        foreach ($namespaces as $namespace) {
            foreach ($namespace->enums as $enum) {
                foreach ($this->enumWriters as $enumWriter) {
                    $enumContent = $enumWriter->writeEnum($enum);
                    $exports[] = $enumContent;
                }
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
