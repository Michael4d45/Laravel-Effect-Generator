<?php

declare(strict_types=1);

use EffectSchemaGenerator\Tokens\EnumToken;

it('can be instantiated', function () {
    $token = new EnumToken(
        fqcn: 'Test\Namespace\MyEnum',
        namespace: 'Test\Namespace',
        backedType: 'string',
        cases: ['CASE1', 'CASE2'],
    );

    expect($token->fqcn)->toBe('Test\Namespace\MyEnum');
    expect($token->namespace)->toBe('Test\Namespace');
    expect($token->backedType)->toBe('string');
    expect($token->cases)->toBe(['CASE1', 'CASE2']);
});
