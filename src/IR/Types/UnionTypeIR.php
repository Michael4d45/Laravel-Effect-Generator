<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class UnionTypeIR extends TypeIR
{
    /**
     * @param list<TypeIR> $types
     */
    public function __construct(
        public array $types,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'union',
            'types' => array_map(
                fn(TypeIR $type) => $type->toArray(),
                $this->types,
            ),
        ];
    }
}
