<?php

declare(strict_types=1);

namespace EffectSchemaGenerator\Writer;

/**
 * Default implementation of ImportWriter that generates TypeScript import statements.
 */
class DefaultImportWriter implements ImportWriter
{
    public function writeImports(
        array $imports,
        string $currentFilePath,
    ): string {
        if (empty($imports)) {
            return '';
        }

        $importLines = [];
        ksort($imports);

        foreach ($imports as $relativePath => $names) {
            // The relativePath from calculateRelativePath already has the correct format
            // Just ensure it doesn't have .ts extension
            $importPath = $relativePath;
            if (str_ends_with($importPath, '.ts')) {
                $importPath = substr($importPath, 0, -3);
            }

            // Sort names for consistent output
            ksort($names);
            $namesList = implode(', ', array_keys($names));
            $importLines[] = "import { {$namesList} } from '{$importPath}';";
        }

        return implode("\n", $importLines);
    }
}
