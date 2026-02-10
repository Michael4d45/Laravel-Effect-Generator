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

    /**
     * @param RootIR $ast The root IR to generate files for
     * @param list<Transformer> $transformers Transformers to use for generation
     * @param string $outputDirectory Output directory
     */
    public function __construct(
        private RootIR $ast,
        array $transformers,
        string $outputDirectory = '',
    ) {
        $this->transformers = $transformers;
        $this->outputDirectory = $outputDirectory ?: resource_path(
            'ts/schemas',
        );

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
        // Preprocess properties using transformers
        $this->preprocessAst();

        // Write transformer-provided files first
        $this->writeTransformerFiles();

        // Group namespaces by their file structure
        $fileGroups = $this->groupNamespacesByFile();

        foreach ($fileGroups as $filePath => $namespaces) {
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
     * Preprocess the AST: allow transformers to preprocess properties.
     * This is called before type transformation, allowing transformers to modify property metadata.
     */
    private function preprocessAst(): void
    {
        foreach ($this->ast->namespaces as $namespace) {
            foreach ($namespace->schemas as $schema) {
                foreach ($schema->properties as $property) {
                    // Let transformers preprocess properties (e.g., mark as optional)
                    foreach ($this->transformers as $transformer) {
                        if (
                            !(
                                method_exists($transformer, 'canTransform')
                                && $transformer->canTransform(
                                    $property,
                                    WriterContext::INTERFACE,
                                )
                            )
                        ) {
                            continue;
                        }

                        $transformer->transform(
                            $property,
                            WriterContext::INTERFACE,
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
    private function groupNamespacesByFile(): array
    {
        $groups = [];

        foreach ($this->ast->namespaces as $namespace) {
            $filePath = $this->namespaceToFilePath($namespace->namespace);
            if (!array_key_exists($filePath, $groups)) {
                $groups[$filePath] = [];
            }
            $groups[$filePath][] = $namespace;
        }

        return $groups;
    }

    /**
     * Convert namespace to file path.
     * Examples:
     * - App\Data\Events -> App/Data/Events.ts
     * - App\Enums -> App/Enums.ts
     * - Illuminate\Pagination -> Illuminate/Pagination.ts
     */
    private function namespaceToFilePath(string $namespace): string
    {
        $parts = explode('\\', $namespace);
        $fileName = array_pop($parts);
        $path = implode('/', $parts);
        if ($path) {
            return $path . '/' . $fileName . '.ts';
        }
        return $fileName . '.ts';
    }
}
