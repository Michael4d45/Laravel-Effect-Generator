<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\IR;

class RootIR
{
    /**
     * @param array<string,NamespaceIR> $namespaces
     */
    public function __construct(
        public array $namespaces = [],
    ) {}

    public function toArray(): array
    {
        $namespacesJson = [];
        foreach ($this->namespaces as $key => $namespace) {
            $namespacesJson[$key] = $namespace->toArray();
        }

        return [
            'namespaces' => $namespacesJson,
        ];
    }
}
