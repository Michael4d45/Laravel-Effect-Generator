<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class PropertyIR
{
    /**
     * @param list<AttributeIR> $attributes
     */
    public function __construct(
        public string $name,
        public TypeIR $type,
        public bool $nullable = false,
        public bool $optional = false,
        public array $attributes = [],
        public bool $hidden = false,
    ) {}

    /**
     * Returns true if this property should be excluded from generated output.
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'type' => $this->type->toArray(),
            'attributes' => array_map(
                fn(AttributeIR $attribute) => $attribute->toArray(),
                $this->attributes,
            ),
        ];

        if ($this->nullable) {
            $result['nullable'] = true;
        }

        if ($this->optional) {
            $result['optional'] = true;
        }

        if ($this->hidden) {
            $result['hidden'] = true;
        }

        return $result;
    }
}
