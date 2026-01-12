<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\PropertyIR;

/**
 * Default implementation of PropertyWriter that generates TypeScript property lines.
 */
class DefaultPropertyWriter implements PropertyWriter
{
    public function __construct(
        private TypeScriptWriter $typeWriter,
    ) {}

    public function writeProperty(PropertyIR $property): string
    {
        $name = $property->name;
        $tsType = $this->typeWriter->writeType($property->type);

        // Use the optional flag that was set during preprocessing
        $optional = $property->optional ? '?' : '';
        $readonly = 'readonly ';

        // Handle nullable: add | null if not already present and property is nullable
        if ($property->nullable && !str_contains($tsType, 'null')) {
            $tsType .= ' | null';
        }

        if (str_contains($tsType, "\n")) {
            $tsType = str_replace("\n", "\n  ", $tsType);
        }

        return "  {$readonly}{$name}{$optional}: {$tsType};";
    }
}
