<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\PropertyIR;
use EffectSchemaGenerator\IR\TypeIR;

/**
 * Converts TypeIR types to TypeScript string representations.
 */
class TypeScriptWriter
{
    /**
     * @param list<Transformer> $transformers
     */
    public function __construct(
        private array $transformers = [],
        private WriterContext $context = WriterContext::INTERFACE,
    ) {}

    /**
     * Convert a TypeIR to TypeScript string.
     *
     * @param TypeIR $type The type to convert
     * @return string The TypeScript type string
     */
    public function writeType(TypeIR $type): string
    {
        // Try transformers first
        foreach ($this->transformers as $transformer) {
            if (!$transformer->canTransform($type, $this->context)) {
                continue;
            }

            return $transformer->transform($type, $this->context);
        }

        // Fall back to default handling
        return $this->writeTypeDefault($type);
    }

    /**
     * Preprocess a property using transformers.
     */
    public function preprocessProperty(PropertyIR $property): void
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer->canTransform($property, $this->context)) {
                continue;
            }

            $transformer->transform($property, $this->context);
        }
    }

    /**
     * Default type conversion logic.
     *
     * @param TypeIR $type The type to convert
     * @return string The TypeScript type string
     */
    private function writeTypeDefault(TypeIR $type): string
    {
        return TypeTransformer::transform(
            $type,
            $this->context,
            true,
            fn(TypeIR $t) => $this->writeType($t),
        );
    }
}
