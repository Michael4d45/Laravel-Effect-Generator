<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\AttributeIR;
use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

class SnakeCaseAttributePlugin implements Transformer
{
    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        if (!$input instanceof PropertyIR) {
            return false;
        }

        return $this->hasSnakeCaseAttribute($input, $attributes);
    }

    public function transform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if ($input instanceof PropertyIR) {
            $input->name = $this->toSnakeCase($input->name);
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

    private function toSnakeCase(string $value): string
    {
        $result = preg_replace('/([a-z\d])([A-Z])/', '$1_$2', $value);
        $result = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $result);

        return strtolower($result);
    }

    private function hasSnakeCaseAttribute(
        PropertyIR $property,
        array $attributes = [],
    ): bool {
        $propertyAttributes = $attributes['property'] ?? $property->attributes;
        $classAttributes = $attributes['class'] ?? [];

        return (
            $this->containsSnakeCaseAttribute($propertyAttributes)
            || $this->containsSnakeCaseAttribute($classAttributes)
        );
    }

    /**
     * @param list<AttributeIR> $attributes
     */
    private function containsSnakeCaseAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if (
                $attribute->name
                === 'EffectSchemaGenerator\\Attributes\\SnakeCase'
            ) {
                return true;
            }
        }

        return false;
    }
}
