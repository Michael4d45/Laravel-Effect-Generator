<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\Plugins\DatePlugin;
use EffectSchemaGenerator\Writer\DefaultPropertyWriter;
use EffectSchemaGenerator\Writer\DefaultSchemaWriter;
use EffectSchemaGenerator\Writer\FileWriter;
use EffectSchemaGenerator\Writer\TypeEnumWriter;
use EffectSchemaGenerator\Writer\TypeScriptWriter;

beforeEach(function () {
    $this->outputDir = sys_get_temp_dir() . '/date-plugin-test-' . uniqid();
    mkdir($this->outputDir, 0755, true);
});

afterEach(function () {
    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        deleteDirectory($this->outputDir);
    }
});

it('transforms Carbon type to Date in FileWriter with DatePlugin', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data\Events');
    
    // Create a schema with a Carbon property
    $carbonType = new ClassReferenceTypeIR('Carbon\Carbon', 'Carbon');
    $schema = new SchemaIR(
        'SessionEventOccurredData',
        [],
        [
            new PropertyIR('user_id', new \EffectSchemaGenerator\IR\Types\IntTypeIR()),
            new PropertyIR('timestamp', $carbonType),
        ]
    );
    
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data\Events'] = $namespace;
    
    // Create a DatePlugin instance
    $datePlugin = new DatePlugin();
    
    // Create transformers list with DatePlugin
    $transformers = [
        $datePlugin,
        new DefaultSchemaWriter(
            new DefaultPropertyWriter(new TypeScriptWriter([$datePlugin])),
            [$datePlugin],
        ),
        new TypeEnumWriter(),
    ];
    
    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();
    
    $filePath = $this->outputDir . '/App/Data/Events.ts';
    expect(file_exists($filePath))->toBeTrue();
    
    $content = file_get_contents($filePath);
    
    // Should contain the interface
    expect($content)->toContain('export interface SessionEventOccurredData');
    expect($content)->toContain('readonly user_id: number;');
    
    // The crucial test: Carbon should be transformed to Date, not left as Carbon
    expect($content)->toContain('readonly timestamp: Date;');
    expect($content)->not->toContain('readonly timestamp: Carbon;');
});

it('transforms Illuminate Carbon type to Date in FileWriter with DatePlugin', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data\Events');
    
    // Create a schema with an Illuminate Carbon property
    $carbonType = new ClassReferenceTypeIR('Illuminate\Support\Carbon', 'Carbon');
    $schema = new SchemaIR(
        'TimerUpdatedData',
        [],
        [
            new PropertyIR('remaining_seconds', new \EffectSchemaGenerator\IR\Types\IntTypeIR()),
            new PropertyIR('timestamp', $carbonType),
        ]
    );
    
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data\Events'] = $namespace;
    
    // Create a DatePlugin instance
    $datePlugin = new DatePlugin();
    
    // Create transformers list with DatePlugin
    $transformers = [
        $datePlugin,
        new DefaultSchemaWriter(
            new DefaultPropertyWriter(new TypeScriptWriter([$datePlugin])),
            [$datePlugin],
        ),
        new TypeEnumWriter(),
    ];
    
    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();
    
    $filePath = $this->outputDir . '/App/Data/Events.ts';
    expect(file_exists($filePath))->toBeTrue();
    
    $content = file_get_contents($filePath);
    
    // Should contain the interface
    expect($content)->toContain('export interface TimerUpdatedData');
    expect($content)->toContain('readonly remaining_seconds: number;');
    
    // The crucial test: Illuminate\Support\Carbon should be transformed to Date
    expect($content)->toContain('readonly timestamp: Date;');
    expect($content)->not->toContain('readonly timestamp: Carbon;');
});

it('transforms multiple Carbon properties in schema', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data');
    
    // Create a schema with multiple Carbon properties (like ComplexData fixture)
    $carbonType = new ClassReferenceTypeIR('Carbon\Carbon', 'Carbon');
    $nullableCarbonType = new \EffectSchemaGenerator\IR\Types\NullableTypeIR($carbonType);
    
    $schema = new SchemaIR(
        'ComplexData',
        [],
        [
            new PropertyIR('name', new \EffectSchemaGenerator\IR\Types\StringTypeIR()),
            new PropertyIR('createdAt', $carbonType),
            new PropertyIR('updatedAt', $nullableCarbonType),
        ]
    );
    
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;
    
    // Create a DatePlugin instance
    $datePlugin = new DatePlugin();
    
    // Create transformers list with DatePlugin
    $transformers = [
        $datePlugin,
        new DefaultSchemaWriter(
            new DefaultPropertyWriter(new TypeScriptWriter([$datePlugin])),
            [$datePlugin],
        ),
        new TypeEnumWriter(),
    ];
    
    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();
    
    $filePath = $this->outputDir . '/App/Data.ts';
    expect(file_exists($filePath))->toBeTrue();
    
    $content = file_get_contents($filePath);
    
    // Should contain the interface
    expect($content)->toContain('export interface ComplexData');
    
    // Should have Date types, not Carbon
    expect($content)->toContain('readonly createdAt: Date;');
    expect($content)->toContain('readonly updatedAt: Date | null;');
    
    // Should NOT contain Carbon references
    expect($content)->not->toContain('Carbon');
});
