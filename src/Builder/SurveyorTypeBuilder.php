<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Builder;

use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\Contracts\Type;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\NumberType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;

class SurveyorTypeBuilder
{
    public function buildType(Type $type): TypeIR
    {
        $irType = $this->buildBaseType($type);

        if ($type->isNullable()) {
            return new \EffectSchemaGenerator\IR\Types\NullableTypeIR($irType);
        }

        return $irType;
    }

    private function buildBaseType(Type $type): TypeIR
    {
        // Handle union types
        if ($type instanceof UnionType) {
            $types = [];
            foreach ($type->types as $unionType) {
                // If the union contains NullType, we skip it here as it's handled by buildType's isNullable check
                if ($unionType instanceof \Laravel\Surveyor\Types\NullType) {
                    continue;
                }
                $types[] = $this->buildType($unionType);
            }

            if (count($types) === 1) {
                return $types[0];
            }

            return new UnionTypeIR($types);
        }

        // Handle array types
        if ($type instanceof ArrayType) {
            $valueType = $type->valueType();
            // If value type is a union with multiple types, we'll use the union
            // Otherwise, use the single value type
            $itemType = $this->buildType($valueType);
            return new ArrayTypeIR($itemType);
        }

        // Handle primitive types
        if ($type instanceof StringType) {
            return new StringTypeIR;
        }

        if ($type instanceof IntType) {
            return new IntTypeIR;
        }

        if ($type instanceof FloatType || $type instanceof NumberType) {
            return new FloatTypeIR;
        }

        if ($type instanceof BoolType) {
            return new BoolTypeIR;
        }

        // Handle class types
        if ($type instanceof ClassType) {
            $fqcn = $type->resolved();
            $isEnum = enum_exists($fqcn);
            return new ClassReferenceTypeIR($fqcn, '', [], $isEnum);
        }

        // Unknown type
        return new UnknownTypeIR;
    }
}
