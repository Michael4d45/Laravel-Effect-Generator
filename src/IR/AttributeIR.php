<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class AttributeIR
{
    /**
     * @param array<mixed> $arguments
     */
    public function __construct(
        public string $name,
        public array $arguments = [],
    ) {}

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'arguments' => $this->arguments,
        ];
    }
}