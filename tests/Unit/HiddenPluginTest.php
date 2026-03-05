<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\AttributeIR;
use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\Plugins\HiddenPlugin;
use EffectSchemaGenerator\Writer\FileWriter;
use EffectSchemaGenerator\Writer\WriterContext;

beforeEach(function () {
    $this->plugin = new HiddenPlugin();
});

it('can transform property with Hidden attribute', function () {
    $property = new PropertyIR('secret', new StringTypeIR(), attributes: [
        new AttributeIR('EffectSchemaGenerator\\Attributes\\Hidden'),
    ]);

    $attributes = [
        'class' => [],
        'property' => $property->attributes,
    ];

    expect($this->plugin->canTransform($property, WriterContext::INTERFACE, $attributes))->toBeTrue();
});

it('cannot transform property without Hidden attribute', function () {
    $property = new PropertyIR('name', new StringTypeIR());

    $attributes = [
        'class' => [],
        'property' => $property->attributes,
    ];

    expect($this->plugin->canTransform($property, WriterContext::INTERFACE, $attributes))->toBeFalse();
});

it('can transform property with Spatie Computed attribute', function () {
    $property = new PropertyIR('numerator', new \EffectSchemaGenerator\IR\Types\IntTypeIR(), attributes: [
        new AttributeIR('Spatie\\LaravelData\\Attributes\\Computed'),
    ]);

    $attributes = [
        'class' => [],
        'property' => $property->attributes,
    ];

    expect($this->plugin->canTransform($property, WriterContext::INTERFACE, $attributes))->toBeTrue();
});

it('sets hidden flag on property with Computed attribute when transforming', function () {
    $property = new PropertyIR('denominator', new \EffectSchemaGenerator\IR\Types\IntTypeIR(), attributes: [
        new AttributeIR('Spatie\\LaravelData\\Attributes\\Computed'),
    ]);

    expect($property->hidden)->toBeFalse();

    $this->plugin->transform($property, WriterContext::INTERFACE, [
        'property' => $property->attributes,
    ]);

    expect($property->hidden)->toBeTrue();
});

it('can transform property when class has Hidden attribute', function () {
    $property = new PropertyIR('internal', new StringTypeIR());

    $attributes = [
        'class' => [new AttributeIR('EffectSchemaGenerator\\Attributes\\Hidden')],
        'property' => $property->attributes,
    ];

    expect($this->plugin->canTransform($property, WriterContext::INTERFACE, $attributes))->toBeTrue();
});

it('cannot transform TypeIR inputs', function () {
    $type = new StringTypeIR();

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeFalse();
});

it('sets hidden flag on property when transforming', function () {
    $property = new PropertyIR('secret', new StringTypeIR(), attributes: [
        new AttributeIR('EffectSchemaGenerator\\Attributes\\Hidden'),
    ]);

    expect($property->hidden)->toBeFalse();

    $this->plugin->transform($property, WriterContext::INTERFACE, [
        'property' => $property->attributes,
    ]);

    expect($property->hidden)->toBeTrue();
});

it('does not provide files', function () {
    expect($this->plugin->providesFile())->toBeFalse();
    expect($this->plugin->getFileContent())->toBeNull();
    expect($this->plugin->getFilePath())->toBeNull();
});

it('excludes hidden properties from generated output in FileWriter', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\\Data');
    $hiddenAttr = new AttributeIR('EffectSchemaGenerator\\Attributes\\Hidden');
    $schema = new SchemaIR('UserData', [], [
        new PropertyIR('name', new StringTypeIR()),
        new PropertyIR('secret', new StringTypeIR(), attributes: [$hiddenAttr]),
    ]);
    $namespace->schemas[] = $schema;
    $root->namespaces[] = $namespace;

    $outputDir = sys_get_temp_dir() . '/hidden-plugin-test-' . uniqid();
    $writer = new FileWriter($root, [$this->plugin], $outputDir);
    $writer->write();

    $expectedPath = $outputDir . '/App/Data/UserData.ts';
    expect(file_exists($expectedPath))->toBeTrue();
    $content = file_get_contents($expectedPath);
    expect($content)->toContain('name');
    expect($content)->not->toContain('secret');
});
