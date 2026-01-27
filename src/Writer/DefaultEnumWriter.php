<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;

/**
 * Default implementation of EnumWriter that generates TypeScript enums or types.
 */
class DefaultEnumWriter implements EnumWriter, Transformer
{
    public function __construct(
        private bool $transformToNativeEnums = true,
    ) {}

    public function writeEnum(EnumIR $enum): string
    {
        if ($this->transformToNativeEnums) {
            return $this->writeNativeEnum($enum);
        }

        return $this->writeTypeAlias($enum);
    }

    /**
     * Generate a native TypeScript enum.
     */
    private function writeNativeEnum(EnumIR $enum): string
    {
        $cases = [];
        foreach ($enum->cases as $case) {
            $name = $case['name'];
            $value = $case['value'] ?? null;
            if ($value !== null) {
                if (is_string($value)) {
                    $cases[] = "  {$name} = '{$value}',";
                } else {
                    $cases[] = "  {$name} = {$value},";
                }
            } else {
                $cases[] = "  {$name},";
            }
        }

        $casesStr = implode("\n", $cases);
        return "export enum {$enum->name} {\n{$casesStr}\n}";
    }

    /**
     * Generate a TypeScript type alias (union of string literals).
     */
    private function writeTypeAlias(EnumIR $enum): string
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
