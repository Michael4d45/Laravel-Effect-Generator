<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class PropertyIR
{
    public function __construct(
        public string $name,
        public TypeIR $type,
        public bool $nullable = false,
        public bool $optional = false,
    ) {}

    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type->toArray(),
        ];

        if ($this->nullable) {
            $result['nullable'] = true;
        }

        if ($this->optional) {
            $result['optional'] = true;
        }

        return $result;
    }
}
