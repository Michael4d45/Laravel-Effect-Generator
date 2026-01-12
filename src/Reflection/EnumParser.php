<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Reflection;

use EffectSchemaGenerator\Tokens\EnumToken;
use ReflectionClass;
use ReflectionEnum;

/**
 * Parser for PHP enums.
 */
class EnumParser
{
    /**
     * Parse a PHP enum into an EnumToken.
     *
     * @param class-string $enumName The name of the enum to parse.
     * @return EnumToken The parsed enum token.
     */
    public function parse(string $enumName): EnumToken
    {
        $reflection = new ReflectionClass($enumName);

        // Get FQCN and namespace from reflection
        $fqcn = $reflection->getName();
        $namespace = $reflection->getNamespaceName();

        // Determine if enum is backed and get the backing type
        $backedType = null;
        if ($reflection->isEnum()) {
            // Get backing type using ReflectionEnum (PHP 8.1+)
            $enumReflection = new ReflectionEnum($enumName);
            /** @var \ReflectionNamedType|null $backingType */
            $backingType = $enumReflection->getBackingType();
            if ($backingType instanceof \ReflectionNamedType) {
                $backedType = $backingType->getName();
            }
        }

        // Get enum cases from reflection
        $cases = [];
        /** @var array<string, \UnitEnum> $enumCases */
        $enumCases = $reflection->getConstants();
        foreach ($enumCases as $caseName => $caseConstant) {
            $caseValue = null;
            if ($backedType !== null && $caseConstant instanceof \BackedEnum) {
                $caseValue = $caseConstant->value;
            }

            $cases[$caseName] = [
                'name' => $caseName,
                'value' => $caseValue,
            ];
        }

        return new EnumToken(
            fqcn: $fqcn,
            namespace: $namespace,
            backedType: $backedType ?? '',
            cases: $cases,
        );
    }
}
