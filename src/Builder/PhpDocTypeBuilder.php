<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Builder;

use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\StructTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeItemNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class PhpDocTypeBuilder
{
    public function buildType(TypeNode $typeNode, array $uses): TypeIR
    {
        // Handle nullable types
        if ($typeNode instanceof NullableTypeNode) {
            return new NullableTypeIR($this->buildType($typeNode->type, $uses));
        }

        // Handle union types
        if ($typeNode instanceof UnionTypeNode) {
            $types = [];
            $hasNull = false;
            foreach ($typeNode->types as $unionType) {
                // Check for null in union types
                if (
                    $unionType instanceof IdentifierTypeNode
                    && $unionType->name === 'null'
                ) {
                    $hasNull = true;
                    continue;
                }
                $types[] = $this->buildType($unionType, $uses);
            }

            $unionType = count($types) > 1
                ? new UnionTypeIR($types)
                : $types[0] ?? new UnknownTypeIR;

            // If null was in the union, wrap in NullableTypeIR
            if ($hasNull) {
                return new NullableTypeIR($unionType);
            }

            return $unionType;
        }

        // Handle array shapes (e.g., array{name: string, value: int})
        if ($typeNode instanceof ArrayShapeNode) {
            return $this->buildStructTypeFromArrayShape($typeNode, $uses);
        }

        // Handle arrays
        if ($typeNode instanceof ArrayTypeNode) {
            $itemType = $typeNode->type !== null
                ? $this->buildType($typeNode->type, $uses)
                : null;
            return new ArrayTypeIR($itemType);
        }

        // Handle generic types (e.g., array<string, Type>, array<Type>, Collection<Type>)
        if ($typeNode instanceof GenericTypeNode) {
            // Check if it's array<K, V> - should become RecordTypeIR
            if (
                $typeNode->type instanceof IdentifierTypeNode
                && $typeNode->type->name === 'array'
                && count($typeNode->genericTypes) === 2
            ) {
                $keyType = $this->buildType($typeNode->genericTypes[0], $uses);

                // If value type is an ArrayShapeNode, wrap the resulting StructTypeIR in ArrayTypeIR
                $valueTypeNode = $typeNode->genericTypes[1];
                if ($valueTypeNode instanceof ArrayShapeNode) {
                    $structType = $this->buildStructTypeFromArrayShape(
                        $valueTypeNode,
                        $uses,
                    );
                    $valueType = new ArrayTypeIR($structType);
                } else {
                    $valueType = $this->buildType($valueTypeNode, $uses);
                }

                return new RecordTypeIR($keyType, $valueType);
            }

            // Check if it's array<T> (single parameter) - should become ArrayTypeIR
            if (
                $typeNode->type instanceof IdentifierTypeNode
                && $typeNode->type->name === 'array'
                && count($typeNode->genericTypes) === 1
            ) {
                $itemType = $this->buildType($typeNode->genericTypes[0], $uses);
                return new ArrayTypeIR($itemType);
            }

            // For other generics (e.g., Collection<Type>, DataCollection<Type>), create GenericTypeIR
            $baseTypeName = $typeNode->type instanceof IdentifierTypeNode
                ? $typeNode->type->name
                : 'unknown';

            $typeParameters = [];
            foreach ($typeNode->genericTypes as $genericType) {
                $typeParameters[] = $this->buildType($genericType, $uses);
            }

            $fqcn = $baseTypeName;
            foreach ($uses as $alias => $use) {
                $parts = explode('\\', $alias);
                $className = end($parts);
                if ($className === $baseTypeName) {
                    $fqcn = $use;
                    break;
                }
            }
            return new ClassReferenceTypeIR(
                $fqcn,
                $baseTypeName,
                $typeParameters,
            );
        }

        // Handle identifier types (primitives, class names, etc.)
        if ($typeNode instanceof IdentifierTypeNode) {
            return $this->buildIdentifierType($typeNode->name, $uses);
        }

        // Unknown type
        return new UnknownTypeIR;
    }

    private function buildStructTypeFromArrayShape(
        ArrayShapeNode $arrayShape,
        array $uses,
    ): StructTypeIR {
        $properties = [];
        foreach ($arrayShape->items as $item) {
            if (!$item instanceof ArrayShapeItemNode) {
                continue;
            }

            $keyName = $this->getArrayShapeKeyName($item);
            $valueType = $this->buildType($item->valueType, $uses);
            $properties[] = new PropertyIR(
                $keyName,
                $valueType,
                false,
                $item->optional,
            );
        }
        return new StructTypeIR($properties);
    }

    private function getArrayShapeKeyName(ArrayShapeItemNode $item): string
    {
        // Use keyName if available (IdentifierTypeNode)
        if (
            $item->keyName !== null
            && $item->keyName instanceof IdentifierTypeNode
        ) {
            return $item->keyName->name;
        }

        // Fallback: try to get from key property if available
        // Using pattern from tests: $item->keyName ?? ($item->key->value ?? '')
        /** @phpstan-ignore-next-line */
        $key = $item->key ?? null;
        if ($key !== null) {
            /** @phpstan-ignore-next-line */
            return (string) ($key->value ?? (is_string($key) ? $key : ''));
        }

        return '';
    }

    private function buildIdentifierType(string $name, array $uses): TypeIR
    {
        return match (strtolower($name)) {
            'string' => new StringTypeIR,
            'int', 'integer' => new IntTypeIR,
            'float', 'double' => new FloatTypeIR,
            'bool', 'boolean' => new BoolTypeIR,
            'array' => new ArrayTypeIR,
            'mixed' => new UnknownTypeIR,
            'array-key' => new UnionTypeIR([new StringTypeIR, new IntTypeIR]),
            default => $this->buildClassReferenceType($name, $uses),
        };
    }

    private function buildClassReferenceType(
        string $name,
        array $uses,
    ): ClassReferenceTypeIR {
        foreach ($uses as $alias => $use) {
            $parts = explode('\\', $alias);
            $className = end($parts);
            if ($className === $name) {
                return new ClassReferenceTypeIR($use, $name);
            }
        }
        return new ClassReferenceTypeIR($name);
    }
}
