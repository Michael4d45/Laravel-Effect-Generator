<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

use EffectSchemaGenerator\IR\RootIR;

/**
 * Writes TypeScript files from RootIR with intelligent import handling.
 */
class FileWriter
{
    private array $transformers;
    private FileContentWriter $fileContentWriter;
    private string $outputDirectory;
    private bool $clearOutputDirectoryBeforeWrite;
    private \EffectSchemaGenerator\Writer\OutputPathResolver $pathResolver;

    /**
     * @param RootIR $ast The root IR to generate files for
     * @param list<Transformer> $transformers Transformers to use for generation
     * @param string $outputDirectory Output directory
     * @param bool $clearOutputDirectoryBeforeWrite Whether to clear output directory contents before writing
     */
    public function __construct(
        private RootIR $ast,
        array $transformers,
        string $outputDirectory = '',
        bool $clearOutputDirectoryBeforeWrite = false,
    ) {
        $this->transformers = $transformers;
        $this->outputDirectory = $outputDirectory ?: resource_path(
            'ts/schemas',
        );
        $this->clearOutputDirectoryBeforeWrite =
            $clearOutputDirectoryBeforeWrite;
        $this->pathResolver =
            new \EffectSchemaGenerator\Writer\OutputPathResolver;

        $this->fileContentWriter = new MultiArtifactFileContentWriter(
            $transformers,
            new DefaultImportWriter,
        );
    }

    /**
     * Write all TypeScript files.
     */
    public function write(): void
    {
        if ($this->clearOutputDirectoryBeforeWrite) {
            $this->clearOutputDirectoryContents();
        }

        // Preprocess properties using transformers
        $this->preprocessAst();

        // Write transformer-provided files first
        $this->writeTransformerFiles();

        // Write one file per schema/enum symbol to mirror PHP namespace + type.
        foreach ($this->buildFileUnits() as $filePath => $namespaces) {
            $fullPath = $this->outputDirectory . '/' . $filePath;
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }

            $content = $this->fileContentWriter->writeFileContent(
                $filePath,
                $namespaces,
            );
            // Ensure content ends with a single newline
            $content = rtrim($content) . "\n";
            file_put_contents($fullPath, $content);
        }
    }

    /**
     * Delete all files and directories within the configured output directory.
     */
    private function clearOutputDirectoryContents(): void
    {
        if (!is_dir($this->outputDirectory)) {
            return;
        }

        $entries = scandir($this->outputDirectory);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $this->outputDirectory . '/' . $entry;
            $this->deletePathRecursively($path);
        }
    }

    private function deletePathRecursively(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries !== false) {
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $this->deletePathRecursively($path . '/' . $entry);
            }
        }

        @rmdir($path);
    }

    /**
     * Preprocess the AST: allow transformers to preprocess properties.
     * This is called before type transformation, allowing transformers to modify property metadata.
     */
    private function preprocessAst(): void
    {
        foreach ($this->ast->namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                foreach ($schema->properties as $property) {
                    // Let transformers preprocess properties (e.g., mark as optional)
                    $attributes = [
                        'class' => $schema->classAttributes,
                        'property' => $property->attributes,
                    ];
                    foreach ($this->transformers as $transformer) {
                        if (
                            !(
                                method_exists($transformer, 'canTransform')
                                && $transformer->canTransform(
                                    $property,
                                    WriterContext::INTERFACE,
                                    $attributes,
                                )
                            )
                        ) {
                            continue;
                        }

                        $transformer->transform(
                            $property,
                            WriterContext::INTERFACE,
                            $attributes,
                        );
                    }
                }
            }
        }
    }

    /**
     * Write files provided by transformers.
     */
    private function writeTransformerFiles(): void
    {
        foreach ($this->transformers as $transformer) {
            if (!$transformer->providesFile()) {
                continue;
            }

            $filePath = $transformer->getFilePath();
            if ($filePath === null) {
                continue;
            }

            $fullPath = $this->outputDirectory . '/' . $filePath;
            $directory = dirname($fullPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0o755, true);
            }

            $content = $transformer->getFileContent();
            file_put_contents($fullPath, $content);
        }
    }

    /**
     * Group namespaces into files based on their structure.
     *
     * @return array<string, list<NamespaceIR>> File path => namespaces
     */
    private function buildFileUnits(): array
    {
        $units = [];

        foreach ($this->ast->namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                $filePath = $this->pathResolver->schemaFilePath(
                    $namespace->namespace,
                    $schema->name,
                );

                $unitNamespace = new \EffectSchemaGenerator\IR\NamespaceIR(
                    namespace: $namespace->namespace,
                    uses: $namespace->uses,
                    schemas: [$schema],
                    enums: [],
                );
                $units[$filePath] = [$unitNamespace];
            }

            foreach ($namespace->enums as $enum) {
                $filePath = $this->pathResolver->enumFilePath(
                    $namespace->namespace,
                    $enum->name,
                );

                $unitNamespace = new \EffectSchemaGenerator\IR\NamespaceIR(
                    namespace: $namespace->namespace,
                    uses: $namespace->uses,
                    schemas: [],
                    enums: [$enum],
                );
                $units[$filePath] = [$unitNamespace];
            }
        }

        return $units;
    }
}
