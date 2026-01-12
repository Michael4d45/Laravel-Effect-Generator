# Laravel Effect Schema Generator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/michael4d45/effect-schema-generator.svg?style=flat-square)](https://packagist.org/packages/michael4d45/effect-schema-generator)
[![Total Downloads](https://img.shields.io/packagist/dt/michael4d45/effect-schema-generator.svg?style=flat-square)](https://packagist.org/packages/michael4d45/effect-schema-generator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/Michael4d45/Laravel-Effect-Generator/Tests?branch=main&label=tests&style=flat-square)](https://github.com/Michael4d45/Laravel-Effect-Generator/actions)

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
