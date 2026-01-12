<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\Plugins\DatePlugin;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\WriterContext;

beforeEach(function () {
    $this->plugin = new DatePlugin();
});

it('can transform Carbon type in interface context', function () {
    $type = new ClassReferenceTypeIR('Carbon\Carbon');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('can transform Illuminate Carbon type in interface context', function () {
    $type = new ClassReferenceTypeIR('Illuminate\Support\Carbon');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('can transform DateTime type in interface context', function () {
    $type = new ClassReferenceTypeIR('DateTime');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('can transform DateTimeImmutable type in interface context', function () {
    $type = new ClassReferenceTypeIR('DateTimeImmutable');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('cannot transform non-date types', function () {
    $type = new ClassReferenceTypeIR('App\Data\UserData');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeFalse();
});

it('transforms Carbon to Date in interface context', function () {
    $type = new ClassReferenceTypeIR('Carbon\Carbon');

    $result = $this->plugin->transform($type, WriterContext::INTERFACE);

    expect($result)->toBe('Date');
});

it('transforms Carbon to string in encoded interface context', function () {
    $type = new ClassReferenceTypeIR('Carbon\Carbon');

    $result = $this->plugin->transform($type, WriterContext::ENCODED_INTERFACE);

    expect($result)->toBe('string');
});

it('transforms Carbon to Effect schema in schema context', function () {
    $type = new ClassReferenceTypeIR('Carbon\Carbon');

    $result = $this->plugin->transform($type, WriterContext::SCHEMA);

    expect($result)->toBe('S.DateFromString');
});

it('implements Transformer interface', function () {
    expect($this->plugin)->toBeInstanceOf(Transformer::class);
});

it('transforms all date types correctly in different contexts', function () {
    $carbonType = new ClassReferenceTypeIR('Carbon\Carbon');
    $illuminateCarbonType = new ClassReferenceTypeIR('Illuminate\Support\Carbon');
    $dateTimeType = new ClassReferenceTypeIR('DateTime');
    $dateTimeImmutableType = new ClassReferenceTypeIR('DateTimeImmutable');

    // Interface context
    expect($this->plugin->transform($carbonType, WriterContext::INTERFACE))->toBe('Date');
    expect($this->plugin->transform($illuminateCarbonType, WriterContext::INTERFACE))->toBe('Date');
    expect($this->plugin->transform($dateTimeType, WriterContext::INTERFACE))->toBe('Date');
    expect($this->plugin->transform($dateTimeImmutableType, WriterContext::INTERFACE))->toBe('Date');

    // Encoded interface context
    expect($this->plugin->transform($carbonType, WriterContext::ENCODED_INTERFACE))->toBe('string');
    expect($this->plugin->transform($illuminateCarbonType, WriterContext::ENCODED_INTERFACE))->toBe('string');
    expect($this->plugin->transform($dateTimeType, WriterContext::ENCODED_INTERFACE))->toBe('string');
    expect($this->plugin->transform($dateTimeImmutableType, WriterContext::ENCODED_INTERFACE))->toBe('string');

    // Schema context
    expect($this->plugin->transform($carbonType, WriterContext::SCHEMA))->toBe('S.DateFromString');
    expect($this->plugin->transform($illuminateCarbonType, WriterContext::SCHEMA))->toBe('S.DateFromString');
    expect($this->plugin->transform($dateTimeType, WriterContext::SCHEMA))->toBe('S.DateFromString');
    expect($this->plugin->transform($dateTimeImmutableType, WriterContext::SCHEMA))->toBe('S.DateFromString');
});
