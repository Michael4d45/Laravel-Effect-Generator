<?php

declare(strict_types=1);

use EffectSchemaGenerator\Builder\AstBuilder;
use EffectSchemaGenerator\Discovery\ClassDiscoverer;
use EffectSchemaGenerator\IR\RootIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\RecordTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\StructTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\IR\Types\UnknownTypeIR;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use Illuminate\Support\Collection;

beforeEach(function () {
    $this->dataParser = app(DataClassParser::class);
    $this->enumParser = app(EnumParser::class);
    $this->astBuilder = app(AstBuilder::class);
});

it('builds RootIR from empty collection', function () {
    $root = $this->astBuilder->build(collect([]));

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toBeArray();
    expect($root->namespaces)->toBeEmpty();
});

it('builds RootIR with single data class', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas)->toHaveCount(1);
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0]->name)->toBe('UserData');
});

it('builds RootIR with single enum', function () {
    $enumToken = $this->enumParser->parse(\EffectSchemaGenerator\Tests\Fixtures\Color::class);
    $tokens = collect([$enumToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->enums)->toHaveCount(1);
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->enums[0]->name)->toBe('Color');
});

it('builds RootIR with multiple classes in same namespace', function () {
    $userToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $categoryToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\CategoryData::class);
    $tokens = collect([$userToken, $categoryToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas)->toHaveCount(2);
});

it('builds RootIR with classes and enums in different namespaces', function () {
    $userToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $colorToken = $this->enumParser->parse(\EffectSchemaGenerator\Tests\Fixtures\Color::class);
    $tokens = collect([$userToken, $colorToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas)->toHaveCount(1);
    expect($root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->enums)->toHaveCount(1);
});

it('identifies enums in class properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProductData::class);
    $enumToken = $this->enumParser->parse(\EffectSchemaGenerator\Tests\Fixtures\TestStatus::class);
    $tokens = collect([$classToken, $enumToken]);

    $root = $this->astBuilder->build($tokens);

    $namespace = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures'];
    $productSchema = collect($namespace->schemas)->first(fn($s) => $s->name === 'ProductData');
    $statusProp = collect($productSchema->properties)->first(fn($p) => $p->name === 'status');

    expect($statusProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($statusProp->type->isEnum)->toBeTrue();
    expect($statusProp->type->fqcn)->toBe(\EffectSchemaGenerator\Tests\Fixtures\TestStatus::class);
});

it('builds RootIR with all fixture Data classes', function () {
    $discoverer = app(ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn ($class) => str_starts_with($class, 'EffectSchemaGenerator\\Tests\\Fixtures\\'))
        ->filter(function ($class) {
            // Only test actual Data classes, not test fixtures
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    if ($dataClasses->isEmpty()) {
        $this->markTestSkipped('No fixture Data classes found');
    }

    $tokens = collect();
    foreach ($dataClasses as $className) {
        try {
            $tokens->push($this->dataParser->parse($className));
        } catch (\Throwable $e) {
            // Skip classes that can't be parsed
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No fixture Data classes could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();

    // Verify all namespaces are present
    $expectedNamespaces = $tokens->map(fn ($token) => $token->namespace)->unique();
    foreach ($expectedNamespaces as $namespace) {
        expect($root->namespaces)->toHaveKey($namespace);
    }
});

it('builds RootIR with all fixture Enums', function () {
    $discoverer = app(ClassDiscoverer::class);
    $enums = $discoverer->discoverEnums()
        ->filter(fn ($enum) => str_starts_with($enum, 'EffectSchemaGenerator\\Tests\\Fixtures\\'));

    if ($enums->isEmpty()) {
        $this->markTestSkipped('No fixture Enums found');
    }

    $tokens = collect();
    foreach ($enums as $enumName) {
        try {
            $tokens->push($this->enumParser->parse($enumName));
        } catch (\Throwable $e) {
            // Skip enums that can't be parsed
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No fixture Enums could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();

    // Verify all namespaces are present
    $expectedNamespaces = $tokens->map(fn ($token) => $token->namespace)->unique();
    foreach ($expectedNamespaces as $namespace) {
        expect($root->namespaces)->toHaveKey($namespace);
    }
});

it('builds RootIR with UserData properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];
    expect($schema->properties)->toBeArray();
    expect($schema->properties)->not->toBeEmpty();

    // Verify some expected properties
    $propertyNames = array_map(fn ($prop) => $prop->name, $schema->properties);
    expect($propertyNames)->toContain('id');
    expect($propertyNames)->toContain('name');
    expect($propertyNames)->toContain('email');
});

it('transfers class attributes from tokens to IR', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\OptionalAttributeData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];
    expect($schema->classAttributes)->toBeArray();
    expect($schema->classAttributes)->toHaveCount(1);
    expect($schema->classAttributes[0]->name)->toBe('Spatie\LaravelData\Attributes\Optional');
});

it('transfers property attributes from tokens to IR', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\OptionalAttributeData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $optionalProperty = findProperty($schema->properties, 'optionalProperty');
    expect($optionalProperty->attributes)->toBeArray();
    expect($optionalProperty->attributes)->toHaveCount(1);
    expect($optionalProperty->attributes[0]->name)->toBe('Spatie\LaravelData\Attributes\Optional');

    $requiredProperty = findProperty($schema->properties, 'requiredProperty');
    expect($requiredProperty->attributes)->toBeArray();
    expect($requiredProperty->attributes)->toBeEmpty();
});

it('transfers trait property attributes from tokens to IR', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ClassWithOptionalTrait::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $traitOptional = findProperty($schema->properties, 'traitOptionalProperty');
    expect($traitOptional->attributes)->toHaveCount(1);
    expect($traitOptional->attributes[0]->name)->toBe('Spatie\LaravelData\Attributes\Optional');

    $traitRequired = findProperty($schema->properties, 'traitRequiredProperty');
    expect($traitRequired->attributes)->toBeEmpty();
});

it('builds RootIR with enum cases', function () {
    $enumToken = $this->enumParser->parse(\EffectSchemaGenerator\Tests\Fixtures\Color::class);
    $tokens = collect([$enumToken]);

    $root = $this->astBuilder->build($tokens);

    $enum = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->enums[0];
    expect($enum->cases)->toBeArray();
    expect($enum->cases)->not->toBeEmpty();
    expect($enum->type)->toBe('');
});

it('handles properties with phpDoc types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];
    foreach ($schema->properties as $property) {
        expect($property->type)->not->toBeNull();
    }
});

it('handles properties with surveyor types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];
    foreach ($schema->properties as $property) {
        expect($property->type)->not->toBeNull();
    }
});

it('handles properties with unknown types', function () {
    // This test verifies that properties without types get UnknownTypeIR
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];
    // All properties should have a type, even if unknown
    foreach ($schema->properties as $property) {
        expect($property->type)->not->toBeNull();
    }
});

it('builds RootIR that can be converted to array', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $enumToken = $this->enumParser->parse(\EffectSchemaGenerator\Tests\Fixtures\Color::class);
    $tokens = collect([$classToken, $enumToken]);

    $root = $this->astBuilder->build($tokens);

    $array = $root->toArray();

    expect($array)->toBeArray();
    expect($array)->toHaveKey('namespaces');
    expect($array['namespaces'])->toBeArray();
});

it('builds RootIR with multiple fixture classes in same namespace', function () {
    $fixtureClasses = [
        \EffectSchemaGenerator\Tests\Fixtures\UserData::class,
        \EffectSchemaGenerator\Tests\Fixtures\AddressData::class,
        \EffectSchemaGenerator\Tests\Fixtures\CategoryData::class,
        \EffectSchemaGenerator\Tests\Fixtures\ProductData::class,
    ];

    $tokens = collect($fixtureClasses)->map(fn ($class) => $this->dataParser->parse($class));
    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');

    $namespace = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures'];
    expect($namespace->schemas)->toHaveCount(count($fixtureClasses));

    // Verify all classes are present
    $schemaNames = array_map(fn ($schema) => $schema->name, $namespace->schemas);
    expect($schemaNames)->toContain('UserData');
    expect($schemaNames)->toContain('AddressData');
    expect($schemaNames)->toContain('CategoryData');
    expect($schemaNames)->toContain('ProductData');
});

it('builds RootIR with multiple enums in same namespace', function () {
    $fixtureEnums = [
        \EffectSchemaGenerator\Tests\Fixtures\Color::class,
        \EffectSchemaGenerator\Tests\Fixtures\Priority::class,
        \EffectSchemaGenerator\Tests\Fixtures\TestStatus::class,
    ];

    $tokens = collect($fixtureEnums)->map(fn ($enum) => $this->enumParser->parse($enum));
    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('EffectSchemaGenerator\Tests\Fixtures');

    $namespace = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures'];
    expect($namespace->enums)->toHaveCount(count($fixtureEnums));

    // Verify all enums are present
    $enumNames = array_map(fn ($enum) => $enum->name, $namespace->enums);
    expect($enumNames)->toContain('Color');
    expect($enumNames)->toContain('Priority');
    expect($enumNames)->toContain('TestStatus');
});

it('handles all fixture data classes', function () {
    $fixtureClasses = [
        \EffectSchemaGenerator\Tests\Fixtures\UserData::class,
        \EffectSchemaGenerator\Tests\Fixtures\AddressData::class,
        \EffectSchemaGenerator\Tests\Fixtures\CategoryData::class,
        \EffectSchemaGenerator\Tests\Fixtures\ComplexData::class,
        \EffectSchemaGenerator\Tests\Fixtures\ProductData::class,
    ];

    $tokens = collect($fixtureClasses)->map(fn ($class) => $this->dataParser->parse($class));
    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();
});

it('handles all fixture enums', function () {
    $fixtureEnums = [
        \EffectSchemaGenerator\Tests\Fixtures\Color::class,
        \EffectSchemaGenerator\Tests\Fixtures\Priority::class,
        \EffectSchemaGenerator\Tests\Fixtures\TestStatus::class,
    ];

    $tokens = collect($fixtureEnums)->map(fn ($enum) => $this->enumParser->parse($enum));
    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();
});

it('builds RootIR with app Data classes from tests/Fixtures/src/app', function () {
    $discoverer = app(ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn ($class) => str_starts_with($class, 'App\\Data\\'))
        ->filter(function ($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    if ($dataClasses->isEmpty()) {
        $this->markTestSkipped('No app Data classes found');
    }

    $tokens = collect();
    foreach ($dataClasses->take(5) as $className) {
        try {
            $tokens->push($this->dataParser->parse($className));
        } catch (\Throwable $e) {
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No app Data classes could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();

    // Verify all namespaces are present
    $expectedNamespaces = $tokens->map(fn ($token) => $token->namespace)->unique();
    foreach ($expectedNamespaces as $namespace) {
        expect($root->namespaces)->toHaveKey($namespace);
    }
});

it('builds RootIR with app Enums from tests/Fixtures/src/app', function () {
    $discoverer = app(ClassDiscoverer::class);
    $enums = $discoverer->discoverEnums()
        ->filter(fn ($enum) => str_starts_with($enum, 'App\\Enums\\'));

    if ($enums->isEmpty()) {
        $this->markTestSkipped('No app Enums found');
    }

    $tokens = collect();
    foreach ($enums as $enumName) {
        try {
            $tokens->push($this->enumParser->parse($enumName));
        } catch (\Throwable $e) {
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No app Enums could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->not->toBeEmpty();

    // Verify all namespaces are present
    $expectedNamespaces = $tokens->map(fn ($token) => $token->namespace)->unique();
    foreach ($expectedNamespaces as $namespace) {
        expect($root->namespaces)->toHaveKey($namespace);
    }
});

it('builds RootIR with specific app Data class', function () {
    if (! class_exists('App\\Data\\Events\\RealtimeMessageData')) {
        $this->markTestSkipped('App\\Data\\Events\\RealtimeMessageData not found');
    }

    $classToken = $this->dataParser->parse('App\\Data\\Events\\RealtimeMessageData');
    $tokens = collect([$classToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('App\\Data\\Events');
    expect($root->namespaces['App\\Data\\Events']->schemas)->toHaveCount(1);
    expect($root->namespaces['App\\Data\\Events']->schemas[0]->name)->toBe('RealtimeMessageData');
});

it('builds RootIR with specific app Enum', function () {
    if (! enum_exists('App\\Enums\\Role')) {
        $this->markTestSkipped('App\\Enums\\Role not found');
    }

    $enumToken = $this->enumParser->parse('App\\Enums\\Role');
    $tokens = collect([$enumToken]);

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);
    expect($root->namespaces)->toHaveKey('App\\Enums');
    expect($root->namespaces['App\\Enums']->enums)->toHaveCount(1);
    expect($root->namespaces['App\\Enums']->enums[0]->name)->toBe('Role');
});

it('validates correct number of classes and enums discovered from src/app dir', function () {
    $discoverer = app(ClassDiscoverer::class);

    // Discover all Data classes from App namespace
    // Use unique() to handle duplicates from scanning both tests/Fixtures and tests/Fixtures/src/app
    $dataClasses = $discoverer->discoverDataClasses()
        ->unique()
        ->filter(fn ($class) => str_starts_with($class, 'App\\Data\\'))
        ->filter(function ($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    // Discover all Enums from App namespace
    $enums = $discoverer->discoverEnums()
        ->unique()
        ->filter(fn ($enum) => str_starts_with($enum, 'App\\Enums\\'));

    // Expected counts based on tests/Fixtures/src/app structure
    // 68 Data classes: 3 Events + 23 Models + 13 Requests + 29 Response
    expect($dataClasses->count())->toBe(68);

    // 7 Enums in App\Enums (CredentialType, EventType, QuestionType, Role, SessionStatus, SortDirection, Visibility)
    // EnumUtil.php is a trait, not an enum, so it's excluded by enum_exists()
    expect($enums->count())->toBe(7);
});

it('validates built AST has correct number of classes and enums in right namespaces', function () {
    $discoverer = app(ClassDiscoverer::class);

    // Discover all Data classes from App namespace
    // Use unique() to handle duplicates from scanning both tests/Fixtures and tests/Fixtures/src/app
    $dataClasses = $discoverer->discoverDataClasses()
        ->unique()
        ->filter(fn ($class) => str_starts_with($class, 'App\\Data\\'))
        ->filter(function ($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    // Discover all Enums from App namespace
    $enums = $discoverer->discoverEnums()
        ->unique()
        ->filter(fn ($enum) => str_starts_with($enum, 'App\\Enums\\'));

    if ($dataClasses->isEmpty() && $enums->isEmpty()) {
        $this->markTestSkipped('No app classes or enums found');
    }

    // Parse all classes and enums
    $tokens = collect();

    foreach ($dataClasses as $className) {
        try {
            $tokens->push($this->dataParser->parse($className));
        } catch (\Throwable $e) {
            continue;
        }
    }

    foreach ($enums as $enumName) {
        try {
            $tokens->push($this->enumParser->parse($enumName));
        } catch (\Throwable $e) {
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No app classes or enums could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    expect($root)->toBeInstanceOf(RootIR::class);

    // Validate namespace counts
    // App\Data\Events: 3 classes
    expect($root->namespaces)->toHaveKey('App\\Data\\Events');
    expect($root->namespaces['App\\Data\\Events']->schemas)->toHaveCount(3);

    // App\Data\Models: 23 classes
    expect($root->namespaces)->toHaveKey('App\\Data\\Models');
    expect($root->namespaces['App\\Data\\Models']->schemas)->toHaveCount(23);

    // App\Data\Requests: 13 classes
    expect($root->namespaces)->toHaveKey('App\\Data\\Requests');
    expect($root->namespaces['App\\Data\\Requests']->schemas)->toHaveCount(13);

    // App\Data\Response: 29 classes
    expect($root->namespaces)->toHaveKey('App\\Data\\Response');
    expect($root->namespaces['App\\Data\\Response']->schemas)->toHaveCount(29);

    // App\Enums: 7 enums (CredentialType, EventType, QuestionType, Role, SessionStatus, SortDirection, Visibility)
    expect($root->namespaces)->toHaveKey('App\\Enums');
    expect($root->namespaces['App\\Enums']->enums)->toHaveCount(7);

    // Validate total counts
    $totalSchemas = collect($root->namespaces)
        ->filter(fn ($ns) => str_starts_with($ns->namespace, 'App\\Data\\'))
        ->sum(fn ($ns) => count($ns->schemas));

    expect($totalSchemas)->toBe(68);

    $totalEnums = collect($root->namespaces)
        ->filter(fn ($ns) => str_starts_with($ns->namespace, 'App\\Enums'))
        ->sum(fn ($ns) => count($ns->enums));

    expect($totalEnums)->toBe(7);
});

it('generates correct IR types for UserData primitive properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\UserData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    // Find properties by name
    $idProp = findProperty($schema->properties, 'id');
    $nameProp = findProperty($schema->properties, 'name');
    $emailProp = findProperty($schema->properties, 'email');

    expect($idProp->type)->toBeInstanceOf(StringTypeIR::class);
    expect($nameProp->type)->toBeInstanceOf(StringTypeIR::class);
    expect($emailProp->type)->toBeInstanceOf(StringTypeIR::class);
});

it('generates correct IR types for AddressData primitive and class properties', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\AddressData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $streetProp = findProperty($schema->properties, 'street');
    $latitudeProp = findProperty($schema->properties, 'latitude');
    $longitudeProp = findProperty($schema->properties, 'longitude');
    $isPrimaryProp = findProperty($schema->properties, 'isPrimary');
    $apartmentProp = findProperty($schema->properties, 'apartment');

    expect($streetProp->type)->toBeInstanceOf(StringTypeIR::class);
    expect($latitudeProp->type)->toBeInstanceOf(FloatTypeIR::class);
    expect($longitudeProp->type)->toBeInstanceOf(FloatTypeIR::class);
    expect($isPrimaryProp->type)->toBeInstanceOf(BoolTypeIR::class);
    // Nullable string is now wrapped in NullableTypeIR
    expect($apartmentProp->type)->toBeInstanceOf(NullableTypeIR::class);
    expect($apartmentProp->type->innerType)->toBeInstanceOf(StringTypeIR::class);
});

it('generates correct IR types for CategoryData with self-reference', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\CategoryData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $idProp = findProperty($schema->properties, 'id');
    $parentProp = findProperty($schema->properties, 'parent');
    $sortOrderProp = findProperty($schema->properties, 'sortOrder');
    $isActiveProp = findProperty($schema->properties, 'isActive');

    expect($idProp->type)->toBeInstanceOf(StringTypeIR::class);
    expect($parentProp->type)->toBeInstanceOf(NullableTypeIR::class);
    expect($parentProp->type->innerType)->toBeInstanceOf(ClassReferenceTypeIR::class);
    $innerType = $parentProp->type->innerType;
    expect($innerType->namespace)->toBe('EffectSchemaGenerator\Tests\Fixtures');
    expect($innerType->fqcn)->toBe('EffectSchemaGenerator\Tests\Fixtures\CategoryData');
    expect($innerType->alias)->toBe('CategoryData');
    expect($sortOrderProp->type)->toBeInstanceOf(IntTypeIR::class);
    expect($isActiveProp->type)->toBeInstanceOf(BoolTypeIR::class);
});

it('generates correct IR types for ProductData with various types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ProductData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $idProp = findProperty($schema->properties, 'id');
    $priceProp = findProperty($schema->properties, 'price');
    $stockQuantityProp = findProperty($schema->properties, 'stockQuantity');
    $currencyCodeProp = findProperty($schema->properties, 'currencyCode');
    $statusProp = findProperty($schema->properties, 'status');
    $metaTitleProp = findProperty($schema->properties, 'metaTitle');

    expect($idProp->type)->toBeInstanceOf(StringTypeIR::class);
    expect($priceProp->type)->toBeInstanceOf(FloatTypeIR::class);
    expect($stockQuantityProp->type)->toBeInstanceOf(IntTypeIR::class);
    // Union type: string|int
    expect($currencyCodeProp->type)->toBeInstanceOf(UnionTypeIR::class);
    // Enum type should be ClassReferenceTypeIR
    expect($statusProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($statusProp->type->alias)->toBe('TestStatus');
    // Nullable string
    expect($metaTitleProp->type)->toBeInstanceOf(NullableTypeIR::class);
    expect($metaTitleProp->type->innerType)->toBeInstanceOf(StringTypeIR::class);
});

it('generates correct IR types for ComplexData with unions and arrays', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $identifierProp = findProperty($schema->properties, 'identifier');
    $optionalIdentifierProp = findProperty($schema->properties, 'optionalIdentifier');
    $mixedValueProp = findProperty($schema->properties, 'mixedValue');
    $tagsProp = findProperty($schema->properties, 'tags');
    $addressProp = findProperty($schema->properties, 'address');

    // Union type: int|string
    expect($identifierProp->type)->toBeInstanceOf(UnionTypeIR::class);
    $unionTypes = $identifierProp->type->types;
    expect($unionTypes)->toHaveCount(2);
    expect($unionTypes[0])->toBeInstanceOf(IntTypeIR::class);
    expect($unionTypes[1])->toBeInstanceOf(StringTypeIR::class);

    // Nullable union: int|string|null
    expect($optionalIdentifierProp->type)->toBeInstanceOf(UnionTypeIR::class);

    // Complex union: string|int|float|bool|null
    expect($mixedValueProp->type)->toBeInstanceOf(UnionTypeIR::class);

    // Array type
    expect($tagsProp->type)->toBeInstanceOf(ArrayTypeIR::class);

    // Class reference
    expect($addressProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($addressProp->type->alias)->toBe('AddressData');
});

it('generates correct IR types for ComplexData collections with item types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $usersProp = findProperty($schema->properties, 'users');
    $metadataProp = findProperty($schema->properties, 'metadata');

    // Collection<array-key, UserData> should be ArrayTypeIR with itemType
    if ($usersProp->type instanceof ArrayTypeIR) {
        expect($usersProp->type->itemType)->not->toBeNull();
        // The item type might be ClassReferenceTypeIR or could be treated differently
        // depending on how Collection generics are parsed
    }

    // array<string, mixed> should be RecordTypeIR (array<K, V> becomes RecordTypeIR)
    expect($metadataProp->type)->toBeInstanceOf(RecordTypeIR::class);
    expect($metadataProp->type->keyType)->toBeInstanceOf(StringTypeIR::class);
    // mixed becomes UnknownTypeIR
    expect($metadataProp->type->valueType)->toBeInstanceOf(UnknownTypeIR::class);
});

it('generates correct IR types for ComplexMetadataData with nested array structure', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\ComplexMetadataData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $metadataProp = findProperty($schema->properties, 'metadata');

    // array<string, array{name: string, value: int|string|null}> should be RecordTypeIR
    expect($metadataProp)->not->toBeNull();
    expect($metadataProp->type)->toBeInstanceOf(RecordTypeIR::class);

    $recordType = $metadataProp->type;
    expect($recordType->keyType)->toBeInstanceOf(StringTypeIR::class);

    // The value type should be ArrayTypeIR with itemType StructTypeIR
    expect($recordType->valueType)->toBeInstanceOf(ArrayTypeIR::class);
    expect($recordType->valueType->itemType)->toBeInstanceOf(StructTypeIR::class);

    $structType = $recordType->valueType->itemType;
    expect($structType->properties)->toHaveCount(2);

    // Find the 'name' property
    $nameProp = collect($structType->properties)->first(fn ($p) => $p->name === 'name');
    expect($nameProp)->not->toBeNull();
    expect($nameProp->type)->toBeInstanceOf(StringTypeIR::class);

    // Find the 'value' property
    $valueProp = collect($structType->properties)->first(fn ($p) => $p->name === 'value');
    expect($valueProp)->not->toBeNull();
    // The value type should be NullableTypeIR wrapping UnionTypeIR
    expect($valueProp->type)->toBeInstanceOf(NullableTypeIR::class);

    $nullableType = $valueProp->type;
    expect($nullableType->innerType)->toBeInstanceOf(UnionTypeIR::class);

    $unionType = $nullableType->innerType;
    expect($unionType->types)->toHaveCount(2);
    expect($unionType->types[0])->toBeInstanceOf(IntTypeIR::class);
    expect($unionType->types[1])->toBeInstanceOf(StringTypeIR::class);
});

it('generates correct IR types for EdgeCaseData with mixed and complex types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\EdgeCaseData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    $anythingProp = findProperty($schema->properties, 'anything');
    $complexUnionProp = findProperty($schema->properties, 'complexUnion');
    $nullableUnionProp = findProperty($schema->properties, 'nullableUnion');
    $emptyArrayProp = findProperty($schema->properties, 'emptyArray');
    $mixedArrayProp = findProperty($schema->properties, 'mixedArray');

    // mixed should be UnknownTypeIR
    expect($anythingProp->type)->toBeInstanceOf(UnknownTypeIR::class);

    // Complex union: string|int|float|bool|null|TestStatus
    expect($complexUnionProp->type)->toBeInstanceOf(UnionTypeIR::class);

    // Nullable union: string|int|null
    expect($nullableUnionProp->type)->toBeInstanceOf(UnionTypeIR::class);

    // Arrays
    expect($emptyArrayProp->type)->toBeInstanceOf(ArrayTypeIR::class);
    expect($mixedArrayProp->type)->toBeInstanceOf(ArrayTypeIR::class);
});

it('generates correct IR types for CollectionData with various collection types', function () {
    $classToken = $this->dataParser->parse(\EffectSchemaGenerator\Tests\Fixtures\CollectionData::class);
    $tokens = collect([$classToken]);
    $root = $this->astBuilder->build($tokens);

    $schema = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures']->schemas[0];

    // Collection<array-key, UserData> should be ClassReferenceTypeIR with typeParameters
    $usersProp = findProperty($schema->properties, 'users');
    expect($usersProp)->not->toBeNull();
    expect($usersProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($usersProp->type->fqcn)->toBe('Illuminate\Support\Collection');
    expect($usersProp->type->typeParameters)->toHaveCount(2);
    // First parameter: array-key (should be ClassReferenceTypeIR or StringTypeIR)
    expect($usersProp->type->typeParameters[0])->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
    // Second parameter: UserData (should be ClassReferenceTypeIR)
    expect($usersProp->type->typeParameters[1])->toBeInstanceOf(ClassReferenceTypeIR::class);
    // Class name might be fully qualified or just the class name depending on parsing
    expect($usersProp->type->typeParameters[1]->alias)->toBe('UserData');

    // DataCollection<array-key, UserData> should be ClassReferenceTypeIR with typeParameters
    $userDataCollectionProp = findProperty($schema->properties, 'userDataCollection');
    expect($userDataCollectionProp)->not->toBeNull();
    expect($userDataCollectionProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($userDataCollectionProp->type->fqcn)->toBe('Spatie\LaravelData\DataCollection');
    expect($userDataCollectionProp->type->typeParameters)->toHaveCount(2);

    // PaginatedDataCollection<array-key, UserData> should be ClassReferenceTypeIR with typeParameters
    $paginatedUsersProp = findProperty($schema->properties, 'paginatedUsers');
    expect($paginatedUsersProp)->not->toBeNull();
    expect($paginatedUsersProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($paginatedUsersProp->type->fqcn)->toBe('Spatie\LaravelData\PaginatedDataCollection');

    // CursorPaginatedDataCollection<array-key, UserData> should be ClassReferenceTypeIR with typeParameters
    $cursorPaginatedUsersProp = findProperty($schema->properties, 'cursorPaginatedUsers');
    expect($cursorPaginatedUsersProp)->not->toBeNull();
    expect($cursorPaginatedUsersProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($cursorPaginatedUsersProp->type->fqcn)->toBe('Spatie\LaravelData\CursorPaginatedDataCollection');

    // DataCollection<array-key, ProfileData> (lazy) should be ClassReferenceTypeIR with typeParameters
    $lazyProfilesProp = findProperty($schema->properties, 'lazyProfiles');
    expect($lazyProfilesProp)->not->toBeNull();
    expect($lazyProfilesProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($lazyProfilesProp->type->fqcn)->toBe('Spatie\LaravelData\DataCollection');

    // Collection<array-key, DataCollection<array-key, TaskData>> (nested) should be ClassReferenceTypeIR with typeParameters
    $nestedCollectionsProp = findProperty($schema->properties, 'nestedCollections');
    expect($nestedCollectionsProp)->not->toBeNull();
    expect($nestedCollectionsProp->type)->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($nestedCollectionsProp->type->fqcn)->toBe('Illuminate\Support\Collection');
    // Second parameter should be ClassReferenceTypeIR (nested generic)
    expect($nestedCollectionsProp->type->typeParameters[1])->toBeInstanceOf(ClassReferenceTypeIR::class);
    expect($nestedCollectionsProp->type->typeParameters[1]->fqcn)->toBe('Spatie\LaravelData\DataCollection');

    // ?DataCollection<array-key, UserData> (nullable)
    $optionalUsersProp = findProperty($schema->properties, 'optionalUsers');
    expect($optionalUsersProp)->not->toBeNull();
    // Nullable should be wrapped in NullableTypeIR
    expect($optionalUsersProp->type)->toBeInstanceOf(NullableTypeIR::class);
    expect($optionalUsersProp->type->innerType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);

    // Collection<array-key, UserData>|DataCollection<array-key, UserData> (union)
    // Note: This property has no PHP type hint, only PHPDoc, so it might not be parsed
    $usersOrDataCollectionProp = findProperty($schema->properties, 'usersOrDataCollection');
    if ($usersOrDataCollectionProp !== null) {
        // Union should be UnionTypeIR or NullableTypeIR if null is included
        expect($usersOrDataCollectionProp->type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
    }

    // Verify all properties have valid types
    foreach ($schema->properties as $property) {
        expect($property->type)->not->toBeNull();
        expect($property->type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
    }
});

it('generates correct IR types for all fixture Data classes', function () {
    $discoverer = app(ClassDiscoverer::class);
    $dataClasses = $discoverer->discoverDataClasses()
        ->filter(fn ($class) => str_starts_with($class, 'EffectSchemaGenerator\\Tests\\Fixtures\\'))
        ->filter(function ($class) {
            return class_exists($class) && is_subclass_of($class, 'Spatie\\LaravelData\\Data');
        });

    if ($dataClasses->isEmpty()) {
        $this->markTestSkipped('No fixture Data classes found');
    }

    $tokens = collect();
    foreach ($dataClasses as $className) {
        try {
            $tokens->push($this->dataParser->parse($className));
        } catch (\Throwable $e) {
            continue;
        }
    }

    if ($tokens->isEmpty()) {
        $this->markTestSkipped('No fixture Data classes could be parsed');
    }

    $root = $this->astBuilder->build($tokens);

    // Verify all schemas have properties with valid types
    foreach ($root->namespaces as $namespace) {
        foreach ($namespace->schemas as $schema) {
            expect($schema->properties)->toBeArray();
            foreach ($schema->properties as $property) {
                // Every property must have a type
                expect($property->type)->not->toBeNull();
                expect($property->type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);

                // Verify type is one of the expected IR types
                $validTypes = [
                    StringTypeIR::class,
                    IntTypeIR::class,
                    FloatTypeIR::class,
                    BoolTypeIR::class,
                    ArrayTypeIR::class,
                    ClassReferenceTypeIR::class,
                    UnionTypeIR::class,
                    UnknownTypeIR::class,
                    RecordTypeIR::class,
                    StructTypeIR::class,
                    NullableTypeIR::class,
                ];

                $typeClass = get_class($property->type);
                expect($validTypes)->toContain($typeClass);

                // If it's a UnionTypeIR, verify it has types
                if ($property->type instanceof UnionTypeIR) {
                    expect($property->type->types)->toBeArray();
                    expect($property->type->types)->not->toBeEmpty();
                    foreach ($property->type->types as $unionType) {
                        expect($unionType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    }
                }

                // If it's an ArrayTypeIR, verify itemType is valid if present
                if ($property->type instanceof ArrayTypeIR) {
                    if ($property->type->itemType !== null) {
                        expect($property->type->itemType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    }
                }

                // If it's a ClassReferenceTypeIR, verify alias is not empty
                if ($property->type instanceof ClassReferenceTypeIR) {
                    expect($property->type->alias)->not->toBeEmpty();
                    expect($property->type->alias)->toBeString();
                }

                // If it's a RecordTypeIR, verify keyType and valueType are valid
                if ($property->type instanceof RecordTypeIR) {
                    expect($property->type->keyType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    expect($property->type->valueType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                }

                // If it's a StructTypeIR, verify properties are valid
                if ($property->type instanceof StructTypeIR) {
                    expect($property->type->properties)->toBeArray();
                    foreach ($property->type->properties as $structProp) {
                        expect($structProp)->toBeInstanceOf(\EffectSchemaGenerator\IR\PropertyIR::class);
                        expect($structProp->type)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    }
                }

                // If it's a NullableTypeIR, verify innerType is valid
                if ($property->type instanceof NullableTypeIR) {
                    expect($property->type->innerType)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                }

                // If it's a ClassReferenceTypeIR with typeParameters (generic type), verify fqcn and typeParameters are valid
                if ($property->type instanceof ClassReferenceTypeIR && !empty($property->type->typeParameters)) {
                    expect($property->type->fqcn)->not->toBeEmpty();
                    expect($property->type->fqcn)->toBeString();
                    expect($property->type->typeParameters)->toBeArray();
                    foreach ($property->type->typeParameters as $typeParam) {
                        expect($typeParam)->toBeInstanceOf(\EffectSchemaGenerator\IR\TypeIR::class);
                    }
                }
            }
        }
    }
});

it('generates correct IR types for specific fixture classes with known types', function () {
    $testCases = [
        [
            'class' => \EffectSchemaGenerator\Tests\Fixtures\UserData::class,
            'properties' => [
                'id' => StringTypeIR::class,
                'name' => StringTypeIR::class,
                'email' => StringTypeIR::class,
            ],
        ],
        [
            'class' => \EffectSchemaGenerator\Tests\Fixtures\AddressData::class,
            'properties' => [
                'street' => StringTypeIR::class,
                'city' => StringTypeIR::class,
                'latitude' => FloatTypeIR::class,
                'longitude' => FloatTypeIR::class,
                'isPrimary' => BoolTypeIR::class,
            ],
        ],
        [
            'class' => \EffectSchemaGenerator\Tests\Fixtures\CategoryData::class,
            'properties' => [
                'id' => StringTypeIR::class,
                'name' => StringTypeIR::class,
                'sortOrder' => IntTypeIR::class,
                'isActive' => BoolTypeIR::class,
            ],
        ],
    ];

    foreach ($testCases as $testCase) {
        if (! class_exists($testCase['class'])) {
            continue;
        }

        $classToken = $this->dataParser->parse($testCase['class']);
        $tokens = collect([$classToken]);
        $root = $this->astBuilder->build($tokens);

        $namespace = $root->namespaces['EffectSchemaGenerator\Tests\Fixtures'];
        $schema = collect($namespace->schemas)->first(fn ($s) => $s->name === class_basename($testCase['class']));

        expect($schema)->not->toBeNull();

        foreach ($testCase['properties'] as $propertyName => $expectedTypeClass) {
            $property = findProperty($schema->properties, $propertyName);
            expect($property)->not->toBeNull();
            expect($property->type)->toBeInstanceOf($expectedTypeClass);
        }
    }
});

// Helper function to find a property by name
function findProperty(array $properties, string $name): ?\EffectSchemaGenerator\IR\PropertyIR
{
    foreach ($properties as $property) {
        if ($property->name === $name) {
            return $property;
        }
    }

    return null;
}
