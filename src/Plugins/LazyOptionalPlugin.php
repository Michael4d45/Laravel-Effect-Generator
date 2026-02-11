<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\AttributeIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

class LazyOptionalPlugin implements Transformer
{
    public function canTransform(
        $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        if ($input instanceof PropertyIR) {
            // Handle property preprocessing - check for Lazy/Optional types or Optional attributes
            return (
                $this->containsLazyAtTopLevel($input->type)
                || $this->hasOptionalAttribute($input, $attributes)
            );
        }

        return $input instanceof TypeIR && $this->handles($input);
    }

    public function transform(
        $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if ($input instanceof PropertyIR) {
            // Mark property as optional if it contains Lazy/Optional types or has Optional attribute
            if (
                $this->containsLazyAtTopLevel($input->type)
                || $this->hasOptionalAttribute($input, $attributes)
            ) {
                $input->optional = true;
            }

            // Also remove Lazy or Optional from the type structure
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
        // Handle standalone Lazy or Optional type
        if ($type instanceof ClassReferenceTypeIR) {
            return in_array(
                $type->fqcn,
                [
                    'Spatie\LaravelData\Lazy',
                    'Spatie\LaravelData\Optional',
                ],
                true,
            );
        }

        // Handle union types that contain Lazy or Optional (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                if (
                    !(
                        $unionType instanceof ClassReferenceTypeIR
                        && in_array(
                            $unionType->fqcn,
                            [
                                'Spatie\LaravelData\Lazy',
                                'Spatie\LaravelData\Optional',
                            ],
                            true,
                        )
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
        // Handle standalone Lazy or Optional type
        if (
            $type instanceof ClassReferenceTypeIR
            && in_array(
                $type->fqcn,
                [
                    'Spatie\LaravelData\Lazy',
                    'Spatie\LaravelData\Optional',
                ],
                true,
            )
        ) {
            // If Lazy/Optional has type parameters, unwrap them
            if (count($type->typeParameters) > 0) {
                return $this->transformTypeParam(
                    $type->typeParameters[0],
                    $context,
                );
            }

            // If no type parameters, return unknown
            return 'unknown';
        }

        // Handle union types that contain Lazy or Optional (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            $typesWithoutLazyOptional = [];
            foreach ($type->types as $unionType) {
                // Skip Lazy and Optional types
                if (
                    $unionType instanceof ClassReferenceTypeIR
                    && in_array(
                        $unionType->fqcn,
                        [
                            'Spatie\LaravelData\Lazy',
                            'Spatie\LaravelData\Optional',
                        ],
                        true,
                    )
                ) {
                    continue;
                }
                $typesWithoutLazyOptional[] = $this->transformTypeParam(
                    $unionType,
                    $context,
                );
            }

            if (empty($typesWithoutLazyOptional)) {
                return 'unknown';
            }

            return implode(' | ', $typesWithoutLazyOptional);
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
     * Remove Lazy or Optional from a union type, returning a new UnionTypeIR or single TypeIR.
     */
    private function removeLazyFromUnion(UnionTypeIR $type): TypeIR
    {
        $typesWithoutLazyOptional = [];
        foreach ($type->types as $unionType) {
            $processed = $this->removeLazyFromType($unionType);

            if (
                $processed instanceof UnknownTypeIR
                && !$unionType instanceof UnknownTypeIR
            ) {
                continue;
            }

            if (
                $processed
                    instanceof \EffectSchemaGenerator\IR\Types\NullableTypeIR
                && $processed->innerType instanceof UnknownTypeIR
            ) {
                continue;
            }

            $typesWithoutLazyOptional[] = $processed;
        }

        if (count($typesWithoutLazyOptional) === 0) {
            // All types were Lazy or Optional, return unknown
            return new UnknownTypeIR;
        }

        if (count($typesWithoutLazyOptional) === 1) {
            // Only one type remains, return it directly
            return $typesWithoutLazyOptional[0];
        }

        // Multiple types remain, return as union
        return new UnionTypeIR($typesWithoutLazyOptional);
    }

    /**
     * Recursively remove Lazy or Optional from a type structure.
     */
    private function removeLazyFromType(TypeIR $type): TypeIR
    {
        if (
            $type instanceof ClassReferenceTypeIR
            && in_array(
                $type->fqcn,
                [
                    'Spatie\LaravelData\Lazy',
                    'Spatie\LaravelData\Optional',
                ],
                true,
            )
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
     * Check if a type contains Lazy or Optional at the top level (not nested in generics or structs).
     * This is used to mark properties as optional.
     */
    private function containsLazyAtTopLevel(TypeIR $type): bool
    {
        // Check union types at top level (e.g., Collection|Lazy)
        if ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                // Handle nullable union members (e.g., Nullable(Optional))
                $candidate = $unionType instanceof NullableTypeIR
                    ? $unionType->innerType
                    : $unionType;

                if (
                    !(
                        $candidate instanceof ClassReferenceTypeIR
                        && in_array(
                            $candidate->fqcn,
                            [
                                'Spatie\LaravelData\Lazy',
                                'Spatie\LaravelData\Optional',
                            ],
                            true,
                        )
                    )
                ) {
                    continue;
                }

                return true;
            }

            return false;
        }

        // Check standalone Lazy or Optional type at top level
        if ($type instanceof ClassReferenceTypeIR) {
            return in_array(
                $type->fqcn,
                [
                    'Spatie\LaravelData\Lazy',
                    'Spatie\LaravelData\Optional',
                ],
                true,
            );
        }

        // Check nullable wrapping Lazy or Optional at top level
        if ($type instanceof NullableTypeIR) {
            return $this->containsLazyAtTopLevel($type->innerType);
        }

        // Don't check nested types (generics, structs) - those don't make the property optional
        return false;
    }

    /**
     * Check if a property has the Optional attribute.
     */
    private function hasOptionalAttribute(
        PropertyIR $property,
        array $attributes = [],
    ): bool {
        $propertyAttributes = $attributes['property'] ?? $property->attributes;
        $classAttributes = $attributes['class'] ?? [];

        return $this->containsOptionalAttribute($propertyAttributes)
            || $this->containsOptionalAttribute($classAttributes);
    }

    /**
     * Check if any attribute in the list is Optional.
     *
     * @param list<AttributeIR> $attributes
     */
    private function containsOptionalAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if (
                $attribute->name === 'Spatie\\LaravelData\\Attributes\\Optional'
                || $attribute->name
                    === 'EffectSchemaGenerator\\Attributes\\Optional'
            ) {
                return true;
            }
        }

        return false;
    }
}
