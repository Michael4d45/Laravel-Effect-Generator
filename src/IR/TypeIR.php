<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

abstract class TypeIR
{
    /**
     * Convert the type to a JSON-serializable array.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
