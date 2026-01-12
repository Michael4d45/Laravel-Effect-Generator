<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\PropertyIR;

/**
 * Interface for customizing property generation.
 */
interface PropertyWriter
{
    /**
     * Generate TypeScript property line.
     *
     * @param PropertyIR $property The property to generate
     * @return string The TypeScript property line
     */
    public function writeProperty(PropertyIR $property): string;
}
