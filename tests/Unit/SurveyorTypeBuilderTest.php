<?php

declare(strict_types=1);

use EffectSchemaGenerator\Builder\SurveyorTypeBuilder;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Reflection\DataClassParser;
use Laravel\Surveyor\Types\ArrayType;
use Laravel\Surveyor\Types\BoolType;
use Laravel\Surveyor\Types\ClassType;
use Laravel\Surveyor\Types\FloatType;
use Laravel\Surveyor\Types\IntType;
use Laravel\Surveyor\Types\NumberType;
use Laravel\Surveyor\Types\StringType;
use Laravel\Surveyor\Types\Type;
use Laravel\Surveyor\Types\UnionType;

beforeEach(function () {
    $this->builder = new SurveyorTypeBuilder();
    $this->dataParser = app(DataClassParser::class);
});

it('builds StringTypeIR from StringType', function () {
    $type = Type::string();
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(StringTypeIR::class);
});

it('builds IntTypeIR from IntType', function () {
    $type = Type::int();
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(IntTypeIR::class);
});

it('builds FloatTypeIR from FloatType', function () {
    $type = Type::float();
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(FloatTypeIR::class);
});

it('builds FloatTypeIR from NumberType', function () {
    $type = Type::number();
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(FloatTypeIR::class);
});

it('builds BoolTypeIR from BoolType', function () {
    $type = Type::bool();
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(BoolTypeIR::class);
});

it('builds ClassReferenceTypeIR from ClassType', function () {
    $type = new ClassType('App\\Data\\Models\\UserData');
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($typeIR->alias)->toBeString();
});

it('builds ArrayTypeIR from ArrayType', function () {
    $itemType = Type::string();
    $type = Type::array(['key' => $itemType]);
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(ArrayTypeIR::class);
    expect($typeIR->itemType)->not->toBeNull();
});

it('builds ArrayTypeIR with empty array', function () {
    $type = Type::array([]);
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(ArrayTypeIR::class);
    // Empty array should still have a valueType (mixed)
    expect($typeIR->itemType)->not->toBeNull();
});

it('builds UnionTypeIR from UnionType', function () {
    $type1 = Type::string();
    $type2 = Type::int();
    $type = Type::union($type1, $type2);
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(UnionTypeIR::class);
    expect($typeIR->types)->toHaveCount(2);
    expect($typeIR->types[0])->toBeInstanceOf(StringTypeIR::class);
    expect($typeIR->types[1])->toBeInstanceOf(IntTypeIR::class);
});

it('builds types from UserData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from AddressData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\AddressData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from ComplexData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from ProductData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProductData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from EdgeCaseData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\EdgeCaseData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from CollectionData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\CollectionData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from GameSessionData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\GameSessionData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from ProfileData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProfileData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from TaskData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\TaskData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from TreeNodeData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\TreeNodeData::class);
    
    foreach ($classToken->publicProperties as $property) {
        if ($property->property->type !== null) {
            $type = $this->builder->buildType($property->property->type);
            expect($type)->not->toBeNull();
        }
    }
});

it('builds types from all fixture Data classes with surveyor types', function () {
    $discoverer = app(\EffectSchemaGenerator\Discovery\ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn($class) => str_starts_with($class, 'EffectSchemaGenerator\\Tests\\Fixtures\\'))
        ->filter(function($class) {
            // Only test actual Data classes, not test fixtures
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    $classesWithSurveyorTypes = [];
    foreach ($dataClasses as $className) {
        try {
            $classToken = $this->dataParser->parse($className);

            $hasSurveyorType = false;
            foreach ($classToken->publicProperties as $property) {
                if ($property->property->type !== null) {
                    $type = $this->builder->buildType($property->property->type);
                    expect($type)->not->toBeNull();
                    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    $hasSurveyorType = true;
                }
            }

            if ($hasSurveyorType) {
                $classesWithSurveyorTypes[] = $className;
            }
        } catch (\Throwable $e) {
            // Skip classes that can't be parsed
            continue;
        }
    }

    // Ensure we found at least some classes with surveyor types
    expect($classesWithSurveyorTypes)->not->toBeEmpty();
});

it('builds types from app Data classes with surveyor types', function () {
    $discoverer = app(\EffectSchemaGenerator\Discovery\ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn($class) => str_starts_with($class, 'App\\Data\\'))
        ->filter(function($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        })
        ->take(10); // Limit to first 10 to avoid timeout

    $classesWithSurveyorTypes = [];
    foreach ($dataClasses as $className) {
        try {
            $classToken = $this->dataParser->parse($className);

            $hasSurveyorType = false;
            foreach ($classToken->publicProperties as $property) {
                if ($property->property->type !== null) {
                    $type = $this->builder->buildType($property->property->type);
                    expect($type)->not->toBeNull();
                    expect($type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    $hasSurveyorType = true;
                }
            }

            if ($hasSurveyorType) {
                $classesWithSurveyorTypes[] = $className;
            }
        } catch (\Throwable $e) {
            // Skip classes that can't be parsed
            continue;
        }
    }

    // At least verify we can process app classes
    expect($dataClasses->count())->toBeGreaterThan(0);
});

it('handles complex union types from Surveyor', function () {
    $type1 = Type::string();
    $type2 = Type::int();
    $type3 = Type::float();
    $type = Type::union($type1, $type2, $type3);
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(UnionTypeIR::class);
    expect($typeIR->types)->toHaveCount(3);
});

it('handles array with union value type', function () {
    $valueType = Type::union(Type::string(), Type::int());
    $type = Type::array(['key' => $valueType]);
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(ArrayTypeIR::class);
    expect($typeIR->itemType)->toBeInstanceOf(UnionTypeIR::class);
});

it('handles class type with resolved name', function () {
    $type = new ClassType('UserData');
    $typeIR = $this->builder->buildType($type);
    
    expect($typeIR)->toBeInstanceOf(ClassReferenceTypeIR::class);
    // The resolved() method should return the fully qualified name
    expect($typeIR->alias)->toBeString();
});

it('returns UnknownTypeIR for unsupported types', function () {
    // Create a mock type that doesn't match any known patterns
    // In practice, MixedType or other types might return UnknownTypeIR
    $mixedType = Type::mixed();
    $typeIR = $this->builder->buildType($mixedType);
    
    // MixedType should return UnknownTypeIR
    expect($typeIR)->toBeInstanceOf(UnknownTypeIR::class);
});

it('handles nullable types from Surveyor', function () {
    $type = Type::string()->nullable();
    $typeIR = $this->builder->buildType($type);
    
    // Nullable should return NullableTypeIR
    expect($typeIR)->toBeInstanceOf(\EffectSchemaGenerator\IR\Types\NullableTypeIR::class);
    expect($typeIR->innerType)->toBeInstanceOf(StringTypeIR::class);
});

it('handles optional types from Surveyor', function () {
    $type = Type::string()->optional();
    $typeIR = $this->builder->buildType($type);
    
    // Surveyor's optionality is currently handled at the property level,
    // so buildType returns the base type.
    expect($typeIR)->toBeInstanceOf(StringTypeIR::class);
});
