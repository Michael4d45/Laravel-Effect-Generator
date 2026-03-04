<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

/**
 * Unconditionally transforms all property names to snake_case.
 *
 * Unlike SnakeCaseAttributePlugin which requires the #[SnakeCase] attribute,
 * this plugin applies snake_case conversion to every property globally.
 * Register this in the transformers config to enable it.
 */
class SnakeCasePlugin implements Transformer
{
    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        return $input instanceof PropertyIR;
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
}
