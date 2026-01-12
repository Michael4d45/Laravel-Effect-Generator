<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\TypeIR;

class StructTypeIR extends TypeIR
{
    /**
     * @param list<PropertyIR> $properties
     */
    public function __construct(
        public array $properties,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'struct',
            'properties' => array_map(
                fn(PropertyIR $property) => $property->toArray(),
                $this->properties,
            ),
        ];
    }
}
