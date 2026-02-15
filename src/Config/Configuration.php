<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Config;

/**
 * Immutable configuration container for PHPDoc exporter settings.
 *
 * Stores all configuration options needed for documentation generation
 * including source paths, ignore patterns, and output settings.
 *
 * @package DouglasGreen\PhpDocExporter\Config
 * @api
 * @since 1.0.0
 */
final readonly class Configuration
{
    /**
     * @param list<string> $sourcePaths Paths to PHP files or directories
     * @param list<string> $ignorePaths Paths to ignore during processing
     * @param string $outputFile Output Markdown file path
     * @param bool $verbose Enable verbose output
     * @param bool $strict Fail on PHPDoc validation warnings
     */
    public function __construct(
        public array $sourcePaths,
        public array $ignorePaths,
        public string $outputFile,
        public bool $verbose = false,
        public bool $strict = false,
    ) {}
}
