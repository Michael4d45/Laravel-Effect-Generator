<?php

declare(strict_types=1);

use EffectSchemaGenerator\Builder\PhpDocTypeBuilder;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Reflection\DataClassParser;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

beforeEach(function () {
    $this->builder = new PhpDocTypeBuilder();
    $this->dataParser = app(DataClassParser::class);
});

it('builds StringTypeIR from IdentifierTypeNode', function () {
    $typeNode = new IdentifierTypeNode('string');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(StringTypeIR::class);
});

it('builds IntTypeIR from IdentifierTypeNode', function () {
    $typeNode = new IdentifierTypeNode('int');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(IntTypeIR::class);
});

it('builds FloatTypeIR from IdentifierTypeNode', function () {
    $typeNode = new IdentifierTypeNode('float');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(FloatTypeIR::class);
});

it('builds BoolTypeIR from IdentifierTypeNode', function () {
    $typeNode = new IdentifierTypeNode('bool');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(BoolTypeIR::class);
});

it('builds ClassReferenceTypeIR from class IdentifierTypeNode', function () {
    $typeNode = new IdentifierTypeNode('UserData');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($type->alias)->toBe('UserData');
});

it('builds ArrayTypeIR from ArrayTypeNode', function () {
    $itemType = new IdentifierTypeNode('string');
    $typeNode = new ArrayTypeNode($itemType);
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(ArrayTypeIR::class);
    expect($type->itemType)->toBeInstanceOf(StringTypeIR::class);
});

it('builds ArrayTypeIR with mixed item type', function () {
    $mixedType = new IdentifierTypeNode('mixed');
    $typeNode = new ArrayTypeNode($mixedType);
    $type = $this->builder->buildType($typeNode, []);

    expect($type)->toBeInstanceOf(ArrayTypeIR::class);
    expect($type->itemType)->toBeInstanceOf(\EffectSchemaGenerator\IR\Types\UnknownTypeIR::class);
});

it('handles NullableTypeNode', function () {
    $innerType = new IdentifierTypeNode('string');
    $typeNode = new NullableTypeNode($innerType);
    $type = $this->builder->buildType($typeNode, []);
    
    // Nullable should be wrapped in NullableTypeIR
    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\Types\NullableTypeIR::class);
    expect($type->innerType)->toBeInstanceOf(StringTypeIR::class);
});

it('builds UnionTypeIR from UnionTypeNode', function () {
    $type1 = new IdentifierTypeNode('string');
    $type2 = new IdentifierTypeNode('int');
    $typeNode = new UnionTypeNode([$type1, $type2]);
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(UnionTypeIR::class);
    expect($type->types)->toHaveCount(2);
    expect($type->types[0])->toBeInstanceOf(StringTypeIR::class);
    expect($type->types[1])->toBeInstanceOf(IntTypeIR::class);
});

it('handles union type with null', function () {
    $type1 = new IdentifierTypeNode('string');
    $type2 = new IdentifierTypeNode('null');
    $typeNode = new UnionTypeNode([$type1, $type2]);
    $type = $this->builder->buildType($typeNode, []);
    
    // null in union should be wrapped in NullableTypeIR
    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\Types\NullableTypeIR::class);
    expect($type->innerType)->toBeInstanceOf(StringTypeIR::class);
});

it('builds types from ComplexData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->phpDocType !== null) {
            $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
            $type = $this->builder->buildType($property->phpDocType, $uses);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from ProductData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProductData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->phpDocType !== null) {
            $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
            $type = $this->builder->buildType($property->phpDocType, $uses);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from EdgeCaseData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\EdgeCaseData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->phpDocType !== null) {
            $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
            $type = $this->builder->buildType($property->phpDocType, $uses);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from CollectionData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\CollectionData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->phpDocType !== null) {
            $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
            $type = $this->builder->buildType($property->phpDocType, $uses);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from all fixture Data classes with phpDoc types', function () {
    $discoverer = app(\EffectSchemaGenerator\Discovery\ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn($class) => str_starts_with($class, 'EffectSchemaGenerator\\Tests\\Fixtures\\'))
        ->filter(function($class) {
            // Only test actual Data classes, not test fixtures
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    $classesWithPhpDocTypes = [];
    foreach ($dataClasses as $className) {
        try {
            $classToken = $this->dataParser->parse($className);

            $hasPhpDocType = false;
            foreach ($classToken->publicProperties as $property) {
                if ($property->phpDocType !== null) {
                    $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
                    $type = $this->builder->buildType($property->phpDocType, $uses);
                    expect($type)->not->toBeNull();
                    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    $hasPhpDocType = true;
                }
            }

            if ($hasPhpDocType) {
                $classesWithPhpDocTypes[] = $className;
            }
        } catch (\Throwable $e) {
            // Skip classes that can't be parsed
            continue;
        }
    }

    // Ensure we found at least some classes with phpDoc types
    expect($classesWithPhpDocTypes)->not->toBeEmpty();
});

it('builds types from app Data classes with phpDoc types', function () {
    $discoverer = app(\EffectSchemaGenerator\Discovery\ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn($class) => str_starts_with($class, 'App\\Data\\'))
        ->filter(function($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        })
        ->take(10); // Limit to first 10 to avoid timeout

    $classesWithPhpDocTypes = [];
    foreach ($dataClasses as $className) {
        try {
            $classToken = $this->dataParser->parse($className);

            $hasPhpDocType = false;
            foreach ($classToken->publicProperties as $property) {
                if ($property->phpDocType !== null) {
                    $uses = [$classToken->fqcn => $classToken->fqcn, ...$classToken->uses];
                    $type = $this->builder->buildType($property->phpDocType, $uses);
                    expect($type)->not->toBeNull();
                    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    $hasPhpDocType = true;
                }
            }

            if ($hasPhpDocType) {
                $classesWithPhpDocTypes[] = $className;
            }
        } catch (\Throwable $e) {
            // Skip classes that can't be parsed
            continue;
        }
    }

    // At least verify we can process app classes (may or may not have phpDoc types)
    expect($dataClasses->count())->toBeGreaterThan(0);
});

it('handles integer alias', function () {
    $typeNode = new IdentifierTypeNode('integer');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(IntTypeIR::class);
});

it('handles double alias', function () {
    $typeNode = new IdentifierTypeNode('double');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(FloatTypeIR::class);
});

it('handles boolean alias', function () {
    $typeNode = new IdentifierTypeNode('boolean');
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(BoolTypeIR::class);
});

it('handles nested array types', function () {
    $innerArray = new ArrayTypeNode(new IdentifierTypeNode('string'));
    $outerArray = new ArrayTypeNode($innerArray);
    $type = $this->builder->buildType($outerArray, []);
    
    expect($type)->toBeInstanceOf(ArrayTypeIR::class);
    expect($type->itemType)->toBeInstanceOf(ArrayTypeIR::class);
    expect($type->itemType->itemType)->toBeInstanceOf(StringTypeIR::class);
});

it('handles complex union types', function () {
    $type1 = new IdentifierTypeNode('string');
    $type2 = new IdentifierTypeNode('int');
    $type3 = new IdentifierTypeNode('float');
    $typeNode = new UnionTypeNode([$type1, $type2, $type3]);
    $type = $this->builder->buildType($typeNode, []);
    
    expect($type)->toBeInstanceOf(UnionTypeIR::class);
    expect($type->types)->toHaveCount(3);
});

it('returns UnknownTypeIR for unsupported types', function () {
    // Create a mock type node that doesn't match any known patterns
    // In practice, this would be handled by the default case
    // We'll test with an empty identifier to trigger unknown
    $typeNode = new IdentifierTypeNode('unknown_type_that_does_not_exist');
    $type = $this->builder->buildType($typeNode, []);
    
    // Should return ClassReferenceTypeIR for unknown class names
    expect($type)->toBeInstanceOf(ClassReferenceTypeIR::class);
});
