<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class NullableTypeIR extends TypeIR
{
    public function __construct(
        public TypeIR $innerType,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'nullable',
            'innerType' => $this->innerType->toArray(),
        ];
    }
}
