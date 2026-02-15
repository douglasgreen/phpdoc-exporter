<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Cli;

use DouglasGreen\PhpDocExporter\Config\Configuration;

/**
 * POSIX-compliant argument parser for CLI options.
 *
 * Handles both short and long options, validates required arguments,
 * and produces a typed Configuration object for the application.
 *
 * @package DouglasGreen\PhpDocExporter\Cli
 *
 * @api
 *
 * @since 1.0.0
 */
final class ArgsParser
{
    private const string VERSION = '1.0.0';

    private const string PROGRAM_NAME = 'phpdoc-exporter';

    /** @var list<string> */
    private array $sourcePaths = [];

    /** @var list<string> */
    private array $ignorePaths = [];

    private string $outputFile = '';

    private bool $verbose = false;

    private bool $strict = false;

    private bool $helpRequested = false;

    private bool $versionRequested = false;

    /**
     * Parses command-line arguments into a Configuration object.
     *
     * @param list<string> $argv Raw command-line arguments
     *
     * @return Configuration|false Configuration on success, false on parse error
     */
    public function parse(array $argv): Configuration|false
    {
        $this->reset();

        // Skip program name
        $args = array_slice($argv, 1);
        $operandCount = 0;

        $i = 0;
        while ($i < count($args)) {
            $arg = $args[$i];

            // Handle -- option terminator
            if ($arg === '--') {
                $i++;
                while ($i < count($args)) {
                    $this->addOperand($args[$i]);
                    $operandCount++;
                    $i++;
                }

                break;
            }

            // Handle long options
            if (str_starts_with($arg, '--')) {
                $result = $this->parseLongOption($args, $i);
                if ($result === false) {
                    return false;
                }

                $i = $result;
                continue;
            }

            // Handle short options
            if (str_starts_with($arg, '-') && $arg !== '-') {
                $result = $this->parseShortOptions($args, $i);
                if ($result === false) {
                    return false;
                }

                $i = $result;
                continue;
            }

            // Handle operand
            $this->addOperand($arg);
            $operandCount++;
            $i++;
        }

        // Handle help and version flags
        if ($this->helpRequested) {
            $this->printHelp();
            exit(0);
        }

        if ($this->versionRequested) {
            $this->printVersion();
            exit(0);
        }

        // Validate required arguments
        if ($this->outputFile === '') {
            fwrite(STDERR, "error: output file is required\n");
            return false;
        }

        if ($this->sourcePaths === []) {
            fwrite(STDERR, "error: at least one source path is required\n");
            return false;
        }

        return new Configuration(
            sourcePaths: $this->sourcePaths,
            ignorePaths: $this->ignorePaths,
            outputFile: $this->outputFile,
            verbose: $this->verbose,
            strict: $this->strict,
        );
    }

    /**
     * Prints help text to stdout.
     */
    public function printHelp(): void
    {
        $help = <<<'HELP'
Usage: phpdoc-exporter [OPTIONS] <source>... --output <file>

Export PHPDoc comments from PHP files to a single Markdown document.

Arguments:
  <source>                  PHP file or directory to process (multiple allowed)

Options:
  -o, --output <file>       Output Markdown file (required)
  -i, --ignore <path>       Path to ignore (multiple allowed)
  -s, --strict              Fail on PHPDoc validation warnings
  -v, --verbose             Enable verbose output
  -h, --help                Show this help message
      --version             Show version information
  --                        End of options (for files starting with -)

Examples:
  phpdoc-exporter src/ -o docs/api.md
  phpdoc-exporter src/ lib/ -i vendor/ -o docs/api.md
  phpdoc-exporter src/Service.php -o service-docs.md --strict

Exit Codes:
  0   Success
  1   General error
  2   Usage error (invalid arguments)
  126 Permission denied
  127 Command not found

Environment:
  NO_COLOR                  Disable colored output

Documentation:
  https://github.com/douglasgreen/phpdoc-exporter

HELP;

        echo $help;
    }

    /**
     * Prints version information to stdout.
     */
    public function printVersion(): void
    {
        echo self::PROGRAM_NAME . ' ' . self::VERSION . "\n";
    }

    /**
     * Resets parser state for reuse.
     */
    private function reset(): void
    {
        $this->sourcePaths = [];
        $this->ignorePaths = [];
        $this->outputFile = '';
        $this->verbose = false;
        $this->strict = false;
        $this->helpRequested = false;
        $this->versionRequested = false;
    }

    /**
     * Parses a long option (--option or --option=value).
     *
     * @param list<string> $args Argument array
     * @param int $index Current index
     *
     * @return int|false Next index or false on error
     */
    private function parseLongOption(array $args, int $index): int|false
    {
        $arg = $args[$index];
        $option = substr($arg, 2);
        $value = null;

        // Handle --option=value format
        if (str_contains($option, '=')) {
            $parts = explode('=', $option, 2);
            $option = $parts[0];
            $value = $parts[1];
        }

        switch ($option) {
            case 'help':
                $this->helpRequested = true;
                return $index + 1;
            case 'version':
                $this->versionRequested = true;
                return $index + 1;
            case 'verbose':
                $this->verbose = true;
                return $index + 1;
            case 'strict':
                $this->strict = true;
                return $index + 1;
            case 'output':
            case 'o':
                if ($value !== null) {
                    $this->outputFile = $value;
                    return $index + 1;
                }

                if (!isset($args[$index + 1])) {
                    fwrite(STDERR, "error: --output requires a value\n");
                    return false;
                }

                $this->outputFile = $args[$index + 1];
                return $index + 2;
            case 'ignore':
            case 'i':
                if ($value !== null) {
                    $this->ignorePaths[] = $value;
                    return $index + 1;
                }

                if (!isset($args[$index + 1])) {
                    fwrite(STDERR, "error: --ignore requires a value\n");
                    return false;
                }

                $this->ignorePaths[] = $args[$index + 1];
                return $index + 2;
            default:
                fwrite(STDERR, sprintf('error: unknown option --%s%s', $option, PHP_EOL));
                return false;
        }
    }

    /**
     * Parses short options (-a or -abc).
     *
     * @param list<string> $args Argument array
     * @param int $index Current index
     *
     * @return int|false Next index or false on error
     */
    private function parseShortOptions(array $args, int $index): int|false
    {
        $arg = $args[$index];
        $options = substr($arg, 1);

        for ($j = 0; $j < strlen($options); $j++) {
            $option = $options[$j];
            $isLast = ($j === strlen($options) - 1);

            switch ($option) {
                case 'h':
                    $this->helpRequested = true;
                    break;
                case 'v':
                    $this->verbose = true;
                    break;
                case 's':
                    $this->strict = true;
                    break;
                case 'o':
                    if (!$isLast) {
                        fwrite(STDERR, "error: -o cannot be combined with other options\n");
                        return false;
                    }

                    if (!isset($args[$index + 1])) {
                        fwrite(STDERR, "error: -o requires a value\n");
                        return false;
                    }

                    $this->outputFile = $args[$index + 1];
                    return $index + 2;
                case 'i':
                    if (!$isLast) {
                        fwrite(STDERR, "error: -i cannot be combined with other options\n");
                        return false;
                    }

                    if (!isset($args[$index + 1])) {
                        fwrite(STDERR, "error: -i requires a value\n");
                        return false;
                    }

                    $this->ignorePaths[] = $args[$index + 1];
                    return $index + 2;
                default:
                    fwrite(STDERR, sprintf('error: unknown option -%s%s', $option, PHP_EOL));
                    return false;
            }
        }

        return $index + 1;
    }

    /**
     * Adds an operand to the appropriate list.
     *
     * @param string $value Operand value
     */
    private function addOperand(string $value): void
    {
        // Output file takes first priority if not set via option
        if ($this->outputFile === '' && str_ends_with($value, '.md')) {
            $this->outputFile = $value;
            return;
        }

        // Otherwise treat as source path
        $this->sourcePaths[] = $value;
    }
}
