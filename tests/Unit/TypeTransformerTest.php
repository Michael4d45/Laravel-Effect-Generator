<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\StructTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\Writer\TypeTransformer;
use EffectSchemaGenerator\Writer\WriterContext;

it('transforms primitives to TypeScript', function () {
    expect(TypeTransformer::toTypeScript(new StringTypeIR()))->toBe('string');
    expect(TypeTransformer::toTypeScript(new IntTypeIR()))->toBe('number');
    expect(TypeTransformer::toTypeScript(new FloatTypeIR()))->toBe('number');
    expect(TypeTransformer::toTypeScript(new BoolTypeIR()))->toBe('boolean');
});

it('transforms primitives to Effect Schema', function () {
    expect(TypeTransformer::toEffectSchema(new StringTypeIR()))->toBe('S.String');
    expect(TypeTransformer::toEffectSchema(new IntTypeIR()))->toBe('S.Number');
    expect(TypeTransformer::toEffectSchema(new FloatTypeIR()))->toBe('S.Number');
    expect(TypeTransformer::toEffectSchema(new BoolTypeIR()))->toBe('S.Boolean');
});

it('transforms ClassReferenceTypeIR', function () {
    $type = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    
    expect(TypeTransformer::toTypeScript($type))->toBe('UserData');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.suspend(() => UserDataSchema)');
});

it('transforms ArrayTypeIR', function () {
    $type = new ArrayTypeIR(new StringTypeIR());
    
    expect(TypeTransformer::toTypeScript($type))->toBe('readonly string[]');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.Array(S.String)');
    
    $unknownArray = new ArrayTypeIR();
    expect(TypeTransformer::toTypeScript($unknownArray))->toBe('readonly unknown[]');
    expect(TypeTransformer::toEffectSchema($unknownArray))->toBe('S.Array(S.Unknown)');
});

it('transforms NullableTypeIR', function () {
    $type = new NullableTypeIR(new StringTypeIR());
    
    expect(TypeTransformer::toTypeScript($type))->toBe('string | null');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.NullOr(S.String)');
});

it('transforms RecordTypeIR', function () {
    $type = new RecordTypeIR(new StringTypeIR(), new IntTypeIR());
    
    expect(TypeTransformer::toTypeScript($type))->toBe('Record<string, number>');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.Record({ key: S.String, value: S.Number })');
});

it('transforms UnionTypeIR', function () {
    $type = new UnionTypeIR([new StringTypeIR(), new IntTypeIR()]);
    
    expect(TypeTransformer::toTypeScript($type))->toBe('string | number');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.Union(S.String, S.Number)');
});

it('handles nested nullables in unions correctly (avoiding double null)', function () {
    $type = new UnionTypeIR([
        new NullableTypeIR(new StringTypeIR()),
        new NullableTypeIR(new IntTypeIR()),
    ]);
    
    expect(TypeTransformer::toTypeScript($type))->toBe('string | number | null');
    expect(TypeTransformer::toEffectSchema($type))->toBe('S.NullOr(S.Union(S.String, S.Number))');
});

it('respects readonly flag in TypeScript', function () {
    $type = new ArrayTypeIR(new StringTypeIR());
    
    expect(TypeTransformer::toTypeScript($type, true))->toBe('readonly string[]');
    expect(TypeTransformer::toTypeScript($type, false))->toBe('string[]');
});

it('works with different WriterContexts via transform', function () {
    $type = new StringTypeIR();
    
    expect(TypeTransformer::transform($type, WriterContext::INTERFACE))->toBe('string');
    expect(TypeTransformer::transform($type, WriterContext::SCHEMA))->toBe('S.String');
    expect(TypeTransformer::transform($type, WriterContext::ENUM))->toBe('string');
});

it('transforms StructTypeIR', function () {
    $type = new StructTypeIR([
        new PropertyIR('id', new IntTypeIR()),
        new PropertyIR('name', new StringTypeIR()),
    ]);

    $expectedTS = <<<'TS'
{
  readonly id: number;
  readonly name: string;
}
TS;

    $expectedSchema = <<<'TS'
S.Struct({
  id: S.Number,
  name: S.String
})
TS;

    expect(TypeTransformer::toTypeScript($type))->toBe($expectedTS);
    expect(TypeTransformer::toEffectSchema($type))->toBe($expectedSchema);
});

it('transforms StructTypeIR with optional properties and non-readonly', function () {
    $type = new StructTypeIR([
        new PropertyIR('id', new IntTypeIR(), false, true), // optional
    ]);

    $expectedTS = <<<'TS'
{
  id?: number;
}
TS;

    expect(TypeTransformer::toTypeScript($type, false))->toBe($expectedTS);
});

it('transforms nested StructTypeIR', function () {
    $innerStruct = new StructTypeIR([
        new PropertyIR('street', new StringTypeIR()),
    ]);

    $type = new StructTypeIR([
        new PropertyIR('address', $innerStruct),
    ]);

    $expectedTS = <<<'TS'
{
  readonly address: {
    readonly street: string;
  };
}
TS;

    $expectedSchema = <<<'TS'
S.Struct({
  address: S.Struct({
    street: S.String
  })
})
TS;

    expect(TypeTransformer::toTypeScript($type))->toBe($expectedTS);
    expect(TypeTransformer::toEffectSchema($type))->toBe($expectedSchema);
});
