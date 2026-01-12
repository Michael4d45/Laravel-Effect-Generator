<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Interface for plugins that provide additional TypeScript files to be written.
 */
interface FileProvidingPlugin extends TypePlugin
{
    /**
     * Get the TypeScript file content for this plugin's types.
     *
     * @return string The TypeScript file content
     */
    public function getTypeScriptFileContent(): string;

    /**
     * Get the file path where this plugin's types should be written.
     * This should be a relative path from the output directory.
     * Example: "Illuminate/Pagination.ts"
     *
     * @return string The file path
     */
    public function getOutputFilePath(): string;
}
