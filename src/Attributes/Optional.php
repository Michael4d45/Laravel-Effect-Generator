<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Attributes;

use Attribute;

/**
 * Marks a property as optional in generated TypeScript interfaces and schemas.
 *
 * This attribute can be used instead of Spatie Laravel Data's Optional attribute
 * to decouple from external dependencies.
 *
 * @example
 * ```php
 * use EffectSchemaGenerator\Attributes\Optional;
 *
 * class UserData extends Data
 * {
 *     public string $name;
 *
 *     #[Optional]
 *     public string $nickname;
 * }
 * ```
 *
 * This will generate:
 * ```typescript
 * interface UserData {
 *   readonly name: string;
 *   readonly nickname?: string;
 * }
 * ```
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Optional
{
    public function __construct()
    {
        // No parameters needed for this simple attribute
    }
}
