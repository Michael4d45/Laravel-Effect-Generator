<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Attributes;

use Attribute;

/**
 * Marks a property as hidden from generated TypeScript interfaces and schemas.
 *
 * When used on a property, that property is omitted from the generated output.
 *
 * @example
 * ```php
 * use EffectSchemaGenerator\Attributes\Hidden;
 *
 * class UserData extends Data
 * {
 *     public string $name;
 *
 *     #[Hidden]
 *     public string $internalId;  // Not included in generated schema
 * }
 * ```
 *
 * When used on a class, all properties become hidden by default (useful for
 * base classes or DTOs that are not fully exposed).
 *
 * @example
 * ```php
 * #[Hidden]
 * class InternalDto extends Data
 * {
 *     public string $secret;  // Not included
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class Hidden
{
    public function __construct() {}
}
