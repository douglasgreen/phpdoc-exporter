<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\IO;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Recursively finds PHP files in directories while respecting ignore patterns.
 *
 * Supports both file and directory inputs, with glob-style ignore patterns
 * for excluding specific paths from documentation generation.
 *
 * @package DouglasGreen\PhpDocExporter\IO
 *
 * @api
 *
 * @since 1.0.0
 */
final class FileFinder
{
    /**
     * Finds all PHP files matching the given paths and ignore patterns.
     *
     * @param list<string> $sourcePaths Files or directories to search
     * @param list<string> $ignorePaths Patterns to exclude
     *
     * @return list<string> Absolute paths to PHP files
     */
    public function find(array $sourcePaths, array $ignorePaths): array
    {
        $files = [];
        $ignorePatterns = $this->normalizeIgnorePatterns($ignorePaths);

        foreach ($sourcePaths as $path) {
            $realPath = realpath($path);

            if ($realPath === false) {
                continue;
            }

            if (is_file($realPath)) {
                if ($this->shouldInclude($realPath, $ignorePatterns)) {
                    $files[] = $realPath;
                }
                continue;
            }

            if (is_dir($realPath)) {
                $files = [...$files, ...$this->findInDirectory($realPath, $ignorePatterns)];
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * Normalizes ignore patterns for consistent matching.
     *
     * @param list<string> $patterns Raw ignore patterns
     *
     * @return list<string> Normalized patterns
     */
    private function normalizeIgnorePatterns(array $patterns): array
    {
        return array_map(
            fn (string $pattern): string => str_replace('\\', '/', $pattern),
            $patterns,
        );
    }

    /**
     * Recursively finds PHP files in a directory.
     *
     * @param string $directory Directory to search
     * @param list<string> $ignorePatterns Patterns to exclude
     *
     * @return list<string> Found PHP files
     */
    private function findInDirectory(string $directory, array $ignorePatterns): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $path = $file->getRealPath();
                if ($path !== false && $this->shouldInclude($path, $ignorePatterns)) {
                    $files[] = $path;
                }
            }
        }

        return $files;
    }

    /**
     * Checks if a file should be included based on ignore patterns.
     *
     * @param string $filePath File path to check
     * @param list<string> $ignorePatterns Patterns to exclude
     */
    private function shouldInclude(string $filePath, array $ignorePatterns): bool
    {
        $normalizedPath = str_replace('\\', '/', $filePath);

        foreach ($ignorePatterns as $pattern) {
            if (str_contains($normalizedPath, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
