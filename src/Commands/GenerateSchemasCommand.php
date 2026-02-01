<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Commands;

use EffectSchemaGenerator\Builder\AstBuilder;
use EffectSchemaGenerator\Discovery\ClassDiscoverer;
use EffectSchemaGenerator\Reflection\DataClassParser;
use EffectSchemaGenerator\Reflection\EnumParser;
use EffectSchemaGenerator\Writer\FileWriter;
use EffectSchemaGenerator\Writer\Transformer;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Artisan command to generate TypeScript interfaces and Effect schemas from PHP classes.
 */
class GenerateSchemasCommand extends Command
{
    protected $signature = 'effect-schema:transform
                            {--dry-run : Show what would be generated without writing files}';

    protected $description = 'Generate TypeScript interfaces and Effect schemas from PHP Spatie Data classes';

    public function __construct(
        private ClassDiscoverer $discoverer,
        private DataClassParser $dataParser,
        private EnumParser $enumParser,
        private AstBuilder $astBuilder,
        // private FileWriter $fileWriter,
        private array $config,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Generating TypeScript schemas from PHP classes...');

        $hasRetried = false;

        while (true) {
            try {
                return $this->generateAll();
            } catch (\Throwable $e) {
                if (!$hasRetried) {
                    $hasRetried = true;
                    $this->warn(
                        'An error occurred during schema generation. '
                        . 'Clearing cache and retrying once...',
                    );
                    $this->call('effect-schema:clear-cache');
                    continue;
                }

                $this->error("Error: {$e->getMessage()}");
                return self::FAILURE;
            }
        }
    }

    private function generateAll(): int
    {
        $dataClasses = $this->discoverer->discoverDataClasses();
        $enums = $this->discoverer->discoverEnums();

        $this->info(
            "Found {$dataClasses->count()} data classes and {$enums->count()} enums",
        );

        $classTokens = $dataClasses
            ->map(function (string $class) {
                if (class_exists($class)) {
                    return $this->dataParser->parse($class);
                } elseif (enum_exists($class)) {
                    return $this->enumParser->parse($class);
                }
                $this->error("Class or enum '{$class}' not found");
                return null;
            })
            ->filter()
            ->values();

        $enumTokens = $enums->map(
            fn(string $enum) => $this->enumParser->parse($enum),
        )->values();

        if ($this->option('dry-run')) {
            $this->displayDryRun($classTokens, $enumTokens);
            return self::SUCCESS;
        }

        $ast = $this->astBuilder->build($classTokens->merge($enumTokens));

        $transformers = $this->loadTransformers();
        $outputDirectory =
            $this->config['output']['directory'] ?? resource_path('ts/schemas');

        if (empty($transformers)) {
            $this->warn('No transformers configured. Using default behavior.');
        }

        $fileWriter = new FileWriter($ast, $transformers, $outputDirectory);
        $fileWriter->write();

        $this->info('Schema generation completed successfully!');
        return self::SUCCESS;
    }

    /**
     * @return list<Transformer>
     */
    private function loadTransformers(): array
    {
        $transformersConfig = $this->config['transformers'] ?? [];
        if (!is_array($transformersConfig)) {
            throw new \Exception('Transformers config must be an array');
        }

        $transformers = [];
        foreach ($transformersConfig as $transformerClass) {
            $transformer = app($transformerClass);
            if ($transformer instanceof Transformer) {
                $transformers[] = $transformer;
            } else {
                throw new \Exception(
                    "Transformer {$transformerClass} is not a Transformer",
                );
            }
        }
        return $transformers;
    }

    private function displayDryRun(Collection $classes, Collection $enums): void
    {
        $this->info('DRY RUN - The following would be generated:');

        foreach ($classes as $class) {
            $this->displayDefinition($class);
        }

        foreach ($enums as $enum) {
            $this->displayDefinition($enum);
        }
    }
}
