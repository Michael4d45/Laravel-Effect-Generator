<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Builder;

use EffectSchemaGenerator\IR\AttributeIR;
use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\StructTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Tokens\ClassToken;
use EffectSchemaGenerator\Tokens\EnumToken;
use EffectSchemaGenerator\Tokens\PublicPropertyToken;
use Illuminate\Support\Collection;

class AstBuilder
{
    private PhpDocTypeBuilder $phpDocTypeBuilder;

    private SurveyorTypeBuilder $surveyorTypeBuilder;

    public function __construct()
    {
        $this->phpDocTypeBuilder = new PhpDocTypeBuilder;
        $this->surveyorTypeBuilder = new SurveyorTypeBuilder;
    }

    /**
     * @param  Collection<array-key,mixed>  $tokens
     */
    public function build(Collection $tokens): RootIR
    {
        $root = new RootIR;
        $classes = [];
        $enums = [];
        $enumFqcns = [];
        foreach ($tokens as $token) {
            if ($token instanceof ClassToken) {
                $classes[] = $token;
            } elseif ($token instanceof EnumToken) {
                $enums[] = $token;
                $enumFqcns[] = $token->fqcn;
            } else {
                throw new \Exception(
                    'Invalid token type: ' . get_class($token),
                );
            }

            if (!array_key_exists($token->namespace, $root->namespaces)) {
                $namespace = new NamespaceIR($token->namespace);
            } else {
                $namespace = $root->namespaces[$token->namespace];
            }

            $root->namespaces[$token->namespace] = $namespace;
        }

        foreach ($enums as $enum) {
            $root->namespaces[$enum->namespace]->enums[] = new EnumIR(
                $enum->name,
                $enum->cases,
                $enum->backedType,
            );
        }
        foreach ($classes as $class) {
            $properties = [];
            foreach ($class->publicProperties as $property) {
                $type = $this->buildType($property, $class->uses);

                $nullable = false;
                // If it's a NullableTypeIR at the top level, set nullable flag.
                // We keep the NullableTypeIR in the type tree as well for consistency.
                if ($type instanceof NullableTypeIR) {
                    $nullable = true;
                }

                $properties[] = new PropertyIR(
                    $property->property->name,
                    $type,
                    $nullable,
                    false, // optional - will be set by plugins later
                    $this->convertAttributes($property->attributes),
                );
            }
            $root->namespaces[$class->namespace]->schemas[] = new SchemaIR(
                $class->name,
                $class->uses,
                $properties,
                $this->convertAttributes($class->attributes),
            );
        }

        foreach ($root->namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                foreach ($schema->properties as $property) {
                    $this->markEnums($property->type, $enumFqcns);
                }
            }
        }

        return $root;
    }

    private function markEnums(TypeIR $type, array $enumFqcns): void
    {
        if ($type instanceof ClassReferenceTypeIR) {
            if (in_array(ltrim($type->fqcn, '\\'), $enumFqcns, true)) {
                $type->isEnum = true;
            }
            foreach ($type->typeParameters as $param) {
                $this->markEnums($param, $enumFqcns);
            }
        } elseif ($type instanceof ArrayTypeIR && $type->itemType) {
            $this->markEnums($type->itemType, $enumFqcns);
        } elseif ($type instanceof NullableTypeIR) {
            $this->markEnums($type->innerType, $enumFqcns);
        } elseif ($type instanceof RecordTypeIR) {
            $this->markEnums($type->keyType, $enumFqcns);
            $this->markEnums($type->valueType, $enumFqcns);
        } elseif ($type instanceof UnionTypeIR) {
            foreach ($type->types as $unionType) {
                $this->markEnums($unionType, $enumFqcns);
            }
        } elseif ($type instanceof StructTypeIR) {
            foreach ($type->properties as $structProperty) {
                $this->markEnums($structProperty->type, $enumFqcns);
            }
        }
    }

    private function buildType(
        PublicPropertyToken $property,
        array $uses,
    ): TypeIR {
        if ($property->phpDocType !== null) {
            return $this->phpDocTypeBuilder->buildType(
                $property->phpDocType,
                $uses,
            );
        } elseif ($property->property->type !== null) {
            return $this->surveyorTypeBuilder->buildType($property->property->type);
        }

        return new UnknownTypeIR;
    }

    /**
     * @param \ReflectionAttribute[] $reflectionAttributes
     * @return AttributeIR[]
     */
    private function convertAttributes(array $reflectionAttributes): array
    {
        $attributes = [];
        foreach ($reflectionAttributes as $reflectionAttribute) {
            $attributes[] = new AttributeIR(
                $reflectionAttribute->getName(),
                $reflectionAttribute->getArguments(),
            );
        }
        return $attributes;
    }
}
