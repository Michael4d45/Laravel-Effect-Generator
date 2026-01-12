<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;

/**
 * Interface for customizing enum generation.
 */
interface EnumWriter
{
    /**
     * Generate TypeScript enum content.
     *
     * @param EnumIR $enum The enum to generate
     * @return string The TypeScript enum content
     */
    public function writeEnum(EnumIR $enum): string;
}
