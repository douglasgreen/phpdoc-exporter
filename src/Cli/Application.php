<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Cli;

use DouglasGreen\OptParser\Exception\OptParserException;
use DouglasGreen\OptParser\OptParser;
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
 *
 * @api
 *
 * @since 1.0.0
 */
final readonly class Application
{
    private OptParser $parser;

    public function __construct()
    {
        $this->parser = new OptParser(
            'phpdoc-exporter',
            'Export PHPDoc comments from PHP files to a single Markdown document',
            '1.0.0',
        );

        $this->parser
            ->addTerm('source', 'STRING', 'PHP file or directory to process (multiple allowed)', required: true)
            ->addParam(['output', 'o'], 'STRING', 'Output Markdown file', required: true)
            ->addParam(['ignore', 'i'], 'STRING', 'Path to ignore (multiple allowed)')
            ->addFlag(['strict', 's'], 'Fail on PHPDoc validation warnings')
            ->addFlag(['verbose', 'v'], 'Enable verbose output')
            ->addExample('phpdoc-exporter src/ -o docs/api.md')
            ->addExample('phpdoc-exporter src/ lib/ -i vendor/ -o docs/api.md')
            ->addExample('phpdoc-exporter src/Service.php -o service-docs.md --strict')
            ->addExitCode('0', 'Success')
            ->addExitCode('1', 'General error')
            ->addExitCode('2', 'Usage error (invalid arguments)')
            ->addExitCode('130', 'Interrupted (SIGINT)')
            ->addEnvironment('NO_COLOR', 'Disable colored output')
            ->addDocumentation('https://github.com/douglasgreen/phpdoc-exporter');
    }

    /**
     * Runs the application with the given arguments.
     *
     * @param list<string> $argv Command-line arguments
     *
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
        try {
            $input = $this->parser->parse($argv);
        } catch (OptParserException $e) {
            fwrite(STDERR, $e->getMessage() . PHP_EOL);
            return $e->getExitCode();
        }

        // Build configuration from parsed input
        $config = new Configuration(
            sourcePaths: (array) $input->get('source'),
            ignorePaths: (array) ($input->get('ignore') ?? []),
            outputFile: (string) $input->get('output'),
            verbose: $input->get('verbose') ?? false,
            strict: $input->get('strict') ?? false,
        );

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
                fwrite(STDERR, sprintf('Processing: %s%s', $file, PHP_EOL));
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
            /** @var list<array{level: string, rule: string, element: string, message: string}> $warnings */
            $mustCount = count(array_filter($warnings, fn (array $w): bool => $w['level'] === 'MUST'));
            $shouldCount = count(array_filter($warnings, fn (array $w): bool => $w['level'] === 'SHOULD'));

            fwrite(STDERR, sprintf(
                "\nWarning: %d MUST violation(s), %d SHOULD improvement(s) detected\n",
                $mustCount,
                $shouldCount,
            ));

            if ($config->strict && $mustCount > 0) {
                fwrite(STDERR, "error: strict mode enabled, failing on MUST violations\n");
                return ExitCodes::GENERAL_ERROR->value;
            }
        }

        // Write output
        $written = file_put_contents($config->outputFile, $markdown);

        if ($written === false) {
            fwrite(STDERR, sprintf('error: failed to write output file: %s%s', $config->outputFile, PHP_EOL));
            return ExitCodes::GENERAL_ERROR->value;
        }

        if ($config->verbose) {
            fwrite(STDERR, sprintf(
                "Successfully wrote %d bytes to %s\n",
                $written,
                $config->outputFile,
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
