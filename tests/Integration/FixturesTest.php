<?php

declare(strict_types=1);

use EffectSchemaGenerator\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(TestCase::class);

beforeEach(function () {
    $this->outputDir = sys_get_temp_dir().'/fixtures-test-'.uniqid();
    mkdir($this->outputDir, 0755, true);

    config([
        'effect-schema.output.directory' => $this->outputDir,
        'effect-schema.phpdoc_overrides_types' => true,
        'effect-schema.transformers' => [
            \EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin::class,
            \EffectSchemaGenerator\Plugins\DatePlugin::class,
            \EffectSchemaGenerator\Plugins\LazyOptionalPlugin::class,
            \EffectSchemaGenerator\Plugins\CollectionPlugin::class,
            \EffectSchemaGenerator\Writer\TypeEnumWriter::class,
            \EffectSchemaGenerator\Writer\EffectSchemaEnumWriter::class,
        ],
    ]);
});

afterEach(function () {
    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        File::deleteDirectory($this->outputDir);
    }
});

/**
 * Helper to determine expected file path based on namespace and type name.
 */
function getExpectedFile(string $namespace, string $typeName, string $outputDir): string
{
    $path = str_replace('\\', '/', $namespace);
    $suffix = $path.'/'.$typeName.'.ts';

    return $outputDir.'/'.$suffix;
}

it('successfully transforms all fixtures', function () {
    // Run the command to generate everything
    $status = Artisan::call('effect-schema:transform');
    expect($status)->toBe(0);

    // Use ClassDiscoverer to get the actual classes/enums that SHOULD have been transformed
    $discoverer = app(\EffectSchemaGenerator\Discovery\ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses();
    $enums = $discoverer->discoverEnums();

    foreach ($dataClasses as $class) {
        $reflection = new ReflectionClass($class);
        $namespace = $reflection->getNamespaceName();
        $name = $reflection->getShortName();

        $expectedFile = getExpectedFile($namespace, $name, $this->outputDir);

        expect(File::exists($expectedFile))
            ->toBeTrue("Expected output file $expectedFile to exist for $name ($namespace)");

        $output = File::get($expectedFile);
        expect($output)->toContain("export interface $name");
        expect($output)->toContain("export const {$name}Schema: S.Schema<{$name}, {$name}Encoded> = S.Struct({");
    }

    foreach ($enums as $enum) {
        $reflection = new ReflectionEnum($enum);
        $namespace = $reflection->getNamespaceName();
        $name = $reflection->getShortName();

        $expectedFile = getExpectedFile($namespace, $name, $this->outputDir);

        expect(File::exists($expectedFile))
            ->toBeTrue("Expected output file $expectedFile to exist for enum $name ($namespace)");

        $output = File::get($expectedFile);
        expect($output)->toContain("export type $name");
        expect($output)->toContain("export const {$name}Schema = S.Union(");
    }
});

it('produces correct types for ApiResponseData', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('EffectSchemaGenerator\Tests\Fixtures', 'ApiResponseData', $this->outputDir);
    $content = File::get($file);

    expect($content)->toContain('export interface ApiResponseData {');
    expect($content)->toContain('export interface ApiResponseDataEncoded {');
    expect($content)->toContain('export const ApiResponseDataSchema: S.Schema<ApiResponseData, ApiResponseDataEncoded> = S.Struct({');

    expect($content)->toContain('users: S.Array(S.suspend(() => UserDataSchema))');
    expect($content)->toContain('tasks: S.Array(S.suspend(() => TaskDataSchema))');
    expect($content)->toContain('currentUser: S.NullOr(S.suspend(() => UserDataSchema))');
    expect($content)->toContain('userProfile: S.NullOr(S.suspend(() => ProfileDataSchema))');
});

it('produces correct types for UserData', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('App\Data\Models', 'UserData', $this->outputDir);
    $content = File::get($file);

    expect($content)->toContain('export interface UserData');
    expect($content)->toContain('readonly is_admin: boolean;');
    expect($content)->toContain('readonly email_verified_at: Date | null;');
    expect($content)->toContain('readonly game_sessions?: readonly GameSessionData[] | undefined;');

    expect($content)->toContain('game_sessions: S.optional(S.Array(S.suspend(() => GameSessionDataSchema)))');
});

it('produces correct types for Enums', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('App\Enums', 'Role', $this->outputDir);
    $content = File::get($file);

    expect($content)->toContain('export type Role = "host" | "player" | "spectator";');
});

/**
 * Regression: Response with only LengthAwarePaginator (like ActivitiesList) must not
 * import LengthAwarePaginatorEncoded — the Pagination plugin does not export that type.
 * Correct: import { type LengthAwarePaginator, LengthAwarePaginatorSchema } from '...'
 * Wrong:   import { ..., type LengthAwarePaginatorEncoded, ... } from '...'
 * Reproducibility: if EffectSchemaSchemaWriter adds Encoded for transformer-provided types,
 * this test fails (and the same for ListRecipesResponse below).
 */
it('ActivitiesList-style response does not import LengthAwarePaginatorEncoded', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('App\Features\Activity\Responses', 'ActivitiesList', $this->outputDir);
    expect(File::exists($file))->toBeTrue('ActivitiesList.ts should be generated');

    $content = File::get($file);
    expect($content)->toContain('export interface ActivitiesList');
    expect($content)->toContain('LengthAwarePaginatorSchema');
    expect($content)->not->toContain('LengthAwarePaginatorEncoded');
});

/**
 * Regression: Response with LengthAwarePaginator plus other properties (like ListRecipesResponse)
 * must not import LengthAwarePaginatorEncoded — the Pagination plugin does not export that type.
 * kitchenassistant (latest package) was generating the wrong import; this test ensures correct output.
 */
it('ListRecipesResponse-style response does not import LengthAwarePaginatorEncoded', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('App\Features\Recipe\Responses', 'ListRecipesResponse', $this->outputDir);
    expect(File::exists($file))->toBeTrue('ListRecipesResponse.ts should be generated');

    $content = File::get($file);
    expect($content)->toContain('export interface ListRecipesResponse');
    expect($content)->toContain('LengthAwarePaginatorSchema');
    expect($content)->not->toContain('LengthAwarePaginatorEncoded');
});

/**
 * Diagnostic: run effect-schema:debug-imports to see why a schema gets Pagination imports.
 * With correct config (LengthAwarePaginatorPlugin in transformers), getTransformerFilePathForType
 * should return a path for LengthAwarePaginator (transformer branch → we do NOT add *Encoded).
 * If it returns null, we fall into the non-transformer branch and add LengthAwarePaginatorEncoded (wrong).
 */
it('debug-imports shows LengthAwarePaginator has transformer path (no Encoded import)', function () {
    config([
        'effect-schema.transformers' => [
            \EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin::class,
            \EffectSchemaGenerator\Plugins\DatePlugin::class,
            \EffectSchemaGenerator\Plugins\LazyOptionalPlugin::class,
            \EffectSchemaGenerator\Plugins\CollectionPlugin::class,
            \EffectSchemaGenerator\Writer\TypeEnumWriter::class,
            \EffectSchemaGenerator\Writer\EffectSchemaEnumWriter::class,
        ],
    ]);

    $exitCode = Artisan::call('effect-schema:debug-imports', [
        'class' => 'App\Features\Recipe\Responses\ListRecipesResponse',
    ]);
    $output = Artisan::output();

    expect($exitCode)->toBe(0, 'debug-imports should succeed and report correct Pagination import. Output: ' . $output);
    expect($output)->toContain('path = ');
    expect($output)->not->toContain('path = null (will add LengthAwarePaginatorEncoded');
});
