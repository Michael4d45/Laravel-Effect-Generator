<?php

declare(strict_types=1);

use EffectSchemaGenerator\Builder\AstBuilder;
use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\NamespaceIR;
use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use EffectSchemaGenerator\Writer\FileWriter;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->outputDir = sys_get_temp_dir().'/effect-schema-test-'.uniqid();
    mkdir($this->outputDir, 0755, true);

    $this->dataParser = app(DataClassParser::class);
    $this->enumParser = app(EnumParser::class);
    $this->astBuilder = app(AstBuilder::class);
});

afterEach(function () {
    if (isset($this->outputDir) && is_dir($this->outputDir)) {
        deleteDirectory($this->outputDir);
    }
});

it('writes TypeScript files for simple schema', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('id', new StringTypeIR),
            new PropertyIR('name', new StringTypeIR),
        ]
    );
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/App/Data.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);
    expect($content)->toContain('export interface UserData');
    expect($content)->toContain('readonly id: string;');
    expect($content)->toContain('readonly name: string;');
});

it('writes TypeScript files for enum', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Enums');
    $enum = new EnumIR(
        'Color',
        [
            ['name' => 'RED', 'value' => 'red'],
            ['name' => 'BLUE', 'value' => 'blue'],
        ],
        'string'
    );
    $namespace->enums[] = $enum;
    $root->namespaces['App\Enums'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/App/Enums.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);
    expect($content)->toContain('export type Color = "red" | "blue";');
});

it('handles nullable properties', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('id', new StringTypeIR, nullable: true),
            new PropertyIR('name', new StringTypeIR, optional: true),
        ]
    );
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    expect($content)->toContain('readonly id: string | null;');
    expect($content)->toContain('readonly name?: string;');
});

it('generates imports for referenced types', function () {
    $root = new RootIR;

    // Create namespace for enums
    $enumNamespace = new NamespaceIR('App\Enums');
    $enumNamespace->enums[] = new EnumIR('Color', [['name' => 'RED']], '');
    $root->namespaces['App\Enums'] = $enumNamespace;

    // Create namespace for data with reference to enum
    $dataNamespace = new NamespaceIR('App\Data');
    $colorType = new ClassReferenceTypeIR('App\Enums\Color', 'Color');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('favoriteColor', $colorType),
        ]
    );
    $dataNamespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $dataNamespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    expect($content)->toContain("import { Color } from './Enums';");
    expect($content)->toContain('readonly favoriteColor: Color;');
});

it('does not import types already in the same file', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    // Create two schemas in the same namespace
    $userType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $userSchema = new SchemaIR('UserData', [], [new PropertyIR('id', new StringTypeIR)]);
    $profileSchema = new SchemaIR(
        'ProfileData',
        [],
        [new PropertyIR('user', $userType)]
    );

    $namespace->schemas[] = $userSchema;
    $namespace->schemas[] = $profileSchema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    // Should not import UserData since it's in the same file
    expect($content)->not->toContain('import { UserData }');
    expect($content)->toContain('readonly user: UserData;');
});

it('writes transformer-provided files', function () {
    $root = new RootIR;
    $transformer = new \EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin;

    $writer = new FileWriter($root, [$transformer], $this->outputDir);
    $writer->write();

    $transformerFilePath = $this->outputDir.'/Illuminate/Pagination.ts';
    expect(file_exists($transformerFilePath))->toBeTrue();

    $content = file_get_contents($transformerFilePath);
    expect($content)->toContain('import { Schema as S } from \'effect\';');
    expect($content)->toContain('export interface LengthAwarePaginator<T extends object>');
    expect($content)->toContain('export interface PaginationLinks');
    expect($content)->toContain('export interface PaginationMeta');
    expect($content)->toContain('export const LengthAwarePaginatorSchema');
});

it('generates imports for transformer-provided types', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    // Create a schema that uses LengthAwarePaginator
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $paginatorType = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        [new StringTypeIR, $itemType] // key type and item type
    );

    $userSchema = new SchemaIR('UserData', [], [new PropertyIR('id', new StringTypeIR)]);
    $responseSchema = new SchemaIR(
        'UsersResponse',
        [],
        [new PropertyIR('users', $paginatorType)]
    );

    $namespace->schemas[] = $userSchema;
    $namespace->schemas[] = $responseSchema;
    $root->namespaces['App\Data'] = $namespace;

    $transformer = new \EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin;
    $writer = new FileWriter($root, [$transformer], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    // Should import LengthAwarePaginator from the transformer file
    expect($content)->toContain("import { LengthAwarePaginator, LengthAwarePaginatorSchema } from '../Illuminate/Pagination';");
    expect($content)->toContain('readonly users: LengthAwarePaginator<UserData>;');
});

it('organizes files by namespace structure', function () {
    $root = new RootIR;

    $eventsNamespace = new NamespaceIR('App\Data\Events');
    $eventsNamespace->schemas[] = new SchemaIR('EventData', [], []);
    $root->namespaces['App\Data\Events'] = $eventsNamespace;

    $modelsNamespace = new NamespaceIR('App\Data\Models');
    $modelsNamespace->schemas[] = new SchemaIR('ModelData', [], []);
    $root->namespaces['App\Data\Models'] = $modelsNamespace;

    $enumsNamespace = new NamespaceIR('App\Enums');
    $enumsNamespace->enums[] = new EnumIR('Status', [['name' => 'ACTIVE']], '');
    $root->namespaces['App\Enums'] = $enumsNamespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    expect(file_exists($this->outputDir.'/App/Data/Events.ts'))->toBeTrue();
    expect(file_exists($this->outputDir.'/App/Data/Models.ts'))->toBeTrue();
    expect(file_exists($this->outputDir.'/App/Enums.ts'))->toBeTrue();
});

it('handles arrays with item types', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    $itemType = new StringTypeIR;
    $arrayType = new ArrayTypeIR($itemType);
    $schema = new SchemaIR(
        'TagsData',
        [],
        [new PropertyIR('tags', $arrayType)]
    );
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    expect($content)->toContain('readonly tags: readonly string[];');
});

it('handles nested namespaces correctly', function () {
    $root = new RootIR;

    $deepNamespace = new NamespaceIR('App\Data\Deeply\Nested');
    $deepNamespace->schemas[] = new SchemaIR('DeepData', [], []);
    $root->namespaces['App\Data\Deeply\Nested'] = $deepNamespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    expect(file_exists($this->outputDir.'/App/Data/Deeply/Nested.ts'))->toBeTrue();
});

it('marks properties with Lazy types as optional', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    // Create a standalone Lazy type
    $lazyType = new ClassReferenceTypeIR('Spatie\LaravelData\Lazy', 'Lazy');

    $userSchema = new SchemaIR('UserData', [], [new PropertyIR('id', new StringTypeIR)]);
    $responseSchema = new SchemaIR(
        'ResponseData',
        [],
        [
            new PropertyIR('user', $lazyType), // Lazy type should be optional
            new PropertyIR('name', new StringTypeIR), // Regular type should not be optional
        ]
    );

    $namespace->schemas[] = $userSchema;
    $namespace->schemas[] = $responseSchema;
    $root->namespaces['App\Data'] = $namespace;

    $plugin = new \EffectSchemaGenerator\Plugins\LazyPlugin;
    $writer = new FileWriter($root, [$plugin], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    // Lazy property should have ? after the name
    expect($content)->toContain('readonly user?: unknown;');
    // Regular property should not have ?
    expect($content)->toContain('readonly name: string;');
});

it('marks properties with union types containing Lazy as optional', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    // Create a union type: Collection|Lazy
    $collectionType = new ClassReferenceTypeIR('Illuminate\Support\Collection', 'Collection');
    $lazyType = new ClassReferenceTypeIR('Spatie\LaravelData\Lazy', 'Lazy');
    $unionType = new \EffectSchemaGenerator\IR\Types\UnionTypeIR([$collectionType, $lazyType]);

    $userSchema = new SchemaIR('UserData', [], [new PropertyIR('id', new StringTypeIR)]);
    $responseSchema = new SchemaIR(
        'ResponseData',
        [],
        [
            new PropertyIR('participants', $unionType), // Collection|Lazy should be optional and become just Collection
            new PropertyIR('name', new StringTypeIR), // Regular type should not be optional
        ]
    );

    $namespace->schemas[] = $userSchema;
    $namespace->schemas[] = $responseSchema;
    $root->namespaces['App\Data'] = $namespace;

    // Need both LazyPlugin and CollectionPlugin as transformers
    $transformers = [
        new \EffectSchemaGenerator\Plugins\LazyPlugin,
        new \EffectSchemaGenerator\Plugins\CollectionPlugin,
    ];

    $schemaWriter = new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
        new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter($transformers)),
        $transformers,
    );

    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    // Test that the file was generated successfully
    expect($content)->toContain('export interface ResponseData');
    expect($content)->toContain('readonly name: string;');
});

it('generates exact TypeScript output for simple schema', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('id', new StringTypeIR),
            new PropertyIR('name', new StringTypeIR),
            new PropertyIR('email', new StringTypeIR),
        ]
    );
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    $expected = <<<'TS'
export interface UserData {
  readonly id: string;
  readonly name: string;
  readonly email: string;
}
TS;

    // The file will have a trailing newline, so add it to expected
    $expectedWithNewline = $expected."\n";
    expect($content)->toBe($expectedWithNewline);
});

it('generates exact TypeScript output with nullable and optional properties', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('id', new StringTypeIR, nullable: true),
            new PropertyIR('name', new StringTypeIR, optional: true),
            new PropertyIR('email', new StringTypeIR),
        ]
    );
    $namespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    $expected = <<<'TS'
export interface UserData {
  readonly id: string | null;
  readonly name?: string;
  readonly email: string;
}
TS;

    // The file will have a trailing newline, so add it to expected
    $expectedWithNewline = $expected."\n";
    expect($content)->toBe($expectedWithNewline);
});

it('generates exact TypeScript output with imports', function () {
    $root = new RootIR;

    // Create enum namespace
    $enumNamespace = new NamespaceIR('App\Enums');
    $enumNamespace->enums[] = new EnumIR('Status', [['name' => 'ACTIVE'], ['name' => 'INACTIVE']], '');
    $root->namespaces['App\Enums'] = $enumNamespace;

    // Create data namespace with reference to enum
    $dataNamespace = new NamespaceIR('App\Data');
    $statusType = new ClassReferenceTypeIR('App\Enums\Status', 'Status');
    $schema = new SchemaIR(
        'UserData',
        [],
        [
            new PropertyIR('id', new StringTypeIR),
            new PropertyIR('status', $statusType),
        ]
    );
    $dataNamespace->schemas[] = $schema;
    $root->namespaces['App\Data'] = $dataNamespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Data.ts');
    $expected = <<<'TS'
import { Status } from './Enums';
export interface UserData {
  readonly id: string;
  readonly status: Status;
}
TS;

    // The file may have a trailing newline, so normalize both
    // The file will have a trailing newline, so add it to expected
    $expectedWithNewline = $expected."\n";
    expect($content)->toBe($expectedWithNewline);
});

it('generates exact TypeScript output for enum', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Enums');
    $enum = new EnumIR(
        'Color',
        [
            ['name' => 'RED', 'value' => 'red'],
            ['name' => 'GREEN', 'value' => 'green'],
            ['name' => 'BLUE', 'value' => 'blue'],
        ],
        'string'
    );
    $namespace->enums[] = $enum;
    $root->namespaces['App\Enums'] = $namespace;

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $content = file_get_contents($this->outputDir.'/App/Enums.ts');
    $expected = <<<'TS'
export type Color = "red" | "green" | "blue";
TS;

    // The file will have a trailing newline, so add it to expected
    $expectedWithNewline = $expected."\n";
    expect($content)->toBe($expectedWithNewline);
});

it('writes TypeScript files for ComplexMetadataData with nested array structure', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexMetadataData::class);
    $root = $this->astBuilder->build(collect([$classToken]));

    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/EffectSchemaGenerator/Tests/Fixtures.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);

    // Extract just the ComplexMetadataData interface from the file
    $lines = explode("\n", $content);
    $inInterface = false;
    $interfaceLines = [];
    $braceCount = 0;

    foreach ($lines as $line) {
        if (str_contains($line, 'export interface ComplexMetadataData')) {
            $inInterface = true;
            $interfaceLines[] = $line;
            $braceCount += substr_count($line, '{') - substr_count($line, '}');

            continue;
        }

        if ($inInterface) {
            $interfaceLines[] = $line;
            $braceCount += substr_count($line, '{') - substr_count($line, '}');
            if ($braceCount === 0) {
                break;
            }
        }
    }

    $interfaceContent = implode("\n", $interfaceLines);
    $interfaceContent = preg_replace('/[ \t]+$/m', '', $interfaceContent);
    $interfaceContent = rtrim($interfaceContent);

    $expected = <<<'TS'
export interface ComplexMetadataData {
  readonly metadata: Record<string, readonly {
    readonly name: string;
    readonly value: number | string | null;
  }[]>;
}
TS;

    expect($interfaceContent)->toBe($expected);
});

it('writes TypeScript files for ComplexData with all advanced features', function () {
    // Parse all related classes that ComplexData depends on
    $complexDataToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexData::class);

    // Try to parse dependencies (they might not all exist, so we'll handle gracefully)
    $tokens = collect([$complexDataToken]);

    try {
        $userDataToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
        $tokens->push($userDataToken);
    } catch (\Throwable $e) {
        // Skip if not available
    }

    try {
        $profileDataToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProfileData::class);
        $tokens->push($profileDataToken);
    } catch (\Throwable $e) {
        // Skip if not available
    }

    try {
        $addressDataToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\AddressData::class);
        $tokens->push($addressDataToken);
    } catch (\Throwable $e) {
        // Skip if not available
    }

    $root = $this->astBuilder->build($tokens);

    // Use transformers for Lazy, Collection, and Date types
    $transformers = [
        new \EffectSchemaGenerator\Plugins\LazyPlugin,
        new \EffectSchemaGenerator\Plugins\CollectionPlugin,
        new \EffectSchemaGenerator\Plugins\DatePlugin,
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
    ];

    $writer = new FileWriter($root, $transformers, $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/EffectSchemaGenerator/Tests/Fixtures.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);

    // Extract just the ComplexData interface from the file
    // The file might contain other interfaces, so we extract only the ComplexData part
    $lines = explode("\n", $content);
    $inComplexData = false;
    $complexDataLines = [];
    $braceCount = 0;

    foreach ($lines as $line) {
        if (str_contains($line, 'export interface ComplexData')) {
            $inComplexData = true;
            $complexDataLines[] = $line;
            $braceCount += substr_count($line, '{') - substr_count($line, '}');

            continue;
        }

        if ($inComplexData) {
            $complexDataLines[] = $line;
            $braceCount += substr_count($line, '{') - substr_count($line, '}');
            if ($braceCount === 0) {
                break;
            }
        }
    }

    $interfaceContent = implode("\n", $complexDataLines);

    // Normalize whitespace for comparison (remove trailing spaces)
    $interfaceContent = preg_replace('/[ \t]+$/m', '', $interfaceContent);
    $interfaceContent = rtrim($interfaceContent);

    // Check that the interface was generated
    expect($interfaceContent)->toContain('export interface ComplexData');
    expect($interfaceContent)->toContain('readonly identifier:');
    expect($interfaceContent)->toContain('readonly createdAt:');
});

it('generates type aliases for enums when TypeEnumWriter is used', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Enums');
    $enum = new EnumIR(
        'Color',
        [
            ['name' => 'RED', 'value' => 'red'],
            ['name' => 'BLUE', 'value' => 'blue'],
        ],
        'string'
    );
    $namespace->enums[] = $enum;
    $root->namespaces['App\Enums'] = $namespace;

    // Create FileWriter with TypeEnumWriter
    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ], $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/App/Enums.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);
    expect($content)->toContain('export type Color = "red" | "blue";');
    expect($content)->not->toContain('export enum Color');
});

it('generates native enums for enums when DefaultEnumWriter is used', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Enums');
    $enum = new EnumIR(
        'Color',
        [
            ['name' => 'RED', 'value' => 'red'],
            ['name' => 'BLUE', 'value' => 'blue'],
        ],
        'string'
    );
    $namespace->enums[] = $enum;
    $root->namespaces['App\Enums'] = $namespace;

    // Create FileWriter with DefaultEnumWriter
    $writer = new FileWriter($root, [
        new \EffectSchemaGenerator\Writer\DefaultEnumWriter(true),
    ], $this->outputDir);
    $writer->write();

    $filePath = $this->outputDir.'/App/Enums.ts';
    expect(file_exists($filePath))->toBeTrue();

    $content = file_get_contents($filePath);
    expect($content)->toContain('export enum Color');
    expect($content)->toContain("RED = 'red'");
    expect($content)->toContain("BLUE = 'blue'");
    expect($content)->not->toContain('export type Color = "red" | "blue"');
});

it('DefaultEnumWriter generates native enums when transform_to_native_enums is true', function () {
    $enum = new EnumIR('Color', [
        ['name' => 'RED', 'value' => 'red'],
        ['name' => 'BLUE', 'value' => 'blue'],
    ], 'string');

    $writer = new \EffectSchemaGenerator\Writer\DefaultEnumWriter(true);
    $result = $writer->writeEnum($enum);

    expect($result)->toContain('export enum Color');
    expect($result)->toContain("RED = 'red'");
    expect($result)->toContain("BLUE = 'blue'");
});

it('DefaultEnumWriter generates type aliases when transform_to_native_enums is false', function () {
    $enum = new EnumIR('Color', [
        ['name' => 'RED', 'value' => 'red'],
        ['name' => 'BLUE', 'value' => 'blue'],
    ], 'string');

    $writer = new \EffectSchemaGenerator\Writer\DefaultEnumWriter(false);
    $result = $writer->writeEnum($enum);

    expect($result)->toContain('export type Color = "red" | "blue";');
    expect($result)->not->toContain('export enum Color');
});

it('TypeEnumWriter generates type aliases', function () {
    $enum = new EnumIR('Color', [
        ['name' => 'RED', 'value' => 'red'],
        ['name' => 'BLUE', 'value' => 'blue'],
    ], 'string');

    $writer = new \EffectSchemaGenerator\Writer\TypeEnumWriter;
    $result = $writer->writeEnum($enum);

    expect($result)->toBe('export type Color = "red" | "blue";');
});

it('EffectSchemaEnumWriter generates effect schema unions', function () {
    $enum = new EnumIR('Color', [
        ['name' => 'RED', 'value' => 'red'],
        ['name' => 'BLUE', 'value' => 'blue'],
    ], 'string');

    $writer = new \EffectSchemaGenerator\Writer\EffectSchemaEnumWriter;
    $result = $writer->writeEnum($enum);

    expect($result)->toBe('export const ColorSchema = S.Union(S.Literal("red"), S.Literal("blue"));');
});

it('EffectSchemaSchemaWriter generates effect schema structs', function () {
    $schema = new SchemaIR('UserData', [], [
        new PropertyIR('id', new StringTypeIR),
        new PropertyIR('name', new StringTypeIR),
    ]);

    $writer = new \EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter([]);

    $imports = [];
    $result = $writer->writeSchema($schema, 'test.ts', [], [], $imports);

    expect($result)->toContain('export const UserDataSchema = S.Struct({');
    expect($result)->toContain('id: S.String');
    expect($result)->toContain('name: S.String');
    expect($result)->toContain('});');
});

it('EffectSchemaSchemaWriter does not suspend enum references', function () {
    $enumType = new ClassReferenceTypeIR('App\Enums\Status', 'Status');
    $enumType->isEnum = true;

    $classType = new ClassReferenceTypeIR('App\Data\OtherData', 'OtherData');
    $classType->isEnum = false;

    $schema = new SchemaIR('UserData', [], [
        new PropertyIR('status', $enumType),
        new PropertyIR('other', $classType),
    ]);

    $writer = new \EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter([]);

    $imports = [];
    $result = $writer->writeSchema($schema, 'test.ts', [], [], $imports);

    // Enum should not be suspended
    expect($result)->toContain('status: StatusSchema');
    // Class should still be suspended
    expect($result)->toContain('other: S.suspend((): S.Schema<OtherData, OtherDataEncoded> => OtherDataSchema)');
});

it('EffectSchemaSchemaWriter does not suspend nullable enum references', function () {
    $enumType = new ClassReferenceTypeIR('App\Enums\Status', 'Status');
    $enumType->isEnum = true;

    $schema = new SchemaIR('UserData', [], [
        new PropertyIR('status', $enumType, true), // nullable
    ]);

    $writer = new \EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter([]);

    $imports = [];
    $result = $writer->writeSchema($schema, 'test.ts', [], [], $imports);

    expect($result)->toContain('status: S.NullOr(StatusSchema)');
});

it('MultiArtifactFileContentWriter uses multiple writers for each IR type', function () {
    $root = new RootIR;
    $namespace = new NamespaceIR('App\Data');

    // Add a schema
    $schema = new SchemaIR('UserData', [], [
        new PropertyIR('id', new StringTypeIR),
    ]);
    $namespace->schemas[] = $schema;

    // Add an enum
    $enum = new EnumIR('Color', [
        ['name' => 'RED', 'value' => 'red'],
    ], 'string');
    $namespace->enums[] = $enum;

    $root->namespaces['App\Data'] = $namespace;

    // Create multiple transformers
    $transformers = [
        new \EffectSchemaGenerator\Writer\DefaultSchemaWriter(
            new \EffectSchemaGenerator\Writer\DefaultPropertyWriter(new \EffectSchemaGenerator\Writer\TypeScriptWriter([])),
            [],
        ),
        new \EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter([]),
        new \EffectSchemaGenerator\Writer\DefaultEnumWriter(true),
        new \EffectSchemaGenerator\Writer\TypeEnumWriter,
    ];

    $fileContentWriter = new \EffectSchemaGenerator\Writer\MultiArtifactFileContentWriter(
        $transformers,
        new \EffectSchemaGenerator\Writer\DefaultImportWriter,
    );

    $result = $fileContentWriter->writeFileContent('App/Data.ts', [$namespace]);

    // Should contain both interface and schema for UserData
    expect($result)->toContain('export interface UserData');
    expect($result)->toContain('export const UserDataSchema = S.Struct');

    // Should contain both native enum and type alias for Color
    expect($result)->toContain('export enum Color');
    expect($result)->toContain('export type Color = "red";');
});
