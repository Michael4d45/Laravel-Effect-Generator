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
    public function __construct(
        private array $transformers = [],
    ) {}

    public function writeSchema(
        SchemaIR $schema,
        string $currentFilePath,
        array $localSchemas,
        array $localEnums,
        array &$imports,
    ): string {
        $properties = [];
        $referencedTypes = [];

        // Create a TypeScriptWriter for schema context
        $schemaWriter = new TypeScriptWriter(
            $this->transformers,
            WriterContext::SCHEMA,
        );

        foreach ($schema->properties as $property) {
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

        // Add imports for referenced schema types (we import the Schema, not the type)
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
                $relativePath = $this->calculateRelativePath(
                    $currentFilePath,
                    $transformerFilePath,
                );
                if (!array_key_exists($relativePath, $imports)) {
                    $imports[$relativePath] = [];
                }
                // Import the Schema, the base type, and the encoded type
                $imports[$relativePath]["{$name}Schema"] = "{$name}Schema";
                $imports[$relativePath][$name] = $name;

                continue;
            }

            $targetNamespace = $info['namespace'];
            $targetFilePath = $this->namespaceToFilePath($targetNamespace);
            $relativePath = $this->calculateRelativePath(
                $currentFilePath,
                $targetFilePath,
            );

            if (!array_key_exists($relativePath, $imports)) {
                $imports[$relativePath] = [];
            }
            // Import the Schema, the base type, and the encoded type
            $imports[$relativePath]["{$name}Schema"] = "{$name}Schema";
            $imports[$relativePath][$name] = $name;

            if (!($info['isEnum'] ?? false)) {
                $imports[$relativePath]["{$name}Encoded"] = "{$name}Encoded";
            }
        }

        $propertiesStr = implode(",\n", $properties);

        return "export const {$schema->name}Schema = S.Struct({\n{$propertiesStr}\n});";
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
     * Convert namespace to file path.
     */
    private function namespaceToFilePath(string $namespace): string
    {
        $parts = explode('\\', $namespace);
        $fileName = array_pop($parts);
        $path = implode('/', $parts);
        if ($path) {
            return $path . '/' . $fileName . '.ts';
        }

        return $fileName . '.ts';
    }

    /**
     * Calculate relative path from current file to target file.
     */
    private function calculateRelativePath(string $from, string $to): string
    {
        $fromDir = dirname($from);
        $toDir = dirname($to);
        $toFile = basename($to, '.ts');

        $fromDir = rtrim($fromDir, '/');
        $toDir = rtrim($toDir, '/');

        if ($fromDir === $toDir || $fromDir === '.' && $toDir === '.') {
            return "./{$toFile}";
        }

        $fromParts =
            $fromDir === '.' || $fromDir === '' ? [] : explode('/', $fromDir);
        $toParts = $toDir === '.' || $toDir === '' ? [] : explode('/', $toDir);

        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }

        $upLevels = count($fromParts) - $commonLength;
        $downPath = array_slice($toParts, $commonLength);

        $relativePath = '';
        if ($upLevels > 0) {
            $relativePath = str_repeat('../', $upLevels);
        }

        if (!empty($downPath)) {
            $relativePath .= implode('/', $downPath) . '/';
        }

        $relativePath .= $toFile;

        if (
            !str_starts_with($relativePath, '.')
            && !str_starts_with($relativePath, '/')
        ) {
            $relativePath = './' . $relativePath;
        }

        return $relativePath;
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
