<?php

declare(strict_types=1);

namespace EffectSchemaGenerator;

use EffectSchemaGenerator\Builder\AstBuilder;
use EffectSchemaGenerator\Commands\ClearCacheCommand;
use EffectSchemaGenerator\Commands\GenerateSchemasCommand;
use EffectSchemaGenerator\Discovery\ClassDiscoverer;
use EffectSchemaGenerator\Discovery\DataClassDiscoverer;
use EffectSchemaGenerator\Discovery\EnumDiscoverer;
use EffectSchemaGenerator\Discovery\NativeEnumDiscoverer;
use EffectSchemaGenerator\Discovery\SpatieDataClassDiscoverer;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use EffectSchemaGenerator\Writer\DefaultPropertyWriter;
use EffectSchemaGenerator\Writer\PropertyWriter;
use EffectSchemaGenerator\Writer\TypeScriptWriter;
use Illuminate\Support\ServiceProvider;
use Laravel\Surveyor\Analyzer\AnalyzedCache;

/**
 * Service provider for Laravel Effect Schema Generator.
 */
class EffectSchemaGeneratorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/Config/config.php', 'effect-schema');

        $this->app->singleton(ClassDiscoverer::class, function ($app) {
            $legacyPaths = array_map('strval', config()->array('effect-schema.paths', []));

            $dataDiscoverers = $this->resolveConfiguredDataDiscoverers(
                app: $app,
                legacyPaths: $legacyPaths,
            );
            $enumDiscoverers = $this->resolveConfiguredEnumDiscoverers(
                app: $app,
                legacyPaths: $legacyPaths,
            );

            return new ClassDiscoverer(
                paths: $legacyPaths,
                dataClassDiscoverers: $dataDiscoverers,
                enumDiscoverers: $enumDiscoverers,
            );
        });

        $this->app->singleton(DataClassParser::class, function ($app) {
            return new DataClassParser;
        });

        $this->app->singleton(EnumParser::class, function ($app) {
            return new EnumParser;
        });

        $this->app->singleton(AstBuilder::class, function ($app) {
            return new AstBuilder;
        });

        // Bind PropertyWriter to DefaultPropertyWriter with lazy TypeScriptWriter
        $this->app->singleton(PropertyWriter::class, function ($app) {
            return new DefaultPropertyWriter(typeWriter: new TypeScriptWriter([]));
        });

        $this->app->singleton(GenerateSchemasCommand::class, function ($app) {
            $config = config()->array('effect-schema', []);

            return new GenerateSchemasCommand(
                discoverer: app(ClassDiscoverer::class),
                dataParser: app(DataClassParser::class),
                enumParser: app(EnumParser::class),
                astBuilder: app(AstBuilder::class),
                // fileWriter: app(FileWriter::class),
                config: $config,
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Enable Surveyor caching for better performance
            AnalyzedCache::enableDiskCache(storage_path(
                'framework/cache/surveyor-cache',
            ));

            $this->commands([
                ClearCacheCommand::class,
                GenerateSchemasCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/Config/config.php' => config_path(
                    'effect-schema.php',
                ),
            ], 'effect-schema-config');
        }
    }

    /**
     * @param  array<string>  $legacyPaths
     * @return list<DataClassDiscoverer>
     */
    private function resolveConfiguredDataDiscoverers(
        $app,
        array $legacyPaths,
    ): array {
        $configured = config()->array('effect-schema.data_discoverers', []);

        if ($configured === []) {
            $configured = [
                [
                    'class' => SpatieDataClassDiscoverer::class,
                    'paths' => $legacyPaths,
                ],
            ];
        }

        /** @var list<DataClassDiscoverer> $discoverers */
        $discoverers = [];
        foreach ($configured as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $class = $entry['class'] ?? null;
            if (!is_string($class) || $class === '') {
                continue;
            }

            $paths = $entry['paths'] ?? [];
            if (!is_array($paths)) {
                $paths = [];
            }

            $discoverer = $app->makeWith($class, [
                'paths' => array_map('strval', $paths),
            ]);

            if ($discoverer instanceof DataClassDiscoverer) {
                $discoverers[] = $discoverer;
            }
        }

        return $discoverers;
    }

    /**
     * @param  array<string>  $legacyPaths
     * @return list<EnumDiscoverer>
     */
    private function resolveConfiguredEnumDiscoverers(
        $app,
        array $legacyPaths,
    ): array {
        $configured = config()->array('effect-schema.enum_discoverers', []);

        if ($configured === []) {
            $configured = [
                [
                    'class' => NativeEnumDiscoverer::class,
                    'paths' => $legacyPaths,
                ],
            ];
        }

        /** @var list<EnumDiscoverer> $discoverers */
        $discoverers = [];
        foreach ($configured as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $class = $entry['class'] ?? null;
            if (!is_string($class) || $class === '') {
                continue;
            }

            $paths = $entry['paths'] ?? [];
            if (!is_array($paths)) {
                $paths = [];
            }

            $discoverer = $app->makeWith($class, [
                'paths' => array_map('strval', $paths),
            ]);

            if ($discoverer instanceof EnumDiscoverer) {
                $discoverers[] = $discoverer;
            }
        }

        return $discoverers;
    }
}
