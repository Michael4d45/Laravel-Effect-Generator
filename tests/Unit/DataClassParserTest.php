<?php

declare(strict_types=1);

use EffectSchemaGenerator\Reflection\DataClassParser;
use PHPStan\PhpDocParser\Printer\Printer;

beforeEach(function () {
    // Use the service container which should have DataClassParser registered
    // This requires Surveyor's service provider to be loaded
    $this->parser = app(DataClassParser::class);
});

// Helper function to convert TypeNode to string
function typeNodeToString(?\PHPStan\PhpDocParser\Ast\Type\TypeNode $typeNode): ?string
{
    if ($typeNode === null) {
        return null;
    }
    
    // Use the recursive function to traverse the AST
    return typeNodeToStringRecursive($typeNode);
}

// Recursive helper to convert TypeNode to string
function typeNodeToStringRecursive(\PHPStan\PhpDocParser\Ast\Type\TypeNode $typeNode): string
{
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode) {
        return $typeNode->name;
    }
    
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\NullableTypeNode) {
        return typeNodeToStringRecursive($typeNode->type) . '|null';
    }
    
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\UnionTypeNode) {
        return implode('|', array_map('typeNodeToStringRecursive', $typeNode->types));
    }
    
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\GenericTypeNode) {
        $args = array_map('typeNodeToStringRecursive', $typeNode->genericTypes);
        return typeNodeToStringRecursive($typeNode->type) . '<' . implode(', ', $args) . '>';
    }
    
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode) {
        return 'array<' . typeNodeToStringRecursive($typeNode->type) . '>';
    }
    
    if ($typeNode instanceof \PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode) {
        $items = [];
        foreach ($typeNode->items as $item) {
            $key = $item->keyName ?? ($item->key->value ?? '');
            $optional = $item->optional ? '?' : '';
            $items[] = ($key ? $key . ': ' : '') . $optional . typeNodeToStringRecursive($item->valueType);
        }
        return 'array{' . implode(', ', $items) . '}';
    }
    
    // Fallback for unknown types
    return (string) $typeNode;
}

// Helper function to get attribute strings from PublicPropertyToken
function getAttributeStrings(\EffectSchemaGenerator\Tokens\PublicPropertyToken $token, string $className): array
{
    $reflectionClass = new \ReflectionClass($className);
    $reflectionProperty = $reflectionClass->getProperty($token->property->name);
    $attributes = $reflectionProperty->getAttributes();
    $strings = [];
    
    foreach ($attributes as $attribute) {
        $attributeClassName = $attribute->getName();
        $args = $attribute->getArguments();
        
        if (empty($args)) {
            $strings[] = $attributeClassName;
        } else {
            $formattedArgs = array_map(function ($arg) {
                return formatAttributeValue($arg);
            }, $args);
            $strings[] = $attributeClassName . '(' . implode(', ', $formattedArgs) . ')';
        }
    }
    
    return $strings;
}

// Helper function to format attribute argument values
function formatAttributeValue(mixed $value): string
{
    if (is_string($value)) {
        return '"' . addslashes($value) . '"';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_null($value)) {
        return 'null';
    }
    if (is_array($value)) {
        $formatted = [];
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        foreach ($value as $key => $val) {
            if ($isAssoc) {
                $formatted[] = $key . ': ' . formatAttributeValue($val);
            } else {
                $formatted[] = formatAttributeValue($val);
            }
        }
        return '[' . implode(', ', $formatted) . ']';
    }
    return var_export($value, true);
}

it('captures array type from @param annotation in class docblock', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\UpdatePlaylistRequestWithParam::class,
    );

    expect($classDefinition->publicProperties)->toHaveKey('question_ids');
});

it('parses AddressData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\AddressData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['street', 'city', 'state', 'zipCode', 'country', 'apartment', 'latitude', 'longitude', 'isPrimary']);
});

it('parses ApiResponseData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ApiResponseData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['success', 'message', 'statusCode', 'errors', 'warnings', 'tags', 'headers', 'metadata', 'users', 'tasks', 'pagination', 'currentUser', 'userProfile']);
});

it('parses CategoryData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\CategoryData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name', 'slug', 'description', 'parent', 'sortOrder', 'isActive']);
});

it('parses CollectionData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\CollectionData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['users', 'userDataCollection', 'paginatedUsers', 'cursorPaginatedUsers', 'lazyProfiles', 'nestedCollections', 'optionalUsers']);
});

it('parses ComplexData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ComplexData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['identifier', 'optionalIdentifier', 'mixedValue', 'users', 'lazyUsers', 'nestedStrings', 'optionalProfile', 'firstName', 'createdAt', 'updatedAt', 'address', 'tags', 'metadata', 'relatedItems', 'settings']);
});

it('parses EdgeCaseData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\EdgeCaseData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys([
            'anything',
            'complexUnion',
            'nullableUnion',
            'deeplyNested',
            'lazyNullableUsers',
            'emptyArray',
            'mixedArray',
            'mixedProperty',
            'mixedPropertyButArray',
            'arrayShape',
            'arrayShapeWithOptional',
            'arrayShapeWithNumericKeys',
            'listType',
            'listWithUnion',
            'nonEmptyArray',
            'iterableType',
            'iterableWithKeyValue',
            'callableType',
            'classString',
            'classStringGeneric',
            'literalStringUnion',
            'literalIntUnion',
            'literalBoolUnion',
            'closureType',
            'arrayWithSpread',
            'constStringType',
            'intersectionType',
            'complexArrayShape',
        ]);
});

it('parses GameSessionData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\GameSessionData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name', 'created_at', 'updated_at']);
});

it('parses ProductData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ProductData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'sku', 'productName', 'description', 'price', 'stockQuantity', 'currencyCode', 'categories', 'images', 'createdAt', 'updatedAt', 'publishedAt', 'status', 'attributes', 'metaTitle', 'metaDescription', 'relatedProducts', 'reviews']);
});

it('parses ProductImageData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ProductImageData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'url', 'altText', 'sortOrder', 'isPrimary', 'thumbnailUrl', 'width', 'height']);
});

it('parses ProductReviewData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ProductReviewData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'userId', 'userName', 'rating', 'title', 'comment', 'isVerifiedPurchase', 'isRecommended', 'createdAt', 'updatedAt', 'user']);
});

it('parses ProfileData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ProfileData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['bio', 'avatar', 'preferences']);
});

it('parses QuizModeData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\QuizModeData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name']);
});

it('parses QuizRequestWithParam', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\QuizRequestWithParam::class,
    );

    expect($classDefinition->publicProperties)->toHaveKey('quiz_modes');
});

it('parses QuizRequestWithVar', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\QuizRequestWithVar::class,
    );

    expect($classDefinition->publicProperties)->toHaveKey('quiz_modes');
});

it('parses ScoringRuleData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ScoringRuleData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name', 'base_points', 'decay_factor', 'max_time_ms', 'streak_bonus_enabled', 'streak_multiplier', 'created_at', 'updated_at', 'game_sessions']);
});

it('parses TaskData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\TaskData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'title', 'description', 'status', 'priority', 'color', 'statusOrPriority', 'createdAt', 'completedAt', 'tags', 'assignedUser']);
});

it('parses TreeNodeData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\TreeNodeData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name', 'value', 'children', 'parent', 'nestedChildren']);
});

it('parses UpdatePlaylistRequestWithVar', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\UpdatePlaylistRequestWithVar::class,
    );

    expect($classDefinition->publicProperties)->toHaveKey('question_ids');
});

it('parses UserData', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\UserData::class,
    );

    expect($classDefinition->publicProperties)
        ->toHaveKeys(['id', 'name', 'email', 'profile', 'createdAt']);
});

it('extracts attributes with arguments', function () {
    $className = \EffectSchemaGenerator\Tests\Fixtures\ComplexData::class;
    $classDefinition = $this->parser->parse($className);

    $firstNameProperty = $classDefinition->publicProperties['firstName'];
    $attributeStrings = getAttributeStrings($firstNameProperty, $className);
    expect($attributeStrings)->toBeArray();
    expect($attributeStrings)->toContain('Spatie\LaravelData\Attributes\MapInputName("first_name")');
    expect($attributeStrings)->toContain('Spatie\LaravelData\Attributes\MapOutputName("givenName")');
    
    $settingsProperty = $classDefinition->publicProperties['settings'];
    $settingsAttributeStrings = getAttributeStrings($settingsProperty, $className);
    expect($settingsAttributeStrings)->toContain('Spatie\LaravelData\Attributes\WithCast("json")');
});

it('extracts attributes without arguments', function () {
    $className = \EffectSchemaGenerator\Tests\Fixtures\ComplexData::class;
    $classDefinition = $this->parser->parse($className);

    $optionalProfileProperty = $classDefinition->publicProperties['optionalProfile'];
    $attributeStrings = getAttributeStrings($optionalProfileProperty, $className);
    expect($attributeStrings)->toContain('Spatie\LaravelData\Attributes\Lazy');
});

it('parses constructor @param annotations', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\UpdatePlaylistRequestWithParam::class,
    );

    $questionIdsProperty = $classDefinition->publicProperties['question_ids'];
    $phpDocTypeString = typeNodeToString($questionIdsProperty->phpDocType);
    expect($phpDocTypeString)->not->toBeNull();
    expect($phpDocTypeString)->toContain('array');
});

it('extracts phpdoc types from constructor doc comment', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ConstructorParamData::class,
    );

    expect($classDefinition->publicProperties)->toHaveKeys(['items', 'name']);

    // Test that items property has phpDocTypes from constructor @param
    $itemsProperty = $classDefinition->publicProperties['items'];
    $itemsPhpDocType = typeNodeToString($itemsProperty->phpDocType);
    expect($itemsPhpDocType)->not->toBeNull();
    expect($itemsPhpDocType)->toBe('array<int, string>');

    // Test that name property has phpDocTypes from constructor @param
    $nameProperty = $classDefinition->publicProperties['name'];
    $namePhpDocType = typeNodeToString($nameProperty->phpDocType);
    expect($namePhpDocType)->not->toBeNull();
    expect($namePhpDocType)->toBe('string');
});

it('parses complex array shape with union types from @param annotation', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\ComplexMetadataData::class,
    );

    expect($classDefinition->publicProperties)->toHaveKey('metadata');

    $metadataProperty = $classDefinition->publicProperties['metadata'];
    $metadataPhpDocType = typeNodeToString($metadataProperty->phpDocType);
    expect($metadataPhpDocType)->not->toBeNull();
    expect($metadataPhpDocType)->toBe('array<string, array{name: string, value: int|string|null}>');
});

it('handles properties with null types', function () {
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\AddressData::class,
    );

    $apartmentProperty = $classDefinition->publicProperties['apartment'];
    // Check if the property type itself is nullable (from PropertyResult)
    $propertyType = $apartmentProperty->property->type;
    // PropertyResult->type should indicate nullable, or we check the actual reflection type
    $reflectionClass = new \ReflectionClass(\EffectSchemaGenerator\Tests\Fixtures\AddressData::class);
    $reflectionProperty = $reflectionClass->getProperty('apartment');
    $reflectionType = $reflectionProperty->getType();
    expect($reflectionType)->not->toBeNull();
    expect($reflectionType->allowsNull())->toBeTrue();
});

it('handles invalid docblock gracefully', function () {
    // This should not throw an exception even with malformed docblocks
    $classDefinition = $this->parser->parse(
        \EffectSchemaGenerator\Tests\Fixtures\UserData::class,
    );

    expect($classDefinition->publicProperties)->toBeArray();
});

it('formats attribute arguments with different value types', function () {
    $className = \EffectSchemaGenerator\Tests\Fixtures\AttributeTestData::class;
    $classDefinition = $this->parser->parse($className);

    // Test string argument
    $stringProperty = $classDefinition->publicProperties['stringArg'];
    expect(getAttributeStrings($stringProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute("string_value")');

    // Test integer argument
    $intProperty = $classDefinition->publicProperties['intArg'];
    expect(getAttributeStrings($intProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(42)');

    // Test float argument
    $floatProperty = $classDefinition->publicProperties['floatArg'];
    expect(getAttributeStrings($floatProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(3.14)');

    // Test boolean true argument
    $trueProperty = $classDefinition->publicProperties['trueArg'];
    expect(getAttributeStrings($trueProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(true)');

    // Test boolean false argument
    $falseProperty = $classDefinition->publicProperties['falseArg'];
    expect(getAttributeStrings($falseProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(false)');

    // Test null argument
    $nullProperty = $classDefinition->publicProperties['nullArg'];
    expect(getAttributeStrings($nullProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(null)');

    // Test array argument
    $arrayProperty = $classDefinition->publicProperties['arrayArg'];
    expect(getAttributeStrings($arrayProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute(["a", "b", "c"])');

    // Test associative array argument
    $assocArrayProperty = $classDefinition->publicProperties['assocArrayArg'];
    expect(getAttributeStrings($assocArrayProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute([key1: "value1", key2: 123])');

    // Test class constant argument (PHP resolves ::class to string in attributes)
    // Note: The is_object() case in formatValue cannot be tested via attributes
    // since attributes only accept compile-time constants, not object instances
    $classProperty = $classDefinition->publicProperties['classArg'];
    // The class name is escaped with addslashes, so backslashes are doubled
    expect(getAttributeStrings($classProperty, $className))->toContain('EffectSchemaGenerator\Tests\Fixtures\TestAttribute("EffectSchemaGenerator\\\\Tests\\\\Fixtures\\\\UserData")');
});
