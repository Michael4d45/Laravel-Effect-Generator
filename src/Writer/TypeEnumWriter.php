<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

/**
 * Generates TypeScript type aliases for enums (e.g., export type QuestionType = "a" | "b" | "c").
 */
class TypeEnumWriter implements EnumWriter, Transformer
{
    public function writeEnum(EnumIR $enum): string
    {
        $values = [];
        foreach ($enum->cases as $case) {
            $value = $case['value'] ?? $case['name'];
            if (is_string($value)) {
                $values[] = '"' . $value . '"';
            } else {
                $values[] = (string) $value;
            }
        }

        $valuesStr = implode(' | ', $values);
        return "export type {$enum->name} = {$valuesStr};";
    }

    public function canTransform($input, WriterContext $context): bool
    {
        return $input instanceof EnumIR && $context === WriterContext::ENUM;
    }

    public function transform($input, WriterContext $context): string
    {
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
