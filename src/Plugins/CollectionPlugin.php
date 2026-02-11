<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\TypeTransformer;
use EffectSchemaGenerator\Writer\WriterContext;

class CollectionPlugin implements Transformer
{
    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        return $input instanceof TypeIR && $this->handles($input);
    }

    public function transform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if (
            !$input instanceof TypeIR || !$input instanceof ClassReferenceTypeIR
        ) {
            return 'unknown';
        }

        // Collection has two type parameters: array-key and T
        // We want the second one (index 1) which is the item type
        $typeParam = null;
        if (count($input->typeParameters) >= 2) {
            $typeParam = $input->typeParameters[1]; // Second parameter is the item type
        } elseif (count($input->typeParameters) === 1) {
            $typeParam = $input->typeParameters[0]; // Fallback to first if only one
        }

        if ($typeParam !== null) {
            $innerType = $this->transformTypeParam($typeParam, $context);
            return match ($context) {
                WriterContext::INTERFACE => "readonly {$innerType}[]",
                WriterContext::ENCODED_INTERFACE => "readonly {$innerType}[]",
                WriterContext::SCHEMA => "S.Array({$innerType})",
                WriterContext::ENUM => "readonly {$innerType}[]",
            };
        }

        return match ($context) {
            WriterContext::INTERFACE => 'readonly unknown[]',
            WriterContext::ENCODED_INTERFACE => 'readonly unknown[]',
            WriterContext::SCHEMA => 'S.Array(S.Unknown)',
            WriterContext::ENUM => 'readonly unknown[]',
        };
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
        return (
            $type instanceof ClassReferenceTypeIR
            && $type->fqcn === 'Illuminate\Support\Collection'
        );
    }

    private function transformTypeParam(
        TypeIR $typeParam,
        WriterContext $context,
    ): string {
        return TypeTransformer::transform($typeParam, $context);
    }
}
