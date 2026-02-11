<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Reflection;

use EffectSchemaGenerator\Tokens\ClassToken;
use EffectSchemaGenerator\Tokens\PublicPropertyToken;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzed\PropertyResult;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Parser\DocBlockParser as SurveyorDocBlockParser;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\MixedType;

use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\UnionType;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionClass;
use ReflectionProperty;

/**
 * Parser for Spatie Data classes using Laravel Surveyor.
 */
class DataClassParser
{
    private PhpDocParser $phpDocParser;
    private Lexer $lexer;
    private array $parsedCache = [];
    private Analyzer $analyzer;
    private SurveyorDocBlockParser $surveyorDocParser;

    public function __construct()
    {
        $this->analyzer = app(Analyzer::class);
        $this->surveyorDocParser = app(SurveyorDocBlockParser::class);

        // Set up PHPStan parser for direct docblock parsing
        $config = new ParserConfig(usedAttributes: []);
        $constExprParser = new ConstExprParser($config);
        $typeParser = new TypeParser($config, $constExprParser);
        $this->lexer = new Lexer($config);
        $this->phpDocParser = new PhpDocParser(
            $config,
            $typeParser,
            $constExprParser,
        );
    }

    /**
     * Parse a Spatie Data class into a ClassToken.
     */
    public function parse(string $className): ClassToken
    {
        /** @var \Laravel\Surveyor\Analyzer\Analyzer $analyzer */
        $analyzer = $this->analyzer->analyzeClass($className);

        $classResult = $analyzer->result();

        assert(
            $classResult instanceof ClassResult,
            'Should always be a ClassResult',
        );
        // Get the analyzed scope for namespace and use statement resolution
        $scope = $analyzer->analyzed();

        assert($scope instanceof Scope, 'Should always be a Scope');

        $this->surveyorDocParser->setScope($scope);

        $properties = $this->extractPublicProperties($classResult, $className);

        
        /** @var array<string,string> $uses */
        $uses = $this->collectUses($className, $scope->uses());
        return new ClassToken(
            namespace: $classResult->namespace(),
            fqcn: $classResult->name(),
            uses: $uses,
            publicProperties: $properties,
        );
    }

    /**
     * Collect use statements from the class, its parents, and its traits.
     *
     * @param array<string,string> $classUses
     * @return array<string,string>
     */
    private function collectUses(string $className, array $classUses): array
    {
        $uses = $classUses;

        $reflection = new ReflectionClass($className);

        $parents = [];
        $current = $reflection->getParentClass();
        while ($current !== false) {
            $parents[] = $current;
            $current = $current->getParentClass();
        }

        $traits = $this->collectTraits($reflection);

        foreach (array_merge($parents, $traits) as $related) {
            $relatedUses = $this->analyzeUses($related->getName());
            if ($relatedUses !== []) {
                $uses = $uses + $relatedUses;
            }
        }

        return $uses;
    }

    /**
     * @return array<int,ReflectionClass>
     */
    private function collectTraits(ReflectionClass $reflection): array
    {
        $traits = [];
        $stack = $reflection->getTraits();

        while ($stack !== []) {
            /** @var ReflectionClass $trait */
            $trait = array_pop($stack);
            if (array_key_exists($trait->getName(), $traits)) {
                continue;
            }

            $traits[$trait->getName()] = $trait;

            foreach ($trait->getTraits() as $nestedTrait) {
                $stack[] = $nestedTrait;
            }
        }

        return array_values($traits);
    }

    /**
     * @return array<string,string>
     */
    private function analyzeUses(string $className): array
    {
        try {
            $analysis = $this->analyzer->analyzeClass($className);
            $scope = $analysis->analyzed();
            return $scope instanceof Scope ? $scope->uses() : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Extract properties from a ClassResult and its parent classes.
     *
     * @param class-string $className
     * @return array<string,PublicPropertyToken>
     */
    private function extractPublicProperties(
        ClassResult $classResult,
        string $className,
    ): array {
        $properties = [];
        $reflectionClass = new ReflectionClass($className);

        // Get all public properties from the class hierarchy
        $allPublicProperties = $this->getAllPublicProperties($reflectionClass);

        foreach ($allPublicProperties as $propertyName => $reflectionProperty) {
            // Try to find the property in the Surveyor result first
            $surveyorProperty = null;
            foreach ($classResult->publicProperties() as $prop) {
                if ($prop->name !== $propertyName) {
                    continue;
                }

                $surveyorProperty = $prop;
                break;
            }

            // If not found in current class, create a synthetic property for inherited properties
            if ($surveyorProperty === null) {
                // Try to get the native type from reflection for better type inference
                $nativeType = $reflectionProperty->getType();
                $surveyorType = new \Laravel\Surveyor\Types\StringType; // Default fallback

                if ($nativeType !== null) {
                    $surveyorType =
                        $this->convertReflectionTypeToSurveyorType($nativeType);
                }

                // Create a synthetic PropertyResult for inherited properties
                $surveyorProperty = new PropertyResult(
                    name: $propertyName,
                    type: $surveyorType,
                    visibility: 'public',
                );
            }

            $phpDocType = $this->extractPhpDocType(
                $reflectionProperty,
                $reflectionProperty->getDeclaringClass(), // Use the declaring class for PHPDoc
            );

            $propertyToken = new PublicPropertyToken(
                property: $surveyorProperty,
                phpDocType: $phpDocType,
            );

            $properties[$propertyName] = $propertyToken;
        }

        return $properties;
    }

    /**
     * Get all public properties from a class and its parents.
     *
     * @return array<string,ReflectionProperty>
     */
    private function getAllPublicProperties(ReflectionClass $reflectionClass): array
    {
        $properties = [];

        // Get properties from current class and all parents
        $currentClass = $reflectionClass;
        while ($currentClass !== false) {
            foreach ($currentClass->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                // Only include properties that are not static and not inherited from base classes we don't want
                if (
                    !(
                        !$property->isStatic()
                        && !array_key_exists($property->name, $properties)
                    )
                ) {
                    continue;
                }

                $properties[$property->name] = $property;
            }
            $currentClass = $currentClass->getParentClass();
        }

        return $properties;
    }

    /**
     * Extract PHPDoc type AST node from a ReflectionProperty.
     * Parses docblocks directly using PHPStan's parser.
     *
     * @return TypeNode|null The PHPStan AST type node, or null if not found
     */
    private function extractPhpDocType(
        ReflectionProperty $property,
        ReflectionClass $reflectionClass,
    ): null|TypeNode {
        // First, check for @var on the property itself
        $propertyDocComment = $property->getDocComment();
        if ($propertyDocComment !== false) {
            $parsedNode = $this->parseDocBlock($propertyDocComment);
            if ($parsedNode !== null) {
                $type = $this->extractVarTypeFromNode($parsedNode);
                if ($type !== null) {
                    return $type;
                }
            }
        }

        // For promoted properties, check constructor @param
        $constructor = $reflectionClass->getConstructor();
        if ($constructor !== null) {
            $constructorDocComment = $constructor->getDocComment();
            if ($constructorDocComment !== false) {
                $parsedNode = $this->parseDocBlock($constructorDocComment);
                if ($parsedNode !== null) {
                    $type = $this->extractParamTypeFromNode(
                        $parsedNode,
                        $property->name,
                    );
                    if ($type !== null) {
                        return $type;
                    }
                }
            }
        }

        // Check class docblock for @param (unusual but some codebases do this)
        $classDocComment = $reflectionClass->getDocComment();
        if ($classDocComment !== false) {
            $parsedNode = $this->parseDocBlock($classDocComment);
            if ($parsedNode !== null) {
                $type = $this->extractParamTypeFromNode(
                    $parsedNode,
                    $property->name,
                );
                if ($type !== null) {
                    return $type;
                }
            }
        }

        return null;
    }

    /**
     * Parse a docblock string into a PhpDocNode, using cache to avoid duplicate parsing.
     */
    private function parseDocBlock(string $docBlock): null|PhpDocNode
    {
        if (array_key_exists($docBlock, $this->parsedCache)) {
            return $this->parsedCache[$docBlock];
        }

        try {
            $tokens = new TokenIterator($this->lexer->tokenize($docBlock));
            $parsed = $this->phpDocParser->parse($tokens);
            $this->parsedCache[$docBlock] = $parsed;
            return $parsed;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extract @var type AST node from a parsed PhpDocNode.
     * Returns the first @var type found.
     *
     * @return TypeNode|null
     */
    private function extractVarTypeFromNode(PhpDocNode $phpDocNode): null|TypeNode
    {
        $varTagValues = $phpDocNode->getVarTagValues();

        if ($varTagValues === []) {
            return null;
        }

        // Return the first @var type (most common case)
        return $varTagValues[0]->type;
    }

    /**
     * Extract @param type AST node from a parsed PhpDocNode for a specific parameter name.
     *
     * @return TypeNode|null
     */
    private function extractParamTypeFromNode(
        PhpDocNode $phpDocNode,
        string $paramName,
    ): null|TypeNode {
        $paramTagValues = $phpDocNode->getParamTagValues();

        if ($paramTagValues === []) {
            return null;
        }

        // Find the @param tag that matches the property name
        foreach ($paramTagValues as $paramTag) {
            $tagParamName = ltrim($paramTag->parameterName, '$');
            if ($tagParamName === $paramName) {
                return $paramTag->type;
            }
        }

        return null;
    }

    /**
     * Convert a PHP ReflectionType to a Surveyor Type.
     */
    private function convertReflectionTypeToSurveyorType(\ReflectionType $reflectionType): \Laravel\Surveyor\Types\Contracts\Type
    {
        // Handle union types (PHP 8.0+)
        if ($reflectionType instanceof \ReflectionUnionType) {
            $types = [];
            $hasNull = false;

            foreach ($reflectionType->getTypes() as $type) {
                if (
                    $type instanceof \ReflectionNamedType
                    && $type->getName() === 'null'
                ) {
                    $hasNull = true;
                } else {
                    $types[] =
                        $this->convertSingleReflectionTypeToSurveyorType($type);
                }
            }

            if (count($types) === 1) {
                $innerType = $types[0];
                if ($hasNull) {
                    // Make the single type nullable
                    $innerType = $innerType->nullable();
                }
                return $innerType;
            }

            if (count($types) > 1) {
                $unionType = new UnionType($types);
                if ($hasNull) {
                    $unionType = $unionType->nullable();
                }
                return $unionType;
            }

            // Only null types? This shouldn't happen for properties
            return new \Laravel\Surveyor\Types\MixedType;
        }

        // Handle nullable types (PHP 7.1+)
        if (
            $reflectionType instanceof \ReflectionNamedType
            && $reflectionType->allowsNull()
        ) {
            $innerType =
                $this->convertSingleReflectionTypeToSurveyorType(
                    $reflectionType,
                );
            return $innerType->nullable();
        }

        return $this->convertSingleReflectionTypeToSurveyorType(
            $reflectionType,
        );
    }

    /**
     * Convert a single ReflectionType to a Surveyor Type.
     */
    private function convertSingleReflectionTypeToSurveyorType(\ReflectionType $reflectionType): \Laravel\Surveyor\Types\Contracts\Type
    {
        if (!$reflectionType instanceof \ReflectionNamedType) {
            return new StringType; // Fallback
        }

        $typeName = $reflectionType->getName();

        return match ($typeName) {
            'int' => new \Laravel\Surveyor\Types\IntType,
            'float' => new \Laravel\Surveyor\Types\FloatType,
            'string' => new \Laravel\Surveyor\Types\StringType,
            'bool' => new \Laravel\Surveyor\Types\BoolType,
            'array' => new \Laravel\Surveyor\Types\MixedType,
            default => new \Laravel\Surveyor\Types\ClassType($typeName), // For classes/enums
        };
    }
}
