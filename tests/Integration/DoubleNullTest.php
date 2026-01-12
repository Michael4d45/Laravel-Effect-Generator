<?php

declare(strict_types=1);

use EffectSchemaGenerator\Tests\TestCase;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\Writer\MultiArtifactFileContentWriter;
use EffectSchemaGenerator\Writer\DefaultImportWriter;
use EffectSchemaGenerator\Writer\WriterContext;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Builder\AstBuilder;

uses(TestCase::class);

it('does not repeat null in union types', function () {
    // Create the problematic IR structure: Union of Nullables
    $unionType = new UnionTypeIR([
        new NullableTypeIR(new IntTypeIR()),
        new NullableTypeIR(new StringTypeIR()),
    ]);

    $property = new PropertyIR('user_id', $unionType, nullable: false);
    $schema = new SchemaIR('SessionEventOccurredData', [], [$property]);

    $namespace = new NamespaceIR('App\Data\Events');
    $namespace->schemas[] = $schema;

    // Set up the writers
    $fileContentWriter = new MultiArtifactFileContentWriter(
        [],
        new DefaultImportWriter()
    );

    $content = $fileContentWriter->writeFileContent(
        'App/Data/Events.ts',
        [$namespace]
    );

    // Check for double null in TypeScript interface
    // Currently, it will likely be "number | null | string | null"
    expect($content)->not->toContain('number | null | string | null');
    
    // Check for double null in Effect Schema
    // Currently, it will likely be "S.Union(S.NullOr(S.Number), S.NullOr(S.String))"
    expect($content)->not->toContain('S.NullOr(S.Number');
    expect($content)->not->toContain('S.NullOr(S.String');
});

it('does not repeat null for real PHPDoc types', function () {
    $classToken = app(DataClassParser::class)->parse(\EffectSchemaGenerator\Tests\Fixtures\SessionEventOccurredData::class);
    $tokens = collect([$classToken]);
    $root = app(AstBuilder::class)->build($tokens);

    $fileContentWriter = new MultiArtifactFileContentWriter(
        [],
        new DefaultImportWriter()
    );

    $namespaces = [$root->namespaces['EffectSchemaGenerator\Tests\Fixtures']];
    $content = $fileContentWriter->writeFileContent(
        'EffectSchemaGenerator/Tests/Fixtures/SessionEventOccurredData.ts',
        $namespaces
    );

    expect($content)->not->toContain('number | null | string | null');
    // Effect Schema should use S.NullOr at the top if it's a union containing null
    // or just include S.Null in the union.
});
