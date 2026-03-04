<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Attributes;

use Attribute;

/**
 * Transforms property names to snake_case in generated TypeScript interfaces and schemas.
 *
 * When used on a property, only that property's name is transformed:
 * @example
 * ```php
 * use EffectSchemaGenerator\Attributes\SnakeCase;
 *
 * class UserData extends Data
 * {
 *     #[SnakeCase]
 *     public string $firstName;
 *
 *     public string $lastName;
 * }
 * ```
 *
 * This will generate:
 * ```typescript
 * interface UserData {
 *   readonly first_name: string;
 *   readonly lastName: string;
 * }
 * ```
 *
 * When used on a class, all property names are transformed:
 * @example
 * ```php
 * #[SnakeCase]
 * class UserData extends Data
 * {
 *     public string $firstName;
 *     public string $lastName;
 * }
 * ```
 *
 * This will generate:
 * ```typescript
 * interface UserData {
 *   readonly first_name: string;
 *   readonly last_name: string;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_CLASS)]
class SnakeCase
{
    public function __construct() {}
}
