<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\Plugins\DatePlugin;
use EffectSchemaGenerator\Writer\DefaultPropertyWriter;
use EffectSchemaGenerator\Writer\DefaultSchemaWriter;
use EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter;
use EffectSchemaGenerator\Writer\FileWriter;
use EffectSchemaGenerator\Writer\TypeEnumWriter;
use EffectSchemaGenerator\Writer\TypeScriptWriter;

beforeEach(function () {
    $this->outputDir = sys_get_temp_dir() . '/date-debug-' . uniqid();
    mkdir($this->outputDir, 0755, true);
});

afterEach(function () {
    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        deleteDirectory($this->outputDir);
    }
});

it('debug: show actual generated output with DatePlugin', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data\Events');
    
    // Create exact schema from user's output
    $carbonType = new ClassReferenceTypeIR('Carbon\Carbon', 'Carbon');
    $schema = new SchemaIR(
        'SessionEventOccurredData',
        [],
        [
            new PropertyIR('user_id', new IntTypeIR()),
            new PropertyIR('timestamp', $carbonType),
        ]
    );
    
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data\Events'] = $namespace;
    
    $datePlugin = new DatePlugin();
    
    // Build transformers the same way config.php does
    $transformers = [
        $datePlugin,
        new DefaultSchemaWriter(
            new DefaultPropertyWriter(new TypeScriptWriter([$datePlugin])),
            [$datePlugin],
        ),
        new EffectSchemaSchemaWriter([$datePlugin]),
        new TypeEnumWriter(),
    ];
    
    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();
    
    $filePath = $this->outputDir . '/App/Data/Events.ts';
    $content = file_get_contents($filePath);
    
    // The assertions that should pass if DatePlugin is working
    expect($content)->toContain('readonly timestamp: Date;');
    expect($content)->not->toContain('readonly timestamp: Carbon;');
});
