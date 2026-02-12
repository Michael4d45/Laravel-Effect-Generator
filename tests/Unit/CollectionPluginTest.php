<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\Plugins\CollectionPlugin;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

beforeEach(function () {
    $this->plugin = new CollectionPlugin();
});

it('can transform Collection type', function () {
    $type = new ClassReferenceTypeIR('Illuminate\Support\Collection');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('cannot transform non-Collection types', function () {
    $type = new ClassReferenceTypeIR('App\Data\UserData');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeFalse();
});

it('transforms Collection with one type parameter in interface context', function () {
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly UserData[]');
});

it('transforms Collection with two type parameters in interface context', function () {
    $keyType = new StringTypeIR();
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$keyType, $itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    // Should use the second parameter (index 1) which is the item type
    expect($result)->toBe('readonly UserData[]');
});

it('transforms Collection with primitive type parameter in interface context', function () {
    $keyType = new StringTypeIR();
    $itemType = new StringTypeIR();
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$keyType, $itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly string[]');
});

it('transforms Collection with int type parameter in interface context', function () {
    $keyType = new StringTypeIR();
    $itemType = new IntTypeIR();
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$keyType, $itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly number[]');
});

it('transforms Collection with primitive type parameter in schema context', function () {
    $keyType = new StringTypeIR();
    $itemType = new StringTypeIR();
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$keyType, $itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::SCHEMA);

    expect($result)->toBe('S.Array(S.String)');
});

it('transforms Collection with class reference in schema context', function () {
    $keyType = new StringTypeIR();
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [$keyType, $itemType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::SCHEMA);

    expect($result)->toBe('S.Array(S.suspend(() => UserDataSchema))');
});

it('transforms Collection without type parameters in interface context', function () {
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        []
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly unknown[]');
});

it('implements Transformer interface', function () {
    expect($this->plugin)->toBeInstanceOf(Transformer::class);
});

it('transforms Collection to exact TypeScript output in interface context', function () {
    $userType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [new StringTypeIR(), $userType]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly UserData[]');
});

it('transforms Collection with primitive to exact TypeScript output in interface context', function () {
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        [new StringTypeIR(), new StringTypeIR()]
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly string[]');
});

it('transforms Collection without params to exact TypeScript output in interface context', function () {
    $collectionType = new ClassReferenceTypeIR(
        'Illuminate\Support\Collection',
        'Collection',
        []
    );

    $result = $this->plugin->transform($collectionType, WriterContext::INTERFACE);

    expect($result)->toBe('readonly unknown[]');
});
