# Changelog

All notable changes to `michael4d45/effect-schema-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.2] - 2026-01-15

### Added

- Clear cache command.
- Support for unioned lazy types.

## [0.1.1] - 2026-01-15

### Added

- Updated LazyPlugin to include optional functionality, renamed to LazyOptionalPlugin.
- Updated default configuration to use LazyOptionalPlugin instead of LazyPlugin.

## [0.1.0] - 2026-01-12

### Added

- Initial release of Laravel Effect Schema Generator.
- Support for Spatie Data classes.
- Support for PHP 8.1+ Enums.
- Generation of TypeScript interfaces.
- Generation of Effect schemas.
- Built-in transformers for Collections, Dates, and Lazy types.
- Artisan command `effect-schema:transform`.
- Dry-run mode for previewing output.
