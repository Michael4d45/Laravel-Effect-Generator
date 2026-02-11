<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;

/**
 * Generates TypeScript constant objects for enums (e.g., export const CredentialType = { ApiKey: 'api_key' } as const).
 */
class ConstObjectEnumWriter implements EnumWriter, Transformer
{
    public function writeEnum(EnumIR $enum): string
    {
        $entries = [];
        foreach ($enum->cases as $case) {
            $name = $case['name'];
            $value = $case['value'] ?? $case['name'];

            if (is_string($value)) {
                $valueStr = "'" . $value . "'";
            } else {
                $valueStr = (string) $value;
            }

            $entries[] = "    {$name}: {$valueStr}";
        }

        $entriesStr = implode(",\n", $entries);
        return "export const {$enum->name} = {\n{$entriesStr}\n} as const;";
    }

    public function canTransform($input, WriterContext $context, array $attributes = []): bool
    {
        return $input instanceof EnumIR && $context === WriterContext::ENUM;
    }

    public function transform($input, WriterContext $context, array $attributes = []): string
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
