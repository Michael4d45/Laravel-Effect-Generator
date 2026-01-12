<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Tokens;

/**
 * Represents a class definition.
 */
class ClassToken
{
    public string $name;

    /**
     * @param array<string,string> $uses Uses statements for this class
     * @param PublicPropertyToken[] $publicProperties Properties of this class
     */
    public function __construct(
        public string $namespace,
        public string $fqcn,
        public array $uses,
        public array $publicProperties,
    ) {
        $parts = explode('\\', $fqcn);
        $this->name = end($parts);
    }
}
