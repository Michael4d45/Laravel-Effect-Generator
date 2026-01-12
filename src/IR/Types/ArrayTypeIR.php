<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class ArrayTypeIR extends TypeIR
{
    public function __construct(
        public null|TypeIR $itemType = null,
    ) {}

    public function toArray(): array
    {
        $result = ['type' => 'array'];
        if ($this->itemType !== null) {
            $result['itemType'] = $this->itemType->toArray();
        }
        return $result;
    }
}
