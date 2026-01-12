<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Tokens;

/**
 * Represents an enum definition.
 */
class EnumToken
{
    public string $name;

    /**
     * @param array<string, array{name: string, value: int|string|null}> $cases Enum cases with their metadata
     */
    public function __construct(
        public string $fqcn,
        public string $namespace,
        public string $backedType,
        public array $cases,
    ) {
        $parts = explode('\\', $fqcn);
        $this->name = end($parts);
    }
}
