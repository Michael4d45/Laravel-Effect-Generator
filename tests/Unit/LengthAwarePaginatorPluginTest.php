<?php

declare(strict_types=1);

use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin;
use EffectSchemaGenerator\Writer\TypeScriptWriter;
use EffectSchemaGenerator\Writer\WriterContext;

beforeEach(function () {
    $this->plugin = new LengthAwarePaginatorPlugin();
    $this->writer = new TypeScriptWriter([$this->plugin]);
});

it('can transform LengthAwarePaginator type in interface context', function () {
    $type = new ClassReferenceTypeIR('Illuminate\Pagination\LengthAwarePaginator');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeTrue();
});

it('cannot transform non-LengthAwarePaginator types in interface context', function () {
    $type = new ClassReferenceTypeIR('App\Data\UserData');

    expect($this->plugin->canTransform($type, WriterContext::INTERFACE))->toBeFalse();
});

it('transforms LengthAwarePaginator with one type parameter', function () {
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $type = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        [$itemType]
    );
    
    $result = $this->plugin->transform($type, WriterContext::INTERFACE);
    
    expect($result)->toBe('LengthAwarePaginator<UserData>');
});

it('transforms LengthAwarePaginator with two type parameters', function () {
    $keyType = new StringTypeIR();
    $itemType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $type = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        [$keyType, $itemType]
    );
    
    $result = $this->plugin->transform($type, WriterContext::INTERFACE);
    
    // Should use the second parameter (index 1) which is the data type
    expect($result)->toBe('LengthAwarePaginator<UserData>');
});

it('transforms LengthAwarePaginator with primitive type parameter', function () {
    $keyType = new StringTypeIR();
    $itemType = new StringTypeIR();
    $type = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        [$keyType, $itemType]
    );
    
    $result = $this->plugin->transform($type, WriterContext::INTERFACE);
    
    expect($result)->toBe('LengthAwarePaginatorSchema(S.String)');
});

it('transforms LengthAwarePaginator without type parameters', function () {
    $type = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        []
    );
    
    $result = $this->plugin->transform($type, WriterContext::INTERFACE);
    
    expect($result)->toBe('LengthAwarePaginator<unknown>');
});

it('provides correct file path', function () {
    expect($this->plugin->getFilePath())->toBe('Illuminate/Pagination.ts');
});

it('provides file content with all required types', function () {
    $content = $this->plugin->getFileContent();
    
    expect($content)->toContain('export interface PaginationLinks');
    expect($content)->toContain('export interface PaginationMeta');
    expect($content)->toContain('export interface LengthAwarePaginator<T extends object>');
    expect($content)->toContain('url: string | null');
    expect($content)->toContain('label: string');
    expect($content)->toContain('page: number | null');
    expect($content)->toContain('active: boolean');
    expect($content)->toContain('current_page: number');
    expect($content)->toContain('data: readonly T[]');
    expect($content)->toContain('links: readonly PaginationLinks[]');
    expect($content)->toContain('readonly meta: PaginationMeta');
});

it('implements Transformer interface', function () {
    expect($this->plugin)->toBeInstanceOf(\EffectSchemaGenerator\Writer\Transformer::class);
});

it('generates exact TypeScript file content', function () {
    $content = $this->plugin->getFileContent();
    
    $expected = <<<'TS'
import { Schema as S } from 'effect';

export interface PaginationLinks {
    readonly url: string | null;
    readonly label: string;
    readonly page: number | null;
    readonly active: boolean;
}

export const PaginationLinksSchema = S.Struct({
    url: S.NullOr(S.String),
    label: S.String,
    page: S.NullOr(S.Number),
    active: S.Boolean,
});

export interface PaginationMeta {
    readonly current_page: number;
    readonly first_page_url: string;
    readonly from: number | null;
    readonly last_page: number;
    readonly last_page_url: string;
    readonly next_page_url: string | null;
    readonly path: string;
    readonly per_page: number;
    readonly prev_page_url: string | null;
    readonly to: number | null;
    readonly total: number;
}

export const PaginationMetaSchema = S.Struct({
    current_page: S.Number,
    first_page_url: S.String,
    from: S.NullOr(S.Number),
    last_page: S.Number,
    last_page_url: S.String,
    next_page_url: S.NullOr(S.String),
    path: S.String,
    per_page: S.Number,
    prev_page_url: S.NullOr(S.String),
    to: S.NullOr(S.Number),
    total: S.Number,
});

export interface LengthAwarePaginator<T extends object> {
    readonly data: readonly T[];
    readonly links: readonly PaginationLinks[];
    readonly meta: PaginationMeta;
}

export const LengthAwarePaginatorSchema = <A extends S.Schema.Any>(item: A) =>
    S.Struct({
        data: S.Array(item),
        links: S.Array(PaginationLinksSchema),
        meta: PaginationMetaSchema,
    });
TS;
    
    expect($content)->toBe($expected);
});

it('transforms to exact TypeScript output', function () {
    $userType = new ClassReferenceTypeIR('App\Data\UserData', 'UserData');
    $paginatorType = new ClassReferenceTypeIR(
        'Illuminate\Pagination\LengthAwarePaginator',
        'LengthAwarePaginator',
        [new StringTypeIR(), $userType]
    );
    
    $result = $this->plugin->transform($paginatorType, WriterContext::SCHEMA);
    
    expect($result)->toBe('LengthAwarePaginatorSchema(S.suspend((): S.Schema<UserData, UserDataEncoded> => UserDataSchema))');
});
