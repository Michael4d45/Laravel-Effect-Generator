<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;

it('StringTypeIR toArray returns correct structure', function () {
    $type = new StringTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'string']);
});

it('IntTypeIR toArray returns correct structure', function () {
    $type = new IntTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'int']);
});

it('FloatTypeIR toArray returns correct structure', function () {
    $type = new FloatTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'float']);
});

it('BoolTypeIR toArray returns correct structure', function () {
    $type = new BoolTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'bool']);
});

it('ClassReferenceTypeIR toArray returns correct structure', function () {
    $type = new ClassReferenceTypeIR('App\Models\User');
    $result = $type->toArray();

    expect($result)->toBe([
        'type' => 'class',
        'fqcn' => 'App\Models\User',
        'namespace' => 'App\Models',
        'alias' => 'User',
        'isEnum' => false,
        'typeParameters' => [],
    ]);
});

it('ClassReferenceTypeIR toArray returns true for enums', function () {
    $type = new ClassReferenceTypeIR('App\Enums\Status');
    $type->isEnum = true;
    $result = $type->toArray();

    expect($result)->toBe([
        'type' => 'class',
        'fqcn' => 'App\Enums\Status',
        'namespace' => 'App\Enums',
        'alias' => 'Status',
        'isEnum' => true,
        'typeParameters' => [],
    ]);
});

it('UnknownTypeIR toArray returns correct structure', function () {
    $type = new UnknownTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'unknown']);
});

it('ArrayTypeIR toArray returns correct structure without item type', function () {
    $type = new ArrayTypeIR;
    $result = $type->toArray();

    expect($result)->toBe(['type' => 'array']);
});

it('ArrayTypeIR toArray returns correct structure with item type', function () {
    $itemType = new StringTypeIR;
    $type = new ArrayTypeIR($itemType);
    $result = $type->toArray();

    expect($result)->toBe([
        'type' => 'array',
        'itemType' => ['type' => 'string'],
    ]);
});

it('UnionTypeIR toArray returns correct structure', function () {
    $type1 = new StringTypeIR;
    $type2 = new IntTypeIR;
    $type = new UnionTypeIR([$type1, $type2]);
    $result = $type->toArray();

    expect($result)->toBe([
        'type' => 'union',
        'types' => [
            ['type' => 'string'],
            ['type' => 'int'],
        ],
    ]);
});

it('UnionTypeIR toArray handles complex nested types', function () {
    $stringType = new StringTypeIR;
    $arrayType = new ArrayTypeIR($stringType);
    $classType = new ClassReferenceTypeIR('App\Models\User');

    $type = new UnionTypeIR([$stringType, $arrayType, $classType]);
    $result = $type->toArray();

    expect($result)->toBe([
        'type' => 'union',
        'types' => [
            ['type' => 'string'],
            [
                'type' => 'array',
                'itemType' => ['type' => 'string'],
            ],
            [
                'type' => 'class',
                'fqcn' => 'App\Models\User',
                'namespace' => 'App\Models',
                'alias' => 'User',
                'isEnum' => false,
                'typeParameters' => [],
            ],
        ],
    ]);
});

it('ArrayTypeIR constructor accepts null itemType', function () {
    $type = new ArrayTypeIR(null);

    expect($type->itemType)->toBeNull();
    expect($type->toArray())->toBe(['type' => 'array']);
});

it('ArrayTypeIR constructor accepts TypeIR itemType', function () {
    $itemType = new StringTypeIR;
    $type = new ArrayTypeIR($itemType);

    expect($type->itemType)->toBeInstanceOf(StringTypeIR::class);
});

it('UnionTypeIR constructor accepts array of types', function () {
    $types = [new StringTypeIR, new IntTypeIR];
    $type = new UnionTypeIR($types);

    expect($type->types)->toHaveCount(2);
    expect($type->types[0])->toBeInstanceOf(StringTypeIR::class);
    expect($type->types[1])->toBeInstanceOf(IntTypeIR::class);
});

it('ClassReferenceTypeIR constructor accepts class name', function () {
    $type = new ClassReferenceTypeIR('TestClass');

    expect($type->alias)->toBe('TestClass');
});

it('all TypeIR classes implement toArray method', function () {
    $types = [
        new StringTypeIR,
        new IntTypeIR,
        new FloatTypeIR,
        new BoolTypeIR,
        new UnknownTypeIR,
        new ClassReferenceTypeIR('Test'),
        new ArrayTypeIR,
        new ArrayTypeIR(new StringTypeIR),
        new UnionTypeIR([new StringTypeIR]),
    ];

    foreach ($types as $type) {
        expect(method_exists($type, 'toArray'))->toBeTrue();
        expect(is_array($type->toArray()))->toBeTrue();
        expect($type->toArray())->toHaveKey('type');
    }
});

it('TypeIR toArray methods return valid JSON structures', function () {
    $types = [
        new StringTypeIR,
        new IntTypeIR,
        new FloatTypeIR,
        new BoolTypeIR,
        new UnknownTypeIR,
        new ClassReferenceTypeIR('Test'),
        new ArrayTypeIR,
        new UnionTypeIR([new StringTypeIR, new IntTypeIR]),
    ];

    foreach ($types as $type) {
        $result = $type->toArray();
        expect(json_encode($result))->not->toBeFalse();
    }
});
