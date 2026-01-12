<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class RecordTypeIR extends TypeIR
{
    public function __construct(
        public TypeIR $keyType,
        public TypeIR $valueType,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'record',
            'keyType' => $this->keyType->toArray(),
            'valueType' => $this->valueType->toArray(),
        ];
    }
}
