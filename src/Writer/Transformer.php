<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\EnumIR;
use EffectSchemaGenerator\IR\SchemaIR;
use EffectSchemaGenerator\IR\TypeIR;

/**
 * Unified interface for transforming types and generating output.
 * Replaces both TypePlugin and writer interfaces.
 */
interface Transformer
{
    /**
     * Check if this transformer can handle the given type in the given context.
     *
     * @param TypeIR|SchemaIR|EnumIR $input The input to check
     * @param WriterContext $context The output context
     * @param array $attributes Attribute context (e.g., ['class' => AttributeIR[], 'property' => AttributeIR[]])
     * @return bool True if this transformer can handle it
     */
    public function canTransform(
        $input,
        WriterContext $context,
        array $attributes = [],
    ): bool;

    /**
     * Transform the input to a string representation for the given context.
     *
     * @param TypeIR|SchemaIR|EnumIR $input The input to transform
     * @param WriterContext $context The output context
     * @param array $attributes Attribute context (e.g., ['class' => AttributeIR[], 'property' => AttributeIR[]])
     * @return string The transformed output
     */
    public function transform(
        $input,
        WriterContext $context,
        array $attributes = [],
    ): string;

    /**
     * Check if this transformer provides additional file content.
     */
    public function providesFile(): bool;

    /**
     * Get additional TypeScript file content (e.g., utility types, schemas).
     */
    public function getFileContent(): null|string;

    /**
     * Get the file path where additional content should be written.
     */
    public function getFilePath(): null|string;
}
