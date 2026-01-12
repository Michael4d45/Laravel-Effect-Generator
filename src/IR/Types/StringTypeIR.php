<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class StringTypeIR extends TypeIR
{
    public function toArray(): array
    {
        return ['type' => 'string'];
    }
}
