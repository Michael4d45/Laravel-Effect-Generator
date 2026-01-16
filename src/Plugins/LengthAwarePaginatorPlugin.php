<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Plugins;

use EffectSchemaGenerator\IR\TypeIR;
use EffectSchemaGenerator\IR\Types\ArrayTypeIR;
use EffectSchemaGenerator\IR\Types\BoolTypeIR;
use EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR;
use EffectSchemaGenerator\IR\Types\FloatTypeIR;
use EffectSchemaGenerator\IR\Types\IntTypeIR;
use EffectSchemaGenerator\IR\Types\NullableTypeIR;
use EffectSchemaGenerator\IR\Types\StringTypeIR;
use EffectSchemaGenerator\IR\Types\UnionTypeIR;
use EffectSchemaGenerator\Writer\Transformer;
use EffectSchemaGenerator\Writer\TypeTransformer;
use EffectSchemaGenerator\Writer\WriterContext;

class LengthAwarePaginatorPlugin implements Transformer
{
    public function canTransform($input, WriterContext $context): bool
    {
        return $input instanceof TypeIR && $this->handles($input);
    }

    public function transform($input, WriterContext $context): string
    {
        if (
            !$input instanceof TypeIR || !$input instanceof ClassReferenceTypeIR
        ) {
            return 'unknown';
        }

        // Get the type parameter (usually the first one is the data type)
        // LengthAwarePaginator has two type parameters: array-key and T
        // We want the second one (index 1) which is the data type
        $typeParam = null;
        if (count($input->typeParameters) >= 2) {
            $typeParam = $input->typeParameters[1]; // Second parameter is the data type
        } elseif (count($input->typeParameters) === 1) {
            $typeParam = $input->typeParameters[0]; // Fallback to first if only one
        }

        if ($typeParam !== null) {
            // Check if the type parameter is a primitive type
            $isPrimitive = $this->isPrimitiveType($typeParam);

            if ($isPrimitive) {
                $schemaType = $this->transformTypeParamToSchema(
                    $typeParam,
                    $context,
                );

                return match ($context) {
                    WriterContext::INTERFACE
                        => "LengthAwarePaginatorSchema({$schemaType})",
                    WriterContext::ENCODED_INTERFACE
                        => "LengthAwarePaginatorSchema({$schemaType})",
                    WriterContext::SCHEMA
                        => "LengthAwarePaginatorSchema({$schemaType})",
                    WriterContext::ENUM
                        => "LengthAwarePaginatorSchema({$schemaType})",
                };
            }

            $innerType = $this->transformTypeParam($typeParam, $context);

            return match ($context) {
                WriterContext::INTERFACE
                    => "LengthAwarePaginator<{$innerType}>",
                WriterContext::ENCODED_INTERFACE
                    => "LengthAwarePaginator<{$innerType}>",
                WriterContext::SCHEMA
                    => "LengthAwarePaginatorSchema({$innerType})",
                WriterContext::ENUM => "LengthAwarePaginator<{$innerType}>",
            };
        }

        return match ($context) {
            WriterContext::INTERFACE => 'LengthAwarePaginator<unknown>',
            WriterContext::ENCODED_INTERFACE => 'LengthAwarePaginator<unknown>',
            WriterContext::SCHEMA => 'LengthAwarePaginatorSchema(S.Unknown)',
            WriterContext::ENUM => 'LengthAwarePaginator<unknown>',
        };
    }

    public function providesFile(): bool
    {
        return true;
    }

    public function getFileContent(): null|string
    {
        return <<<'TS'
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
    }

    public function getFilePath(): null|string
    {
        return 'Illuminate/Pagination.ts';
    }

    private function handles(TypeIR $type): bool
    {
        return (
            $type instanceof ClassReferenceTypeIR
            && $type->fqcn === 'Illuminate\Pagination\LengthAwarePaginator'
        );
    }

    private function transformTypeParam(
        TypeIR $typeParam,
        WriterContext $context,
    ): string {
        return TypeTransformer::transform($typeParam, $context);
    }

    private function isPrimitiveType(TypeIR $type): bool
    {
        return (
            $type instanceof StringTypeIR
            || $type instanceof IntTypeIR
            || $type instanceof FloatTypeIR
            || $type instanceof BoolTypeIR
            || $type instanceof ArrayTypeIR
            || $type instanceof UnionTypeIR
            || $type instanceof NullableTypeIR
        );
    }

    private function transformTypeParamToSchema(
        TypeIR $typeParam,
        WriterContext $context,
    ): string {
        return TypeTransformer::toEffectSchema($typeParam);
    }
}
