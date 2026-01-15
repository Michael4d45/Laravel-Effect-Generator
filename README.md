# Laravel Effect Schema Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/michael4d45/effect-schema-generator.svg?style=flat-square)](https://packagist.org/packages/michael4d45/effect-schema-generator) [![Total Downloads](https://img.shields.io/packagist/dt/michael4d45/effect-schema-generator.svg?style=flat-square)](https://packagist.org/packages/michael4d45/effect-schema-generator) [![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Michael4d45/Laravel-Effect-Generator/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/Michael4d45/Laravel-Effect-Generator/actions)

Generate TypeScript interfaces and Effect schemas from PHP Spatie Data classes. This package bridges your PHP domain models to your TypeScript frontend contracts by generating AST-based representations and transforming them into valid TypeScript code.

## Installation

You can install the package via composer:

```bash
composer require michael4d45/effect-schema-generator
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="effect-schema-config"
```

## Usage

Define your Spatie Data classes as usual:

```php
namespace App\Data;

use Spatie\LaravelData\Data;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
    ) {}
}
```

Then run the generator command:

```bash
php artisan effect-schema:transform
```

This will generate TypeScript interfaces and Effect schemas in your configured output directory (defaults to `resources/ts/schemas`).

## Commands

```bash
php artisan effect-schema:transform        # Generate schemas
php artisan effect-schema:transform --dry-run # Preview output
```

## Features

- **AST-based generation**: Robust transformation from PHP to TypeScript.
- **Spatie Data support**: Deep integration with `spatie/laravel-data`.
- **Effect Schema**: Generates not just interfaces, but runtime-validatable Effect schemas.
- **Configurable Transformers**: Easily extend the generator with custom type mappings.
- **PHPDoc Parsing**: Uses PHPDoc annotations to refine or override types.

## Generated File Structure

The generator creates TypeScript files that mirror your PHP namespace structure. For example:

```
PHP Namespaces:                  Generated TypeScript:
├── App\Data\                  ├── resources/ts/schemas/
│   ├── UserData.php           │   └── App/Data.ts
│   └── Models\                │   └── App/Data/Models.ts
│       └── GameSessionData    │
└── App\Enums\                 └── App/Enums.ts
    └── Role.php
```

Each TypeScript file contains:

- TypeScript interfaces (for the decoded types)
- Encoded interfaces (for the serialized types)
- Effect Schema definitions (for runtime validation)

### Example Output

Given this PHP Data class:

```php
namespace App\Data;

use Spatie\LaravelData\Data;
use Carbon\Carbon;

class UserData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public bool $is_admin,
        public ?Carbon $email_verified_at,
    ) {}
}
```

The generator produces this TypeScript file (`App/Data.ts`):

```typescript
import { Schema as S } from "effect";

export interface UserData {
  readonly id: number;
  readonly name: string;
  readonly email: string;
  readonly is_admin: boolean;
  readonly email_verified_at: Date | null;
}

export interface UserDataEncoded {
  readonly id: number;
  readonly name: string;
  readonly email: string;
  readonly is_admin: boolean;
  readonly email_verified_at: string | null;
}

export const UserDataSchema = S.Struct({
  id: S.Number,
  name: S.String,
  email: S.String,
  is_admin: S.Boolean,
  email_verified_at: S.NullOr(S.DateFromString),
});
```

### Complex Types

The generator handles complex types, relationships, and collections:

```php
namespace App\Data;

use Spatie\LaravelData\Data;
use Illuminate\Support\Collection;

class ApiResponseData extends Data
{
    /**
     * @param Collection<int, UserData> $users
     */
    public function __construct(
        public Collection $users,
        public ?UserData $currentUser,
    ) {}
}
```

Generates:

```typescript
import { Schema as S } from "effect";
import { UserData, UserDataEncoded, UserDataSchema } from "./User";

export interface ApiResponseData {
  readonly users: readonly UserData[];
  readonly currentUser: UserData | null;
}

export interface ApiResponseDataEncoded {
  readonly users: readonly UserDataEncoded[];
  readonly currentUser: UserDataEncoded | null;
}

export const ApiResponseDataSchema = S.Struct({
  users: S.Array(
    S.suspend((): S.Schema<UserData, UserDataEncoded> => UserDataSchema)
  ),
  currentUser: S.NullOr(
    S.suspend((): S.Schema<UserData, UserDataEncoded> => UserDataSchema)
  ),
});
```

### Enums

PHP enums are converted to TypeScript type unions with corresponding schemas:

```php
namespace App\Enums;

enum Role: string
{
    case HOST = 'host';
    case PLAYER = 'player';
    case SPECTATOR = 'spectator';
}
```

Generates:

```typescript
import { Schema as S } from "effect";

export type Role = "host" | "player" | "spectator";
export const RoleSchema = S.Union(
  S.Literal("host"),
  S.Literal("player"),
  S.Literal("spectator")
);
```

## Configuration

See `config/effect-schema.php` for all available options, including:

- `paths`: Directories to scan for Data classes.
- `transformers`: Custom type transformation logic.
- `output_directory`: Where to save the generated TypeScript files.

## Testing

```bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
