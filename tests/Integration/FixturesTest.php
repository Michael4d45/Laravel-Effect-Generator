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
 * Helper to determine expected file path based on namespace.
 */
function getExpectedFile(string $namespace, string $outputDir): string
{
    $parts = explode('\\', $namespace);
    $fileName = array_pop($parts);
    $path = implode('/', $parts);
    $suffix = $path ? $path.'/'.$fileName.'.ts' : $fileName.'.ts';

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

        $expectedFile = getExpectedFile($namespace, $this->outputDir);

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

        $expectedFile = getExpectedFile($namespace, $this->outputDir);

        expect(File::exists($expectedFile))
            ->toBeTrue("Expected output file $expectedFile to exist for enum $name ($namespace)");

        $output = File::get($expectedFile);
        expect($output)->toContain("export type $name");
        expect($output)->toContain("export const {$name}Schema = S.Union(");
    }
});

it('produces correct types for ApiResponseData', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('EffectSchemaGenerator\Tests\Fixtures', $this->outputDir);
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

    $file = getExpectedFile('App\Data\Models', $this->outputDir);
    $content = File::get($file);

    expect($content)->toContain('export interface UserData');
    expect($content)->toContain('readonly is_admin: boolean;');
    expect($content)->toContain('readonly email_verified_at: Date | null;');
    expect($content)->toContain('readonly game_sessions?: readonly GameSessionData[] | undefined;');

    expect($content)->toContain('game_sessions: S.optional(S.Array(S.suspend(() => GameSessionDataSchema)))');
});

it('produces correct types for Enums', function () {
    Artisan::call('effect-schema:transform');

    $file = getExpectedFile('App\Enums', $this->outputDir);
    $content = File::get($file);

    expect($content)->toContain('export type Role = "host" | "player" | "spectator";');
});
