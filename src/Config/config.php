<?php

declare(strict_types=1);

return [
    /*
     |--------------------------------------------------------------------------
     | Data Class Discoverers
     |--------------------------------------------------------------------------
     |
     | Configure discoverer plugins used to find Spatie Data classes.
     | Each discoverer receives its own path list, which allows targeted scans.
     |
     */
    'data_discoverers' => [
        [
            'class' =>
                EffectSchemaGenerator\Discovery\SpatieDataClassDiscoverer::class,
            'paths' => [
                app_path('Data'),
            ],
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Enum Discoverers
     |--------------------------------------------------------------------------
     |
     | Configure discoverer plugins used to find native PHP enums.
     | Each discoverer receives its own path list, which allows targeted scans.
     |
     */
    'enum_discoverers' => [
        [
            'class' =>
                EffectSchemaGenerator\Discovery\NativeEnumDiscoverer::class,
            'paths' => [
                app_path('Enums'),
            ],
        ],
    ],

    /*
     |--------------------------------------------------------------------------
     | Enabled Transformers
     |--------------------------------------------------------------------------
     |
     | Configure which transformers should be active. Transformers handle both
     | type transformations (PHP to TypeScript/Effect) and output generation.
     | They are context-aware and can generate different outputs for interfaces,
     | encoded interfaces, schemas, and enums.
     |
     */
    'transformers' => [
        // Plugins that transform types
        EffectSchemaGenerator\Plugins\LengthAwarePaginatorPlugin::class,
        EffectSchemaGenerator\Plugins\LazyOptionalPlugin::class,
        EffectSchemaGenerator\Plugins\DatePlugin::class,
        EffectSchemaGenerator\Plugins\CollectionPlugin::class,

        // Writers are created automatically with transformers passed to them
        // Do NOT add DefaultSchemaWriter or EffectSchemaSchemaWriter here
        // as they need the full transformers array in their constructor

        // Enum writer doesn't need transformers
        EffectSchemaGenerator\Writer\TypeEnumWriter::class,
        EffectSchemaGenerator\Writer\EffectSchemaEnumWriter::class,
        EffectSchemaGenerator\Writer\ConstObjectEnumWriter::class,
    ],

    /*
     |--------------------------------------------------------------------------
     | PHPDoc Override Behavior
     |--------------------------------------------------------------------------
     |
     | When both native type hints and PHPDoc @var annotations are present,
     | this setting determines which takes precedence.
     |
     | true = PHPDoc overrides native types (recommended for Spatie Data)
     | false = Native types take precedence
     |
     */
    'phpdoc_overrides_types' => true,

    /*
     |--------------------------------------------------------------------------
     | Output Configuration
     |--------------------------------------------------------------------------
     |
     | Configure where generated files should be written and how they should
     | be organized.
     |
     */
    'output' => [
        'directory' => resource_path('js/schemas'),
        'file_extension' => '.ts',
        'clear_output_directory_before_write' => true,
    ],
];
