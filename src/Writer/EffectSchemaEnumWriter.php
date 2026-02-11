<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;

/**
 * Generates Effect Schema definitions for enums (e.g., export const QuestionTypeSchema = S.Union(...)).
 */
class EffectSchemaEnumWriter implements EnumWriter, Transformer
{
    public function writeEnum(EnumIR $enum): string
    {
        $literals = [];
        foreach ($enum->cases as $case) {
            $value = $case['value'] ?? $case['name'];
            if (is_string($value)) {
                $literals[] = 'S.Literal("' . $value . '")';
            } else {
                $literals[] = 'S.Literal(' . $value . ')';
            }
        }

        $literalsStr = implode(', ', $literals);
        return "export const {$enum->name}Schema = S.Union({$literalsStr});";
    }

    public function canTransform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): bool {
        return $input instanceof EnumIR && $context === WriterContext::ENUM;
    }

    public function transform(
        TypeIR|SchemaIR|EnumIR|PropertyIR $input,
        WriterContext $context,
        array $attributes = [],
    ): string {
        if ($input instanceof EnumIR && $context === WriterContext::ENUM) {
            return $this->writeEnum($input);
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
