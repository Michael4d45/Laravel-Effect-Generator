<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

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

/**
 * Utility for transforming TypeIR objects to TypeScript and Effect Schema strings.
 * Provides reusable logic for all plugins to avoid duplication.
 */
class TypeTransformer
{
    /**
     * Transform a TypeIR to TypeScript representation (for INTERFACE/ENCODED_INTERFACE contexts).
     */
    public static function toTypeScript(
        TypeIR $type,
        bool $readonly = true,
        null|callable $recurse = null,
    ): string {
        return self::transformTypeParam(
            $type,
            WriterContext::INTERFACE,
            $readonly,
            $recurse,
        );
    }

    /**
     * Transform a TypeIR to Effect Schema representation (for SCHEMA context).
     */
    public static function toEffectSchema(
        TypeIR $type,
        null|callable $recurse = null,
    ): string {
        return self::transformTypeParam(
            $type,
            WriterContext::SCHEMA,
            true,
            $recurse,
        );
    }

    /**
     * Transform a TypeIR based on the WriterContext.
     */
    public static function transform(
        TypeIR $type,
        WriterContext $context,
        bool $readonly = true,
        null|callable $recurse = null,
    ): string {
        return self::transformTypeParam($type, $context, $readonly, $recurse);
    }

    /**
     * Core transformation logic - converts any TypeIR to appropriate string representation.
     */
    private static function transformTypeParam(
        TypeIR $type,
        WriterContext $context,
        bool $readonly = true,
        null|callable $recurse = null,
    ): string {
        $recurse ??= fn(TypeIR $t) => self::transformTypeParam(
            $t,
            $context,
            $readonly,
            $recurse,
        );

        if ($type instanceof ClassReferenceTypeIR) {
            if ($context === WriterContext::SCHEMA) {
                if ($type->isEnum) {
                    return "{$type->alias}Schema";
                }

                return "S.suspend((): S.Schema<{$type->alias}, {$type->alias}Encoded> => {$type->alias}Schema)";
            }

            if (
                $context === WriterContext::ENCODED_INTERFACE
                && !$type->isEnum
            ) {
                return "{$type->alias}Encoded";
            }

            return $type->alias;
        }

        if ($type instanceof StringTypeIR) {
            return match ($context) {
                WriterContext::INTERFACE,
                WriterContext::ENCODED_INTERFACE,
                    => 'string',
                WriterContext::SCHEMA => 'S.String',
                WriterContext::ENUM => 'string',
            };
        }

        if ($type instanceof IntTypeIR) {
            return match ($context) {
                WriterContext::INTERFACE,
                WriterContext::ENCODED_INTERFACE,
                    => 'number',
                WriterContext::SCHEMA => 'S.Number',
                WriterContext::ENUM => 'number',
            };
        }

        if ($type instanceof FloatTypeIR) {
            return match ($context) {
                WriterContext::INTERFACE,
                WriterContext::ENCODED_INTERFACE,
                    => 'number',
                WriterContext::SCHEMA => 'S.Number',
                WriterContext::ENUM => 'number',
            };
        }

        if ($type instanceof BoolTypeIR) {
            return match ($context) {
                WriterContext::INTERFACE,
                WriterContext::ENCODED_INTERFACE,
                    => 'boolean',
                WriterContext::SCHEMA => 'S.Boolean',
                WriterContext::ENUM => 'boolean',
            };
        }

        if ($type instanceof ArrayTypeIR) {
            if ($type->itemType !== null) {
                $itemType = $recurse($type->itemType);
                $prefix = $readonly
                && (
                    $context === WriterContext::INTERFACE
                    || $context === WriterContext::ENCODED_INTERFACE
                    || $context === WriterContext::ENUM
                )
                    ? 'readonly '
                    : '';

                if ($context === WriterContext::SCHEMA) {
                    return "S.Array({$itemType})";
                }

                return "{$prefix}{$itemType}[]";
            }

            return $context === WriterContext::SCHEMA
                ? 'S.Array(S.Unknown)'
                : 'readonly unknown[]';
        }

        if ($type instanceof NullableTypeIR) {
            $innerType = $recurse($type->innerType);
            if ($context === WriterContext::SCHEMA) {
                return "S.NullOr({$innerType})";
            }

            return "{$innerType} | null";
        }

        if ($type instanceof RecordTypeIR) {
            $keyType = $recurse($type->keyType);
            $valueType = $recurse($type->valueType);

            if ($context === WriterContext::SCHEMA) {
                return "S.Record({ key: {$keyType}, value: {$valueType} })";
            }

            return "Record<{$keyType}, {$valueType}>";
        }

        if ($type instanceof StructTypeIR) {
            $properties = [];
            foreach ($type->properties as $property) {
                $propType = $recurse($property->type);
                $name = $property->name;
                $optional = $property->optional ? '?' : '';

                if (str_contains($propType, "\n")) {
                    $propType = str_replace("\n", "\n  ", $propType);
                }

                if ($context === WriterContext::SCHEMA) {
                    $properties[] = "{$name}: {$propType}";
                } else {
                    $prefix = $readonly ? 'readonly ' : '';
                    $properties[] = "{$prefix}{$name}{$optional}: {$propType};";
                }
            }

            if ($context === WriterContext::SCHEMA) {
                return (
                    "S.Struct({\n"
                    . implode(",\n", array_map(fn($p) => "  {$p}", $properties))
                    . "\n})"
                );
            }

            return (
                "{\n"
                . implode("\n", array_map(fn($p) => "  {$p}", $properties))
                . "\n}"
            );
        }

        if ($type instanceof UnionTypeIR) {
            $unionTypes = [];
            $hasNull = false;

            foreach ($type->types as $unionMember) {
                $effectiveType = $unionMember;
                if ($unionMember instanceof NullableTypeIR) {
                    $hasNull = true;
                    $effectiveType = $unionMember->innerType;
                }

                $transformed = $recurse($effectiveType);

                // If the transformed type itself contains null, track it
                if (
                    str_contains($transformed, ' | null')
                    || str_contains($transformed, 'null |')
                    || str_contains($transformed, 'S.Null')
                    || str_contains($transformed, 'Null')
                ) {
                    $hasNull = true;
                }

                // Avoid duplicate types in union
                if (!in_array($transformed, $unionTypes, true)) {
                    $unionTypes[] = $transformed;
                }
            }

            if ($context === WriterContext::SCHEMA) {
                $unionStr = count($unionTypes) > 1
                    ? 'S.Union(' . implode(', ', $unionTypes) . ')'
                    : $unionTypes[0] ?? 'S.Unknown';

                if (
                    $hasNull
                    && !str_contains($unionStr, 'S.Null')
                    && !str_contains($unionStr, 'NullOr')
                ) {
                    return "S.NullOr({$unionStr})";
                }

                return $unionStr;
            }

            $unionStr = implode(' | ', $unionTypes);
            if ($hasNull && !str_contains($unionStr, 'null')) {
                return "{$unionStr} | null";
            }

            return $unionStr;
        }

        return $context === WriterContext::SCHEMA ? 'S.Unknown' : 'unknown';
    }
}
