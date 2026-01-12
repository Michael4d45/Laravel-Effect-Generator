<?php

declare(strict_types=1);

use EffectSchemaGenerator\EffectSchemaGeneratorServiceProvider;
use EffectSchemaGenerator\Discovery\ClassDiscoverer;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use EffectSchemaGenerator\Commands\GenerateSchemasCommand;

it('registers ClassDiscoverer as singleton', function () {
    $instance1 = app(ClassDiscoverer::class);
    $instance2 = app(ClassDiscoverer::class);
    
    expect($instance1)->toBeInstanceOf(ClassDiscoverer::class);
    expect($instance1)->toBe($instance2);
});

it('registers DataClassParser as singleton', function () {
    $instance1 = app(DataClassParser::class);
    $instance2 = app(DataClassParser::class);
    
    expect($instance1)->toBeInstanceOf(DataClassParser::class);
    expect($instance1)->toBe($instance2);
});

it('registers EnumParser as singleton', function () {
    $instance1 = app(EnumParser::class);
    $instance2 = app(EnumParser::class);
    
    expect($instance1)->toBeInstanceOf(EnumParser::class);
    expect($instance1)->toBe($instance2);
});

it('registers GenerateSchemasCommand as singleton', function () {
    $instance1 = app(GenerateSchemasCommand::class);
    $instance2 = app(GenerateSchemasCommand::class);
    
    expect($instance1)->toBeInstanceOf(GenerateSchemasCommand::class);
    expect($instance1)->toBe($instance2);
});

it('merges config from config file', function () {
    $config = config('effect-schema');
    
    expect($config)->toBeArray();
    expect($config)->toHaveKey('paths');
});

it('registers command when running in console', function () {
    // The command should be registered
    expect($this->artisan('effect-schema:transform', ['--help']))
        ->assertSuccessful();
});

it('registers services correctly', function () {
    // All services should be registered and resolvable
    expect(app(ClassDiscoverer::class))->toBeInstanceOf(ClassDiscoverer::class);
    expect(app(DataClassParser::class))->toBeInstanceOf(DataClassParser::class);
    expect(app(EnumParser::class))->toBeInstanceOf(EnumParser::class);
    expect(app(GenerateSchemasCommand::class))->toBeInstanceOf(GenerateSchemasCommand::class);
});
