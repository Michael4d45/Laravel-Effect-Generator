# Changelog

All notable changes to `michael4d45/effect-schema-generator` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.4] - 2026-03-05

### Added

- Added `#[Hidden]` attribute (`EffectSchemaGenerator\Attributes\Hidden`) to exclude properties from generated TypeScript interfaces and schemas. Supports both property-level and class-level usage.
- Added `HiddenPlugin` — marks properties with `#[Hidden]` or Spatie's `#[Computed]` so they are omitted from generated output (computed properties are derived at runtime and not part of the serialized payload). Registered by default in config.

## [0.5.3] - 2026-03-05

### Added

- Added `effect-schema:debug-imports` command to diagnose schema import behaviour. Run with an optional Data class FQCN (e.g. `php artisan effect-schema:debug-imports "App\Features\Recipe\Responses\ListRecipesResponse"`). The command prints: configured transformers in order; all referenced types for that schema; for each type, whether a transformer provides its file (path or `null` — when `null`, the writer adds `*Encoded` to imports); and the imports actually added by `EffectSchemaSchemaWriter`. Use it to trace why a type like `LengthAwarePaginatorEncoded` appears when the Pagination plugin does not export it (e.g. transformer path missing or wrong config).

## [0.5.2] - 2026-03-03

### Added

- Added `#[SnakeCase]` attribute (`EffectSchemaGenerator\Attributes\SnakeCase`) for opt-in snake_case conversion of property names in generated TypeScript output. Supports both property-level and class-level usage.
- Added `SnakeCaseAttributePlugin` — transforms property names to snake_case only when the `#[SnakeCase]` attribute is present on the property or class. Registered by default in config.
- Added `SnakeCasePlugin` — unconditionally transforms all property names to snake_case globally. Can be swapped in via config for projects that want snake_case everywhere without attributes.

## [0.5.1] - 2026-03-03

### Fixed

- Output type imports. e.g. `import { type User } from './UserData.php'` rather than  `import { User } from './UserData.php'`

## [0.5.0] - 2026-03-03

### Added

- Added `output.clear_output_directory_before_write` config option to delete existing output directory contents before generating new schema files.

## [0.4.1] - 2026-03-02

### Fixed

- Fixed missing sibling imports for unqualified class references (for example PHPDoc `EffortData` inside array properties).
- Updated schema/interface import resolution to treat unqualified class names as same-directory type files.
- Added regression coverage for unqualified sibling array references.

## [0.4.0] - 2026-03-02

### Added

- Added `OutputPathResolver` to centralize namespace and FQCN to output path mapping.

### Changed

- **Breaking:** Switched schema/enum generation from namespace-level files to per-type files.
- Output paths now mirror PHP namespace and type names directly (for example `App\\Data\\Events\\SessionEventOccurredData` -> `App/Data/Events/SessionEventOccurredData.ts`).
- Updated import resolution to use deterministic FQCN-based target file paths.
- Updated unit and integration tests to validate the new per-type output structure.

## [0.3.0] - 2026-03-02

### Changed

- **Breaking:** Removed backward compatibility fallback to `effect-schema.paths`.
- Discovery now relies exclusively on `data_discoverers` and `enum_discoverers` configuration.

## [0.2.10] - 2026-03-02

### Added

- Added pluggable discovery contracts: `DataClassDiscoverer` and `EnumDiscoverer`.
- Added default discoverer plugins: `SpatieDataClassDiscoverer` and `NativeEnumDiscoverer`.
- Added `PhpClassCandidateScanner` for shared recursive PHP class candidate scanning.
- Added configuration keys `data_discoverers` and `enum_discoverers` with per-discoverer `paths`.

### Changed

- Refactored `ClassDiscoverer` into an orchestrator that aggregates configured discoverer plugins.
- Updated service container wiring to resolve discoverers from config entries (`class` + `paths`).
- Expanded tests to cover plugin aggregation and resilience when one discoverer fails.

## [0.2.9] - 2026-02-12

### Changed

- Enhanced schema generation to include full TypeScript type annotations on schema declarations (e.g., `S.Schema<Type, TypeEncoded>`).
- Updated optional properties in TypeScript interfaces to explicitly include `| undefined` in their type declarations.
- Simplified `S.suspend` calls in Effect schemas by removing verbose type annotations for cleaner output.
- Updated all test expectations to match the new output format.

### Changed

- Updated Transformer interface and all implementations to use proper union types (TypeIR|SchemaIR|EnumIR|PropertyIR) instead of mixed parameters, eliminating the need for PHPStan ignore annotations in plugin code.

## [0.2.7] - 2026-02-11

### Added

- Test case demonstrating that class-level `#[Optional]` attribute makes all properties optional in generated TypeScript interfaces and schemas.

## [0.2.6] - 2026-02-11

### Added

- Support for class-level `#[Optional]` attribute - when applied to a class, all properties in that class become optional by default in generated TypeScript interfaces and schemas.

### Changed

- Updated `EffectSchemaGenerator\Attributes\Optional` to support both `TARGET_CLASS` and `TARGET_PROPERTY` targets.

### Added

- Custom `#[Optional]` attribute (`EffectSchemaGenerator\Attributes\Optional`) that works independently of Spatie Laravel Data's Optional attribute, allowing users to decouple from external dependencies while maintaining the same functionality.

### Changed

- Enhanced LazyOptionalPlugin to support both Spatie's `#[Optional]` attribute and the new custom `#[Optional]` attribute.

### Added

- Support for `#[Optional]` attribute from Spatie Laravel Data - properties with this attribute are now correctly marked as optional in generated TypeScript interfaces and schemas.

### Changed

- Removed redundant `preprocessProperty` call in `DefaultPropertyWriter` that was duplicating preprocessing already done by `FileWriter`.

## [0.2.3] - 2026-02-11

### Fixed

- Fixed import generation for types referenced in PHPDoc annotations by trimming leading backslashes from class names.

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
