<?php

declare(strict_types=1);

use EffectSchemaGenerator\Commands\GenerateSchemasCommand;

beforeEach(function () {
    $this->command = app(GenerateSchemasCommand::class);
});

it('can be instantiated', function () {
    expect($this->command)->toBeInstanceOf(GenerateSchemasCommand::class);
});

it('has correct signature', function () {
    expect($this->command->getName())->toBe('effect-schema:transform');
    expect($this->command->getDescription())->toBe('Generate TypeScript interfaces and Effect schemas from PHP Spatie Data classes');
});
