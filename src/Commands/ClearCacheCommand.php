<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Artisan command to clear the Surveyor analysis cache used by the generator.
 */
class ClearCacheCommand extends Command
{
    protected $signature = 'effect-schema:clear-cache
                            {--path= : Override the cache path to clear}';

    protected $description = 'Clear the Effect Schema Generator cache';

    public function __construct(
        private Filesystem $files,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path(
            'framework/cache/surveyor-cache',
        );

        if (!$this->files->exists($path)) {
            $this->info("Cache directory not found at {$path}.");
            return self::SUCCESS;
        }

        if ($this->files->isFile($path)) {
            $this->files->delete($path);
            $this->info("Deleted cache file at {$path}.");
            return self::SUCCESS;
        }

        $this->files->deleteDirectory($path);
        $this->info("Cleared cache at {$path}.");

        return self::SUCCESS;
    }
}
