<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\StructTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

/**
 * Default implementation of SchemaWriter that generates TypeScript interfaces.
 */
class DefaultSchemaWriter implements SchemaWriter, Transformer
{
    private TypeScriptWriter $typeWriter;

    public function __construct(
        private PropertyWriter $propertyWriter,
        private array $transformers = [],
        private string $suffix = '',
        private WriterContext $context = WriterContext::INTERFACE,
    ) {
        $this->typeWriter = new TypeScriptWriter($transformers, $this->context);
    }

    public function writeSchema(
        SchemaIR $schema,
        string $currentFilePath,
        array $localSchemas,
        array $localEnums,
        array &$imports,
    ): string {
        $properties = [];
        $referencedTypes = [];

        foreach ($schema->properties as $property) {
            $this->collectReferencedTypes($property->type, $referencedTypes);
            $propertyStr = $this->propertyWriter->writeProperty($property);
            $properties[] = $propertyStr;
        }

        // Add imports for referenced types
        foreach ($referencedTypes as $fqcn => $info) {
            $name = $info['alias'];
            // Skip if it's already in this file
            if (isset($localSchemas[$name]) || isset($localEnums[$name])) {
                continue;
            }

            // Check if a transformer provides this type
            $transformerFilePath = $this->getTransformerFilePathForType($fqcn);
            if ($transformerFilePath !== null) {
                $relativePath = $this->calculateRelativePath(
                    $currentFilePath,
                    $transformerFilePath,
                );
                if (!isset($imports[$relativePath])) {
                    $imports[$relativePath] = [];
                }
                $imports[$relativePath][$name] = $name;
                continue;
            }

            $targetNamespace = $info['namespace'];
            $targetFilePath = $this->namespaceToFilePath($targetNamespace);
            $relativePath = $this->calculateRelativePath(
                $currentFilePath,
                $targetFilePath,
            );

            if (!isset($imports[$relativePath])) {
                $imports[$relativePath] = [];
            }
            $imports[$relativePath][$name] = $name;
        }

        $propertiesStr = implode("\n", $properties);
        return "export interface {$schema->name}{$this->suffix} {\n{$propertiesStr}\n}";
    }

    /**
     * Collect referenced class types from a TypeIR.
     *
     * @param TypeIR $type The type to analyze
     * @param array<string, array{namespace: string, alias: string, fqcn: string}> $referencedTypes Collection (by reference)
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
                )) {
                    continue;
                }

                $handled = true;
                // If the transformer provides a file, we need to add it to referenced types
                // so we can generate the import
                if ($transformer->providesFile()) {
                    $referencedTypes[$type->fqcn] = [
                        'namespace' => $type->namespace,
                        'alias' => $type->alias,
                        'fqcn' => $type->fqcn,
                    ];
                }
                break;
            }

            // If not handled by a transformer, and it's an App or EffectSchemaGenerator type, add it
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
                ];
            }

            // Always collect type parameters (they might be App types)
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
        } elseif ($type instanceof StructTypeIR) {
            foreach ($type->properties as $property) {
                $this->collectReferencedTypes(
                    $property->type,
                    $referencedTypes,
                );
            }
        }
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

        // Normalize: remove .ts extension from paths if present
        $fromDir = rtrim($fromDir, '/');
        $toDir = rtrim($toDir, '/');

        if ($fromDir === $toDir || $fromDir === '.' && $toDir === '.') {
            // Same directory
            return "./{$toFile}";
        }

        $fromParts =
            $fromDir === '.' || $fromDir === '' ? [] : explode('/', $fromDir);
        $toParts = $toDir === '.' || $toDir === '' ? [] : explode('/', $toDir);

        // Find common prefix
        $commonLength = 0;
        $minLength = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $minLength; $i++) {
            if ($fromParts[$i] === $toParts[$i]) {
                $commonLength++;
            } else {
                break;
            }
        }

        // Calculate relative path
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

        // Ensure it starts with ./ or ../
        if (
            !str_starts_with($relativePath, '.')
            && !str_starts_with($relativePath, '/')
        ) {
            $relativePath = './' . $relativePath;
        }

        return $relativePath;
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
                WriterContext::INTERFACE,
            )) {
                return $transformer->getFilePath();
            }
        }
        return null;
    }

    public function canTransform($input, WriterContext $context): bool
    {
        return $input instanceof SchemaIR && $context === $this->context;
    }

    public function transform($input, WriterContext $context): string
    {
        if ($input instanceof SchemaIR && $context === $this->context) {
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
