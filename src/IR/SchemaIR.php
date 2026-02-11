<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class SchemaIR extends TypeIR
{
    /**
     * @param list<PropertyIR> $properties
     * @param list<AttributeIR> $classAttributes
     */
    public function __construct(
        public string $name,
        public array $uses = [],
        public array $properties = [],
        public array $classAttributes = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'schema',
            'name' => $this->name,
            'properties' => array_map(
                fn(PropertyIR $property) => $property->toArray(),
                $this->properties,
            ),
            'classAttributes' => array_map(
                fn(AttributeIR $attribute) => $attribute->toArray(),
                $this->classAttributes,
            ),
        ];
    }
}
