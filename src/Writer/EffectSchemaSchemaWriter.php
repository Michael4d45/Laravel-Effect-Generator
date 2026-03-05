<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;

/**
 * Generates Effect Schema definitions for schemas (e.g., export const CreateQuestionRequestSchema = S.Struct({...})).
 */
class EffectSchemaSchemaWriter implements SchemaWriter, Transformer
{
    private OutputPathResolver $pathResolver;

    public function __construct(
        private array $transformers = [],
    ) {
        $this->pathResolver = new OutputPathResolver;
    }

    public function writeSchema(
        SchemaIR $schema,
        string $currentFilePath,
        array $localSchemas,
        array $localEnums,
        array &$imports,
    ): string {
        $properties = [];
        /** @var array<string, array{namespace: string, alias: string, fqcn: string, isEnum?: bool}> $referencedTypes */
        $referencedTypes = [];

        // Create a TypeScriptWriter for schema context
        $schemaWriter = new TypeScriptWriter(
            $this->transformers,
            WriterContext::SCHEMA,
        );

        foreach ($schema->properties as $property) {
            if ($property->hidden) {
                continue;
            }
            $this->collectReferencedTypes($property->type, $referencedTypes);
            $schemaType = $this->typeToEffectSchema(
                $property->type,
                $localSchemas,
                $localEnums,
                $schemaWriter,
            );

            // Wrap in S.NullOr if property is nullable and not already nullable
            if ($property->nullable && !str_contains($schemaType, 'Null')) {
                $schemaType = "S.NullOr({$schemaType})";
            }

            if ($property->optional) {
                $schemaType = "S.optional({$schemaType})";
            }

            if (str_contains($schemaType, "\n")) {
                $schemaType = str_replace("\n", "\n  ", $schemaType);
            }

            $properties[] = "  {$property->name}: {$schemaType}";
        }

        // Add imports for referenced schema types.
        //
        // Two branches:
        // 1) Transformer branch: a plugin provides the type's file (e.g. LengthAwarePaginator → Illuminate/Pagination.ts).
        //    We only add Schema + type name. We must NOT add *Encoded — the plugin file does not export it.
        // 2) Non-transformer branch: type is from App\Data etc. We add Schema, type, and Encoded.
        //
        // If LengthAwarePaginatorEncoded appears in generated output, getTransformerFilePathForType()
        // returned null for that type (so we fell into branch 2). Run in your app:
        //   php artisan effect-schema:debug-imports "App\Features\Recipe\Responses\ListRecipesResponse"
        // to see configured transformers and per-type transformer path (null = wrong import will be added without safeguard).
        foreach ($referencedTypes as $fqcn => $info) {
            $name = $info['alias'];
            // Skip if it's already in this file
            if (
                array_key_exists($name, $localSchemas)
                || array_key_exists($name, $localEnums)
            ) {
                continue;
            }

            // Check if a transformer provides this type
            $transformerFilePath = $this->getTransformerFilePathForType($fqcn);
            if ($transformerFilePath !== null) {
                $relativePath = $this->pathResolver->relativeImportPath(
                    $currentFilePath,
                    $transformerFilePath,
                );
                if (!array_key_exists($relativePath, $imports)) {
                    $imports[$relativePath] = [];
                }
                // Import Schema and base type only. Do NOT add {$name}Encoded for transformer-provided
                // types (e.g. LengthAwarePaginator): the plugin file does not export an Encoded variant.
                $imports[$relativePath]["{$name}Schema"] = "{$name}Schema";
                $imports[$relativePath][$name] = $name;

                continue;
            }

            $targetFilePath = str_contains($info['fqcn'], '\\')
                ? $this->pathResolver->fqcnFilePath($info['fqcn'])
                : dirname($currentFilePath) . '/' . $info['alias'] . '.ts';
            $relativePath = $this->pathResolver->relativeImportPath(
                $currentFilePath,
                $targetFilePath,
            );

            if (!array_key_exists($relativePath, $imports)) {
                $imports[$relativePath] = [];
            }
            // Import the Schema, the base type, and the encoded type (only if the target exports it)
            $imports[$relativePath]["{$name}Schema"] = "{$name}Schema";
            $imports[$relativePath][$name] = $name;

            if (
                !($info['isEnum'] ?? false)
                && $this->referencedTypeExportsEncodedVariant($fqcn)
            ) {
                $imports[$relativePath]["{$name}Encoded"] = "{$name}Encoded";
            }
        }

        $propertiesStr = implode(",\n", $properties);

        return "export const {$schema->name}Schema: S.Schema<{$schema->name}, {$schema->name}Encoded> = S.Struct({\n{$propertiesStr}\n});";
    }

    /**
     * Convert a TypeIR to Effect Schema representation.
     */
    private function typeToEffectSchema(
        TypeIR $type,
        array $localSchemas,
        array $localEnums,
        TypeScriptWriter $schemaWriter,
    ): string {
        // Try transformers first
        foreach ($this->transformers as $transformer) {
            if (!$transformer->canTransform($type, WriterContext::SCHEMA, [])) {
                continue;
            }

            return $transformer->transform($type, WriterContext::SCHEMA, []);
        }

        // Fallback to default handling
        return $this->typeToEffectSchemaDefault(
            $type,
            $localSchemas,
            $localEnums,
            $schemaWriter,
        );
    }

    /**
     * Default type conversion logic for Effect Schema.
     */
    private function typeToEffectSchemaDefault(
        TypeIR $type,
        array $localSchemas,
        array $localEnums,
        TypeScriptWriter $schemaWriter,
    ): string {
        return TypeTransformer::toEffectSchema($type, fn(TypeIR $t) => $this->typeToEffectSchema(
            $t,
            $localSchemas,
            $localEnums,
            $schemaWriter,
        ));
    }

    /**
     * Collect referenced class types from a TypeIR.
     */
    private function collectReferencedTypes(
        TypeIR $type,
        array &$referencedTypes,
    ): void {
        if ($type instanceof ClassReferenceTypeIR) {
            // Check if a transformer handles this
            $handled = false;
            foreach ($this->transformers as $transformer) {
                if (!$transformer->canTransform(
                    $type,
                    WriterContext::INTERFACE,
                    [], // No attributes for type references
                )) {
                    continue;
                }

                $handled = true;
                if ($transformer->providesFile()) {
                    $referencedTypes[$type->fqcn] = [
                        'namespace' => $type->namespace,
                        'alias' => $type->alias,
                        'fqcn' => $type->fqcn,
                        'isEnum' => $type->isEnum,
                    ];
                }
                break;
            }

            if (
                !$handled
                && (
                    str_starts_with($type->fqcn, 'App\\')
                    || str_starts_with($type->fqcn, 'EffectSchemaGenerator\\')
                    || !str_contains($type->fqcn, '\\')
                )
            ) {
                $referencedTypes[$type->fqcn] = [
                    'namespace' => $type->namespace,
                    'alias' => $type->alias,
                    'fqcn' => $type->fqcn,
                    'isEnum' => $type->isEnum,
                ];
            }

            foreach ($type->typeParameters as $typeParam) {
                $this->collectReferencedTypes($typeParam, $referencedTypes);
            }
        } elseif ($type instanceof NullableTypeIR) {
            $this->collectReferencedTypes($type->innerType, $referencedTypes);
        } elseif ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                $this->collectReferencedTypes($unionType, $referencedTypes);
            }
        } elseif ($type instanceof ArrayTypeIR && $type->itemType !== null) {
            $this->collectReferencedTypes($type->itemType, $referencedTypes);
        } elseif ($type instanceof RecordTypeIR) {
            $this->collectReferencedTypes($type->keyType, $referencedTypes);
            $this->collectReferencedTypes($type->valueType, $referencedTypes);
        }
    }

    /**
     * Get the file path for a type if it's provided by a transformer.
     */
    private function getTransformerFilePathForType(string $fqcn): null|string
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer->providesFile()) {
                continue;
            }

            $tempType = new ClassReferenceTypeIR($fqcn);
            if ($transformer->canTransform(
                $tempType,
                WriterContext::SCHEMA,
                [],
            )) {
                return $transformer->getFilePath();
            }
        }

        return null;
    }

    /**
     * Diagnostic: for a schema, return referenced types and whether each is provided by a transformer.
     * Used by effect-schema:debug-imports to explain why an import (e.g. LengthAwarePaginatorEncoded) appears.
     *
     * @return array<int, array{fqcn: string, alias: string, transformer_path: string|null}>
     */
    public function debugImportDecisions(SchemaIR $schema): array
    {
        $referencedTypes = [];
        foreach ($schema->properties as $property) {
            if ($property->hidden) {
                continue;
            }
            $this->collectReferencedTypes($property->type, $referencedTypes);
        }
        $result = [];
        foreach ($referencedTypes as $fqcn => $info) {
            $result[] = [
                'fqcn' => $fqcn,
                'alias' => $info['alias'],
                'transformer_path' =>
                    $this->getTransformerFilePathForType($fqcn),
            ];
        }
        return $result;
    }

    /**
     * Whether the type's generated file exports an Encoded variant (e.g. UserDataEncoded).
     * Returns false for types like LengthAwarePaginator whose plugin file does not export *Encoded.
     * Used when adding imports in the non-transformer path (e.g. app omits the plugin from config).
     */
    private function referencedTypeExportsEncodedVariant(string $fqcn): bool
    {
        $typesWithoutEncodedExport = [
            'Illuminate\Pagination\LengthAwarePaginator',
        ];

        return !in_array($fqcn, $typesWithoutEncodedExport, true);
    }

    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        return $input instanceof SchemaIR && $context === WriterContext::SCHEMA;
    }

    public function transform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if ($input instanceof SchemaIR && $context === WriterContext::SCHEMA) {
            return $this->writeSchema($input, '', [], [], []);
        }

        return '';
    }

    public function providesFile(): bool
    {
        return false;
    }

    public function getFileContent(): null|string
    {
        return null;
    }

    public function getFilePath(): null|string
    {
        return null;
    }
}
