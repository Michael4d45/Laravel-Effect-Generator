<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR\Types;

use EffectSchemaGenerator\IR\TypeIR;

class ClassReferenceTypeIR extends TypeIR
{
    public string $namespace = '';

    public function __construct(
        public string $fqcn,
        public string $alias = '',
        public array $typeParameters = [],
        public bool $isEnum = false,
    ) {
        $parts = explode('\\', $fqcn);
        if ($alias === '') {
            $this->alias = end($parts);
        }
        if (count($parts) > 1) {
            $this->namespace = implode('\\', array_slice($parts, 0, -1));
        }
        $this->isEnum = $isEnum;
    }

    public function toArray(): array
    {
        return [
            'type' => 'class',
            'fqcn' => $this->fqcn,
            'namespace' => $this->namespace,
            'alias' => $this->alias,
            'isEnum' => $this->isEnum,
            'typeParameters' => array_map(
                fn(TypeIR $type) => $type->toArray(),
                $this->typeParameters,
            ),
        ];
    }
}
