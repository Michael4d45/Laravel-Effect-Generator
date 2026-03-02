<?php
declare(strict_types=1);

namespace EffectSchemaGenerator\Tests;

use EffectSchemaGenerator\Discovery\NativeEnumDiscoverer;
use EffectSchemaGenerator\Discovery\SpatieDataClassDiscoverer;
use EffectSchemaGenerator\EffectSchemaGeneratorServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Surveyor\SurveyorServiceProvider::class,
            EffectSchemaGeneratorServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        // Setup default config for testing
        $paths = [
            __DIR__ . '/Fixtures',
        ];

        $app['config']->set('effect-schema.data_discoverers', [
            [
                'class' => SpatieDataClassDiscoverer::class,
                'paths' => $paths,
            ],
        ]);

        $app['config']->set('effect-schema.enum_discoverers', [
            [
                'class' => NativeEnumDiscoverer::class,
                'paths' => $paths,
            ],
        ]);

        $app['config']->set(
            'effect-schema.output.directory',
            __DIR__ . '/../tests/output',
        );
        $app['config']->set('effect-schema.output.namespace_separator', '/');
        $app['config']->set('effect-schema.output.file_extension', '.ts');
    }
}
