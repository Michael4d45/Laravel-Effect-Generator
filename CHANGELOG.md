# Changelog

All notable changes to `michael4d45/effect-schema-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.2] - 2026-02-11

### Fixed

- Fixed use statement collection for trait-based properties by merging use statements from parent classes and traits to enable proper PHPDoc type resolution.

## [0.2.1] - 2026-02-10

### Fixed

- Fixed encoded interface readonly issue.

## [0.2.0] - 2026-02-02

### Changed

- **Breaking:** Updated LengthAwarePaginatorPlugin to use `PaginationLink` (singular) instead of `PaginationLinks` (plural) for consistency with Laravel's naming.
- Made the `page` property optional in `PaginationLink` interface to better reflect Laravel's pagination structure.

## [0.1.9] - 2026-02-01

### Changed

- Changed retry logic to clear Surveyor cache and retry once on any error during schema generation, instead of only on `filemtime(): stat failed` errors.

## [0.1.8] - 2026-01-27

### Fixed

- Fixed type inference for trait-based properties by using PHP reflection to extract native property types instead of defaulting to string type.

## [0.1.7] - 2026-01-27

### Added

- Support for parsing inherited properties from parent classes in Data class hierarchies.

## [0.1.4] - 2026-01-26

### Fixed

- Fixed PHPDoc `list<T>` type handling to generate proper TypeScript array types instead of treating 'list' as a class reference.

## [0.1.3] - 2026-01-23

### Fixed

- Automatically clear the Surveyor cache and retry once when a `filemtime(): stat failed` error occurs during generation.

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
