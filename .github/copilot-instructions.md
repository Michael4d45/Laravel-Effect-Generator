# Copilot Instructions - Laravel Effect Schema Generator

## Project Overview
This is a Laravel package that generates TypeScript interfaces and Effect schemas from PHP Spatie Data classes. It's a code generation tool that bridges PHP domain models to TypeScript frontend contracts.

**Core Purpose:** Transform Spatie Data class definitions → AST (IR) → TypeScript/Effect schemas

## Architecture & Data Flow

### 1. Discovery Phase
- **ClassDiscoverer** (`src/Discovery/ClassDiscoverer.php`): Scans configured paths for Spatie Data classes and PHP enums using regex extraction and Surveyor analysis
- Input: File paths from `effect-schema.php` config
- Output: Collections of fully-qualified class names
- Uses Laravel Surveyor for robust class analysis with fallback to manual inheritance checking

### 2. Parsing Phase
- **DataClassParser** & **EnumParser** (`src/Reflection/`): Convert PHP classes into Token objects
- Tokens capture: namespace, class/enum name, use statements, properties, and docblock metadata
- PHPStan DocBlockParser extracts @var annotations (which can override native types based on config)
- Output: ClassToken and EnumToken objects

### 3. AST Building Phase
- **AstBuilder** (`src/Builder/AstBuilder.php`): Transforms tokens into IR (Intermediate Representation)
- Creates RootIR containing NamespaceIR → SchemaIR/EnumIR objects
- Type inference: `PhpDocTypeBuilder` (PHPDoc @var) vs `SurveyorTypeBuilder` (native types)
- Priority: PHPDoc overrides native types when both present (`phpdoc_overrides_types` config)

### 4. Writing Phase
- **FileWriter** (`src/Writer/FileWriter.php`): Transforms IR to TypeScript output
- **MultiArtifactFileContentWriter**: Handles actual TS file content generation
- Groups namespaces by file structure (App\Data\Users → App/Data/Users.ts)
- Validates transformer implementations before loading

## Plugin System (Critical Extension Point)

All output generation uses the **Transformer interface** (`src/Writer/Transformer.php`). Each transformer is context-aware and handles type conversions differently based on WriterContext:

```php
WriterContext::INTERFACE,        // TypeScript interface declaration
WriterContext::ENCODED_INTERFACE, // Interface with serialized types
WriterContext::SCHEMA,           // Effect Schema type
WriterContext::ENUM              // Enum representation
```

**Built-in Transformers** (loaded in config):
- `DatePlugin`: Carbon/DateTime → Date/string/S.DateFromString
- `LazyPlugin`: Lazy collections
- `CollectionPlugin`: Illuminate Collections
- `DefaultSchemaWriter`: Default Schema output
- `EffectSchemaSchemaWriter`: Effect-specific schemas
- `TypeEnumWriter`: Enum handling

New type mappings: Implement Transformer, register in config `transformers` array.

## Key Conventions & Patterns

1. **Token → IR → Output**: Three-stage processing ensures separation of concerns
2. **Config-driven transformers**: No hardcoded type mappings; all in `config/effect-schema.php`
3. **Namespace-to-file mapping**: Matches PHP namespace structure to TypeScript directory structure
4. **PHPDoc precedence**: Always check config `phpdoc_overrides_types` when working with type inference
5. **Error resilience**: Discovery skips classes with missing dependencies; no failing on partial analysis

## Testing & Development Workflows

### Commands
```bash
php artisan effect-schema:transform        # Generate schemas
php artisan effect-schema:transform --dry-run # Preview output
composer test                              # Run Pest tests
composer lint                              # Mago static analysis
composer fmt                               # Auto-format code
composer check                             # Lint + test
```

### Configuration
- Entry point: `config/effect-schema.php` (publishes via `effect-schema-config` tag)
- Scan paths: `effect-schema.paths` array
- Transformers: `effect-schema.transformers` array
- Output: `effect-schema.output.directory` (defaults to `resources/ts/schemas`)

### Testing Coverage
- Uses Pest with Orchestra Testbench for Laravel integration
- SQLite in-memory DB for test isolation
- PHPUnit configured to exclude GenerateSchemasCommand (IR dependencies)
- Coverage reports: `reports/coverage_html/` and `reports/coverage-treemap/`

## Important Implementation Details

1. **IR Classes** (`src/IR/`): PropertyIR, SchemaIR, EnumIR are data structures, not logic
2. **Type Resolution**: UnknownTypeIR is fallback when type cannot be determined
3. **Relative Path Calculation**: FileWriter handles cross-file imports intelligently
4. **File Writing**: Ensures single trailing newline, creates directories recursively
5. **Dependencies**: Laravel 12, Spatie LaravelData 4, Laravel Surveyor, PHPStan, Nikic PHP Parser
6. **Service Container Bindings** (`src/EffectSchemaGeneratorServiceProvider.php`): 
   - `PropertyWriter` interface bound to `DefaultPropertyWriter`
   - `TypeScriptWriter` instantiated with empty array (transformers passed at write time to avoid circular dependencies)
   - All core services registered as singletons in `register()` method

## Common Tasks

- **Add new type mapping**: Create Transformer implementing `canTransform()` and `transform()`, register in config
- **Add plugin file output**: Implement `providesFile()`, `getFileContent()`, `getFilePath()`
- **Debug discovery**: Check paths in config; use `ClassDiscoverer::discoverClasses()` for raw results
- **Modify output format**: Extend FileContentWriter or MultiArtifactFileContentWriter
