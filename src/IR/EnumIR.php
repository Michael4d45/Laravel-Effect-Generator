<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class EnumIR extends TypeIR
{
    /**
     * @param array<string, array{name: string, value: int}|array{name: string, value: string}> $cases
     */
    public function __construct(
        public string $name,
        public array $cases,
        public string $type,
    ) {}

    public function toArray(): array
    {
        return [
            'type' => 'enum',
            'name' => $this->name,
            'cases' => $this->cases,
            'backedType' => $this->type,
        ];
    }
}
