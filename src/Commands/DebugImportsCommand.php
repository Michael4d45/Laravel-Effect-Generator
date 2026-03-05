<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Commands;

use EffectSchemaGenerator\Builder\AstBuilder;
use EffectSchemaGenerator\Discovery\ClassDiscoverer;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use EffectSchemaGenerator\Writer\EffectSchemaSchemaWriter;
use EffectSchemaGenerator\Writer\OutputPathResolver;
use Illuminate\Console\Command;

/**
 * Diagnostic command to trace why a schema file gets particular imports
 * (e.g. why LengthAwarePaginatorEncoded appears when the Pagination plugin does not export it).
 *
 * Run in your app (e.g. kitchenassistant) with a class that uses LengthAwarePaginator:
 *   php artisan effect-schema:debug-imports "App\Features\Recipe\Responses\ListRecipesResponse"
 *
 * This dumps: configured transformers, referenced types for that schema, and for each
 * referenced type whether a transformer provides its file (getTransformerFilePathForType).
 * If LengthAwarePaginator has transformer path = null, that explains the wrong Encoded import.
 */
class DebugImportsCommand extends Command
{
    protected $signature = 'effect-schema:debug-imports
                            {class? : FQCN of a Data class that references LengthAwarePaginator (e.g. ListRecipesResponse)}';

    protected $description = 'Trace import logic for a schema (diagnose wrong LengthAwarePaginatorEncoded import)';

    public function __construct(
        private ClassDiscoverer $discoverer,
        private DataClassParser $dataParser,
        private EnumParser $enumParser,
        private AstBuilder $astBuilder,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $transformers = $this->loadTransformers();

        $this->line('');
        $this->line('=== Configured transformers (order matters) ===');
        foreach ($transformers as $i => $t) {
            $this->line(sprintf('  %d. %s', $i + 1, $t::class));
        }
        $this->line('');

        $targetClass = $this->argument('class');
        if ($targetClass === null || $targetClass === '') {
            $targetClass = $this->findClassWithPaginator($transformers);
            if ($targetClass === null) {
                $this->warn(
                    'No class given and none found that references LengthAwarePaginator.',
                );
                $this->line(
                    'Run: php artisan effect-schema:debug-imports "App\\Features\\Recipe\\Responses\\ListRecipesResponse"',
                );
                return self::FAILURE;
            }
            $this->line(
                "Using first discovered class that references LengthAwarePaginator: <info>{$targetClass}</info>",
            );
            $this->line('');
        }

        if (!class_exists($targetClass)) {
            $this->error("Class not found: {$targetClass}");
            return self::FAILURE;
        }

        $token = $this->dataParser->parse($targetClass);
        $ast = $this->astBuilder->build(collect([$token]));

        $schema = null;
        $namespace = null;
        foreach ($ast->namespaces as $ns) {
            foreach ($ns->schemas as $s) {
                if ($s->name === class_basename($targetClass)) {
                    $schema = $s;
                    $namespace = $ns;
                    break 2;
                }
            }
        }

        if ($schema === null || $namespace === null) {
            $this->error('Could not find schema in AST for ' . $targetClass);
            return self::FAILURE;
        }

        $pathResolver = new OutputPathResolver;
        $filePath = $pathResolver->schemaFilePath(
            $namespace->namespace,
            $schema->name,
        );
        $localSchemas = [$schema->name => $schema];
        $localEnums = [];

        $writer = new EffectSchemaSchemaWriter($transformers);
        $decisions = $writer->debugImportDecisions($schema);

        $this->line('=== Referenced types for this schema ===');
        foreach ($decisions as $d) {
            $this->line("  - {$d['fqcn']} (alias: {$d['alias']})");
        }
        $this->line('');

        $this->line(
            '=== For each referenced type: does a transformer provide the file? ===',
        );
        $this->line(
            '(If "path = null", we fall into the non-transformer branch and add *Encoded to imports.)',
        );
        $this->line('');

        foreach ($decisions as $d) {
            $path = $d['transformer_path'];
            $alias = $d['alias'];
            $fqcn = $d['fqcn'];
            if ($path === null) {
                $this->line("  <fg=red>{$alias}</> ({$fqcn})");
                $this->line(
                    "    -> path = <fg=red>null</> (will add {$alias}Encoded to imports)",
                );
            } else {
                $this->line("  <fg=green>{$alias}</> ({$fqcn})");
                $this->line("    -> path = {$path}");
            }
        }
        $this->line('');

        // Run writeSchema and show what actually gets added to imports (especially Pagination)
        $imports = [];
        $writer->writeSchema(
            $schema,
            $filePath,
            $localSchemas,
            $localEnums,
            $imports,
        );

        $this->line(
            '=== Imports actually added by EffectSchemaSchemaWriter ===',
        );
        foreach ($imports as $relativePath => $names) {
            $namesList = implode(', ', array_keys($names));
            $highlight = str_contains($relativePath, 'Pagination')
            || str_contains($namesList, 'LengthAwarePaginator')
                ? ' <-- Pagination'
                : '';
            $this->line("  {$relativePath}");
            $this->line("    {$namesList}{$highlight}");
        }
        $this->line('');

        $paginationKey = null;
        foreach (array_keys($imports) as $key) {
            if (stripos($key, 'Pagination') !== false) {
                $paginationKey = $key;
                break;
            }
        }
        if (
            $paginationKey !== null
            && array_key_exists(
                'LengthAwarePaginatorEncoded',
                $imports[$paginationKey],
            )
        ) {
            $this->line(
                '<fg=red>LengthAwarePaginatorEncoded is in the Pagination import (wrong – plugin file does not export it).</>',
            );
            $this->line(
                'Cause: getTransformerFilePathForType(Illuminate\Pagination\LengthAwarePaginator) returned null above.',
            );
            return self::FAILURE;
        }

        $this->line(
            '<fg=green>Pagination import does not include LengthAwarePaginatorEncoded (correct).</>',
        );
        return self::SUCCESS;
    }

    private function loadTransformers(): array
    {
        $config = config('effect-schema', []);
        $transformersConfig = $config['transformers'] ?? [];
        if (!is_array($transformersConfig)) {
            return [];
        }
        $transformers = [];
        foreach ($transformersConfig as $transformerClass) {
            try {
                $transformer = app($transformerClass);
                if (
                    $transformer
                    instanceof \EffectSchemaGenerator\Writer\Transformer
                ) {
                    $transformers[] = $transformer;
                }
            } catch (\Throwable $e) {
                $this->warn(
                    "Could not instantiate {$transformerClass}: "
                        . $e->getMessage(),
                );
            }
        }
        return $transformers;
    }

    private function findClassWithPaginator(array $transformers): null|string
    {
        $dataClasses = $this->discoverer->discoverDataClasses();
        $paginatorFqcn = 'Illuminate\Pagination\LengthAwarePaginator';

        foreach ($dataClasses as $class) {
            try {
                if (
                    !class_exists($class)
                    || !is_subclass_of($class, 'Spatie\LaravelData\Data')
                ) {
                    continue;
                }
                $token = $this->dataParser->parse($class);
                $ast = $this->astBuilder->build(collect([$token]));
                foreach ($ast->namespaces as $ns) {
                    foreach ($ns->schemas as $schema) {
                        foreach ($schema->properties as $property) {
                            if ($this->typeReferencesFqcn(
                                $property->type,
                                $paginatorFqcn,
                            )) {
                                return $class;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
        return null;
    }

    private function typeReferencesFqcn(
        \EffectSchemaGenerator\IR\TypeIR $type,
        string $fqcn,
    ): bool {
        if (
            $type
            instanceof \EffectSchemaGenerator\IR\Types\ClassReferenceTypeIR
        ) {
            if ($type->fqcn === $fqcn) {
                return true;
            }
            foreach ($type->typeParameters as $param) {
                if ($this->typeReferencesFqcn($param, $fqcn)) {
                    return true;
                }
            }
        }
        if ($type instanceof \EffectSchemaGenerator\IR\Types\NullableTypeIR) {
            return $this->typeReferencesFqcn($type->innerType, $fqcn);
        }
        if ($type instanceof \EffectSchemaGenerator\IR\Types\UnionTypeIR) {
            foreach ($type->types as $t) {
                if ($this->typeReferencesFqcn($t, $fqcn)) {
                    return true;
                }
            }
        }
        if (
            $type instanceof \EffectSchemaGenerator\IR\Types\ArrayTypeIR
            && $type->itemType !== null
        ) {
            return $this->typeReferencesFqcn($type->itemType, $fqcn);
        }
        return false;
    }
}
