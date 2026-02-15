<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Cli;

use DouglasGreen\PhpDocExporter\Config\Configuration;
use DouglasGreen\PhpDocExporter\Core\MarkdownGenerator;
use DouglasGreen\PhpDocExporter\Core\PhpDocExtractor;
use DouglasGreen\PhpDocExporter\Error\ExitCodes;
use DouglasGreen\PhpDocExporter\IO\FileFinder;

/**
 * Main CLI application orchestrating the documentation export process.
 *
 * Coordinates argument parsing, file discovery, PHPDoc extraction,
 * Markdown generation, and output writing.
 *
 * @package DouglasGreen\PhpDocExporter\Cli
 * @api
 * @since 1.0.0
 */
final class Application
{
    private ArgsParser $parser;

    public function __construct()
    {
        $this->parser = new ArgsParser();
    }

    /**
     * Runs the application with the given arguments.
     *
     * @param list<string> $argv Command-line arguments
     * @return int Exit code per POSIX conventions
     */
    public function run(array $argv): int
    {
        // Handle SIGINT gracefully
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, function (): never {
            fwrite(STDERR, "\nInterrupted\n");
            exit(ExitCodes::SIGINT->value);
        });

        // Parse arguments
        $config = $this->parser->parse($argv);

        if ($config === false) {
            fwrite(STDERR, "Try 'phpdoc-exporter --help' for more information.\n");
            return ExitCodes::USAGE_ERROR->value;
        }

        // Discover PHP files
        $finder = new FileFinder();
        $files = $finder->find($config->sourcePaths, $config->ignorePaths);

        if ($files === []) {
            fwrite(STDERR, "error: no PHP files found in specified paths\n");
            return ExitCodes::GENERAL_ERROR->value;
        }

        if ($config->verbose) {
            fwrite(STDERR, sprintf("Found %d PHP file(s) to process\n", count($files)));
        }

        // Extract PHPDoc from files
        $extractor = new PhpDocExtractor();
        $documentation = [];

        foreach ($files as $file) {
            if ($config->verbose) {
                fwrite(STDERR, "Processing: {$file}\n");
            }

            $documentation[] = $extractor->extract($file);
        }

        // Generate Markdown
        $generator = new MarkdownGenerator();
        $result = $generator->generate($documentation, $this->extractProjectName($config));

        $markdown = $result['markdown'];
        $warnings = $result['warnings'];

        // Report warnings
        if ($warnings !== []) {
            $mustCount = count(array_filter($warnings, fn($w) => $w['level'] === 'MUST'));
            $shouldCount = count(array_filter($warnings, fn($w) => $w['level'] === 'SHOULD'));

            fwrite(STDERR, sprintf(
                "\nWarning: %d MUST violation(s), %d SHOULD improvement(s) detected\n",
                $mustCount,
                $shouldCount
            ));

            if ($config->strict && $mustCount > 0) {
                fwrite(STDERR, "error: strict mode enabled, failing on MUST violations\n");
                return ExitCodes::GENERAL_ERROR->value;
            }
        }

        // Write output
        $written = file_put_contents($config->outputFile, $markdown);

        if ($written === false) {
            fwrite(STDERR, "error: failed to write output file: {$config->outputFile}\n");
            return ExitCodes::GENERAL_ERROR->value;
        }

        if ($config->verbose) {
            fwrite(STDERR, sprintf(
                "Successfully wrote %d bytes to %s\n",
                $written,
                $config->outputFile
            ));
        }

        return ExitCodes::SUCCESS->value;
    }

    /**
     * Extracts a project name from configuration or paths.
     *
     * @param Configuration $config Application configuration
     */
    private function extractProjectName(Configuration $config): string
    {
        if ($config->sourcePaths === []) {
            return 'PHP Documentation';
        }

        $firstPath = $config->sourcePaths[0];
        $baseName = basename($firstPath);

        // Use directory name or filename as project name
        if (is_dir($firstPath)) {
            return ucfirst($baseName) . ' Documentation';
        }

        return str_replace('.php', '', $baseName) . ' Documentation';
    }
}
