<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class BoolTypeIR extends TypeIR
{
    public function toArray(): array
    {
        return ['type' => 'bool'];
    }
}
