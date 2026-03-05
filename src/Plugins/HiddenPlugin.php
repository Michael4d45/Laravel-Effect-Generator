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

/**
 * Marks properties that should be excluded from generated TypeScript interfaces
 * and schemas. Excludes properties with:
 *
 * - #[Hidden] (or class-level) — custom attribute
 * - Spatie\LaravelData\Attributes\Computed — computed properties are derived
 *   at runtime and not part of the serialized payload
 */
class HiddenPlugin implements Transformer
{
    private const HIDDEN_ATTRIBUTE = 'EffectSchemaGenerator\\Attributes\\Hidden';

    private const COMPUTED_ATTRIBUTE = 'Spatie\\LaravelData\\Attributes\\Computed';

    /** @var list<string> */
    private const EXCLUDE_ATTRIBUTES = [
        self::HIDDEN_ATTRIBUTE,
        self::COMPUTED_ATTRIBUTE,
    ];

    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        if (!$input instanceof PropertyIR) {
            return false;
        }

        return $this->shouldExcludeProperty($input, $attributes);
    }

    public function transform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if ($input instanceof PropertyIR) {
            $input->hidden = true;
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

    private function shouldExcludeProperty(PropertyIR $property, array $attributes): bool
    {
        $propertyAttributes = $attributes['property'] ?? $property->attributes;
        $classAttributes = $attributes['class'] ?? [];

        return $this->hasExcludeAttribute($propertyAttributes)
            || $this->hasExcludeAttribute($classAttributes);
    }

    /**
     * @param list<AttributeIR> $attributes
     */
    private function hasExcludeAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if (in_array($attribute->name, self::EXCLUDE_ATTRIBUTES, true)) {
                return true;
            }
        }

        return false;
    }
}
