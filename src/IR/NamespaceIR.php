<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class NamespaceIR
{
    public string $name;

    /**
     * @param array<string,SchemaIR> $schemas
     * @param array<string,EnumIR> $enums
     */
    public function __construct(
        public string $namespace,
        public array $uses = [],
        public array $schemas = [],
        public array $enums = [],
    ) {
        $parts = explode('\\', $namespace);
        $this->name = end($parts);
    }

    public function toArray(): array
    {
        $result = [
            'namespace' => $this->namespace,
            'name' => $this->name,
        ];

        if (!empty($this->uses)) {
            $result['uses'] = $this->uses;
        }

        if (!empty($this->schemas)) {
            $result['schemas'] = array_map(
                fn(SchemaIR $schema) => $schema->toArray(),
                $this->schemas,
            );
        }

        if (!empty($this->enums)) {
            $result['enums'] = array_map(
                fn(EnumIR $enum) => $enum->toArray(),
                $this->enums,
            );
        }

        return $result;
    }
}
