<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

class DatePlugin implements Transformer
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
        if (!$input instanceof TypeIR) {
            return 'unknown';
        }

        return match ($context) {
            WriterContext::INTERFACE => 'Date',
            WriterContext::ENCODED_INTERFACE => 'string',
            WriterContext::SCHEMA => 'S.DateFromString',
            WriterContext::ENUM => 'string',
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
        if (!$type instanceof ClassReferenceTypeIR) {
            return false;
        }

        // Handle common date/time types
        return in_array(
            $type->fqcn,
            [
                'Illuminate\Support\Carbon',
                'Carbon\Carbon',
                'DateTime',
                'DateTimeImmutable',
            ],
            true,
        );
    }
}
