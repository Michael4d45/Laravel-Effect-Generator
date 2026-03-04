<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\Plugins\SnakeCasePlugin;
use EffectSchemaGenerator\Writer\FileWriter;
use EffectSchemaGenerator\Writer\WriterContext;

beforeEach(function () {
    $this->plugin = new SnakeCasePlugin();
});

it('can transform any PropertyIR', function () {
    $property = new PropertyIR('firstName', new StringTypeIR());

    expect($this->plugin->canTransform($property, WriterContext::INTERFACE))->toBeTrue();
});

it('cannot transform TypeIR inputs', function () {
    $type = new StringTypeIR();

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeFalse();
});

it('transforms camelCase property name to snake_case', function () {
    $property = new PropertyIR('firstName', new StringTypeIR());

    $this->plugin->transform($property, WriterContext::INTERFACE);

    expect($property->name)->toBe('first_name');
});

it('transforms multi-word camelCase to snake_case', function () {
    $property = new PropertyIR('myLongPropertyName', new StringTypeIR());

    $this->plugin->transform($property, WriterContext::INTERFACE);

    expect($property->name)->toBe('my_long_property_name');
});

it('handles already snake_case names', function () {
    $property = new PropertyIR('already_snake', new StringTypeIR());

    $this->plugin->transform($property, WriterContext::INTERFACE);

    expect($property->name)->toBe('already_snake');
});

it('handles single word names', function () {
    $property = new PropertyIR('name', new StringTypeIR());

    $this->plugin->transform($property, WriterContext::INTERFACE);

    expect($property->name)->toBe('name');
});

it('handles consecutive uppercase letters', function () {
    $property = new PropertyIR('getHTTPResponse', new StringTypeIR());

    $this->plugin->transform($property, WriterContext::INTERFACE);

    expect($property->name)->toBe('get_http_response');
});

it('does not provide files', function () {
    expect($this->plugin->providesFile())->toBeFalse();
    expect($this->plugin->getFileContent())->toBeNull();
    expect($this->plugin->getFilePath())->toBeNull();
});

it('transforms all property names unconditionally in FileWriter', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data');

    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('firstName', new StringTypeIR()),
            new PropertyIR('lastName', new StringTypeIR()),
            new PropertyIR('phoneNumber', new IntTypeIR()),
        ],
    );

    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $plugin = new SnakeCasePlugin();
    $outputDir = sys_get_temp_dir() . '/snake-case-global-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    try {
        $writer = new FileWriter($root, [$plugin], $outputDir);
        $writer->write();

        $content = file_get_contents($outputDir . '/App/Data/UserData.ts');

        expect($content)->toContain('readonly first_name: string;');
        expect($content)->toContain('readonly last_name: string;');
        expect($content)->toContain('readonly phone_number: number;');

        expect($content)->toContain('first_name: S.String');
        expect($content)->toContain('last_name: S.String');
        expect($content)->toContain('phone_number: S.Number');
    } finally {
        if (is_dir($outputDir)) {
            deleteDirectory($outputDir);
        }
    }
});

it('transforms names that are already snake_case without mangling in FileWriter', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data');

    $schema = new SchemaIR(
        'MixedData',
        [],
        [
            new PropertyIR('already_snake', new StringTypeIR()),
            new PropertyIR('camelCase', new StringTypeIR()),
            new PropertyIR('simple', new IntTypeIR()),
        ],
    );

    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $plugin = new SnakeCasePlugin();
    $outputDir = sys_get_temp_dir() . '/snake-case-mixed-test-' . uniqid();
    mkdir($outputDir, 0755, true);

    try {
        $writer = new FileWriter($root, [$plugin], $outputDir);
        $writer->write();

        $content = file_get_contents($outputDir . '/App/Data/MixedData.ts');

        expect($content)->toContain('readonly already_snake: string;');
        expect($content)->toContain('readonly camel_case: string;');
        expect($content)->toContain('readonly simple: number;');
    } finally {
        if (is_dir($outputDir)) {
            deleteDirectory($outputDir);
        }
    }
});
