<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

class LazyPlugin implements Transformer
{
    public function canTransform($input, WriterContext $context): bool
    {
        if ($input instanceof PropertyIR) {
            // Handle property preprocessing
            return $this->containsLazyAtTopLevel($input->type);
        }

        return $input instanceof TypeIR && $this->handles($input);
    }

    public function transform($input, WriterContext $context): string
    {
        if ($input instanceof PropertyIR) {
            // Mark property as optional if it contains Lazy
            if ($this->containsLazyAtTopLevel($input->type)) {
                $input->optional = true;
            }

            // Also remove Lazy from the type structure
            $input->type = $this->removeLazyFromType($input->type);

            return ''; // Property preprocessing doesn't return a string
        }

        if (!$input instanceof TypeIR) {
            return 'unknown';
        }

        return $this->transformType($input, $context);
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

    private function handles(TypeIR $type): bool
    {
        // Handle standalone Lazy type
        if ($type instanceof ClassReferenceTypeIR) {
            return $type->fqcn === 'Spatie\LaravelData\Lazy';
        }

        // Handle union types that contain Lazy (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                if (
                    !(
                        $unionType instanceof ClassReferenceTypeIR
                        && $unionType->fqcn === 'Spatie\LaravelData\Lazy'
                    )
                ) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }

    private function transformType(TypeIR $type, WriterContext $context): string
    {
        // Handle standalone Lazy type
        if (
            $type instanceof ClassReferenceTypeIR
            && $type->fqcn === 'Spatie\LaravelData\Lazy'
        ) {
            // If Lazy has type parameters, unwrap them
            if (count($type->typeParameters) > 0) {
                return $this->transformTypeParam(
                    $type->typeParameters[0],
                    $context,
                );
            }
            // If no type parameters, return unknown
            return 'unknown';
        }

        // Handle union types that contain Lazy (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            $typesWithoutLazy = [];
            foreach ($type->types as $unionType) {
                // Skip Lazy types
                if (
                    $unionType instanceof ClassReferenceTypeIR
                    && $unionType->fqcn === 'Spatie\LaravelData\Lazy'
                ) {
                    continue;
                }
                $typesWithoutLazy[] = $this->transformTypeParam(
                    $unionType,
                    $context,
                );
            }

            if (empty($typesWithoutLazy)) {
                return 'unknown';
            }

            return implode(' | ', $typesWithoutLazy);
        }

        return 'unknown';
    }

    private function transformTypeParam(
        TypeIR $typeParam,
        WriterContext $context,
    ): string {
        return \EffectSchemaGenerator\Writer\TypeTransformer::transform(
            $typeParam,
            $context,
        );
    }

    /**
     * Check if a union type contains Lazy.
     */
    private function containsLazyInUnion(UnionTypeIR $type): bool
    {
        foreach ($type->types as $unionType) {
            if (
                !(
                    $unionType instanceof ClassReferenceTypeIR
                    && $unionType->fqcn === 'Spatie\LaravelData\Lazy'
                )
            ) {
                continue;
            }

            return true;
        }
        return false;
    }

    /**
     * Remove Lazy from a union type, returning a new UnionTypeIR or single TypeIR.
     */
    private function removeLazyFromUnion(UnionTypeIR $type): TypeIR
    {
        $typesWithoutLazy = [];
        foreach ($type->types as $unionType) {
            $processed = $this->removeLazyFromType($unionType);
            if (
                $processed instanceof UnknownTypeIR
                && !$unionType instanceof UnknownTypeIR
            ) {
                // If the type became unknown (because it was Lazy), skip it
                continue;
            }

            $typesWithoutLazy[] = $processed;
        }

        if (count($typesWithoutLazy) === 0) {
            // All types were Lazy, return unknown
            return new UnknownTypeIR;
        }

        if (count($typesWithoutLazy) === 1) {
            // Only one type remains, return it directly
            return $typesWithoutLazy[0];
        }

        // Multiple types remain, return as union
        return new UnionTypeIR($typesWithoutLazy);
    }

    /**
     * Recursively remove Lazy from a type structure.
     */
    private function removeLazyFromType(TypeIR $type): TypeIR
    {
        if (
            $type instanceof ClassReferenceTypeIR
            && $type->fqcn === 'Spatie\LaravelData\Lazy'
        ) {
            return new UnknownTypeIR;
        }

        if ($type instanceof NullableTypeIR) {
            $inner = $this->removeLazyFromType($type->innerType);
            return new NullableTypeIR($inner);
        }

        if ($type instanceof UnionTypeIR) {
            return $this->removeLazyFromUnion($type);
        }

        // Return other types as-is
        return $type;
    }

    /**
     * Check if a type contains Lazy at the top level (not nested in generics or structs).
     * This is used to mark properties as optional.
     */
    private function containsLazyAtTopLevel(TypeIR $type): bool
    {
        // Check union types at top level (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                if (
                    !(
                        $unionType instanceof ClassReferenceTypeIR
                        && $unionType->fqcn === 'Spatie\LaravelData\Lazy'
                    )
                ) {
                    continue;
                }

                return true;
            }
            return false;
        }

        // Check standalone Lazy type at top level
        if ($type instanceof ClassReferenceTypeIR) {
            return $type->fqcn === 'Spatie\LaravelData\Lazy';
        }

        // Check nullable wrapping Lazy at top level
        if ($type instanceof NullableTypeIR) {
            return $this->containsLazyAtTopLevel($type->innerType);
        }

        // Don't check nested types (generics, structs) - those don't make the property optional
        return false;
    }
}
