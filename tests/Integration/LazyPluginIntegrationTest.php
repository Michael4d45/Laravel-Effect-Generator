<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\Plugins\DatePlugin;
use EffectSchemaGenerator\Plugins\LazyOptionalPlugin;
use EffectSchemaGenerator\Writer\FileWriter;

beforeEach(function () {
    $this->outputDir = sys_get_temp_dir() . '/lazy-session-event-test-' . uniqid();
    mkdir($this->outputDir, 0755, true);
});

afterEach(function () {
    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        deleteDirectory($this->outputDir);
    }
});

it('generates exact TypeScript output for SessionEventData with Lazy properties', function () {
    $root = new RootIR();
    $namespace = new NamespaceIR('App\Data\Models');

    // Replicate SessionEventData structure with Lazy properties
    // From: class SessionEventData extends Data with GameSessionData|Lazy $session and Lazy|SessionParticipantData|null $participant
    
    $carbonType = new ClassReferenceTypeIR('Illuminate\Support\Carbon', 'Carbon');
    $gameSessionType = new ClassReferenceTypeIR('App\Data\Models\GameSessionData', 'GameSessionData');
    $sessionParticipantType = new ClassReferenceTypeIR('App\Data\Models\SessionParticipantData', 'SessionParticipantData');
    $eventTypeEnumType = new ClassReferenceTypeIR('App\Enums\EventType', 'EventType');
    $lazyType = new ClassReferenceTypeIR('Spatie\LaravelData\Lazy', 'Lazy');

    // Build union types with Lazy
    // GameSessionData|Lazy
    $sessionWithLazy = new UnionTypeIR([$gameSessionType, $lazyType]);
    
    // Lazy|SessionParticipantData|null
    $participantWithLazy = new NullableTypeIR(new UnionTypeIR([$lazyType, $sessionParticipantType]));

    $schema = new SchemaIR(
        'SessionEventData',
        [],
        [
            new PropertyIR('id', new StringTypeIR()),
            new PropertyIR('session_id', new StringTypeIR()),
            new PropertyIR('event_type', new NullableTypeIR($eventTypeEnumType)),
            new PropertyIR('participant_id', new NullableTypeIR(new StringTypeIR())),
            new PropertyIR('payload', new NullableTypeIR(new \EffectSchemaGenerator\IR\Types\RecordTypeIR(new StringTypeIR(), new \EffectSchemaGenerator\IR\Types\UnknownTypeIR()))),
            new PropertyIR('created_at', new NullableTypeIR($carbonType)),
            new PropertyIR('updated_at', new NullableTypeIR($carbonType)),
            new PropertyIR('session', $sessionWithLazy),
            new PropertyIR('participant', $participantWithLazy),
        ]
    );

    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data\Models'] = $namespace;

    // Create plugins
    $lazyPlugin = new LazyOptionalPlugin();
    $datePlugin = new DatePlugin();
    $plugins = [$lazyPlugin, $datePlugin];

    $writer = new FileWriter($root, $plugins, $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir . '/App/Data/Models.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);

    // Verify the interface exists
    expect($content)->toContain('export interface SessionEventData');

    // Verify all basic properties are present
    expect($content)->toContain('readonly id: string;');
    expect($content)->toContain('readonly session_id: string;');
    expect($content)->toContain('readonly event_type: EventType | null;');
    expect($content)->toContain('readonly participant_id: string | null;');
    expect($content)->toContain('readonly payload: Record<string, unknown> | null;');

    // Verify date properties are transformed to Date (not Carbon)
    expect($content)->toContain('readonly created_at: Date | null;');
    expect($content)->toContain('readonly updated_at: Date | null;');

    // Verify Lazy properties are marked optional and Lazy is removed
    // GameSessionData|Lazy $session should become optional GameSessionData
    expect($content)->toContain('readonly session?: GameSessionData;');
    
    // Lazy|SessionParticipantData|null $participant should become optional SessionParticipantData | null
    expect($content)->toContain('readonly participant?: SessionParticipantData | null;');

    // Verify Lazy type is completely removed from output (interface)
    expect($content)->not->toContain('Lazy');

    // Verify Carbon is not in output (should be Date)
    expect($content)->not->toContain('Carbon');

    // Verify the schema section exists and has correct structure
    expect($content)->toContain('export const SessionEventDataSchema = S.Struct');
    
    // Verify schema has the right properties - specifically check that Lazy and Carbon are not in schema
    // This tests the bug fix where LazyPlugin needs to handle SCHEMA context properly
    expect($content)->not->toContain('LazySchema');
    expect($content)->not->toContain('CarbonSchema');
});
