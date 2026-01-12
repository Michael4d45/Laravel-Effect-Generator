<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Reflection;

use EffectSchemaGenerator\Tokens\ClassToken;
use EffectSchemaGenerator\Tokens\PublicPropertyToken;
use Laravel\Surveyor\Analysis\Scope;
use Laravel\Surveyor\Analyzed\ClassResult;
use Laravel\Surveyor\Analyzer\Analyzer;
use Laravel\Surveyor\Parser\DocBlockParser as SurveyorDocBlockParser;
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
        $uses = $scope->uses();
        return new ClassToken(
            namespace: $classResult->namespace(),
            fqcn: $classResult->name(),
            uses: $uses,
            publicProperties: $properties,
        );
    }

    /**
     * Extract properties from a ClassResult.
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

        foreach ($classResult->publicProperties() as $property) {
            $reflectionProperty = $reflectionClass->getProperty($property->name);

            $phpDocType = $this->extractPhpDocType(
                $reflectionProperty,
                $reflectionClass,
            );

            $propertyToken = new PublicPropertyToken(
                property: $property,
                phpDocType: $phpDocType,
            );

            $properties[$property->name] = $propertyToken;
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
        if (isset($this->parsedCache[$docBlock])) {
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
}
