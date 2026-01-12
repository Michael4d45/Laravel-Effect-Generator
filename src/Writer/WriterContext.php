<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Context indicating what kind of output is being generated.
 */
enum WriterContext
{
    case INTERFACE;
    case ENCODED_INTERFACE;
    case SCHEMA;
    case ENUM;
}
