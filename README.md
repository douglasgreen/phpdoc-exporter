# phpdoc-exporter

A CLI tool to export PHPDoc comments from PHP files into a single Markdown document with [docs/phpdoc.md](PHPDoc standards) validation.

## Installation

```bash
composer require douglasgreen/phpdoc-exporter
```

## Usage

```bash
# Basic usage
phpdoc-exporter src/ -o docs/api.md

# Multiple source paths
phpdoc-exporter src/ lib/ -o docs/api.md

# With ignore patterns
phpdoc-exporter src/ -i vendor/ -i tests/ -o docs/api.md

# Strict mode (fail on MUST violations)
phpdoc-exporter src/ -o docs/api.md --strict

# Verbose output
phpdoc-exporter src/ -o docs/api.md -v
```

## Options

| Option | Description |
|--------|-------------|
| `-o, --output <file>` | Output Markdown file (required) |
| `-i, --ignore <path>` | Path to ignore (multiple allowed) |
| `-s, --strict` | Fail on PHPDoc validation warnings |
| `-v, --verbose` | Enable verbose output |
| `-h, --help` | Show help message |
| `--version` | Show version |

## Output Format

The generated Markdown includes:

1. **Hierarchical structure**: Files → Classes/Interfaces/Traits → Methods → Properties
2. **PHPDoc blocks**: Rendered in fenced code blocks with syntax highlighting
3. **Tag sections**: Parameters, returns, throws, see also, deprecation notices
4. **Standards violations**: Appendix listing all detected issues

## PHPDoc Standards Validation

The tool validates PHPDoc comments against the [phpdoc.md](php/phpdoc.md) standards and reports:

- **MUST violations**: Critical issues that render documentation incomplete
- **SHOULD improvements**: Recommendations for better documentation quality

### Validation Rules

| Rule | Level | Description |
|------|-------|-------------|
| 1.1 | MUST | Classes/methods/properties have documentation |
| 1.1 | SHOULD | Classes have @package, @since, @api/@internal |
| 1.3 | SHOULD | Summaries under 80 chars, end with period |
| 3.1 | SHOULD | Methods have @return tag |
| 2.3 | SHOULD | Properties have @var tag |
| 4.2 | SHOULD | Deprecated elements reference replacement |

## Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | General error |
| 2 | Usage error (invalid arguments) |
| 130 | Interrupted (SIGINT) |

## Dependencies

- PHP 8.3+
- [phpstan/phpdoc-parser](https://github.com/phpstan/phpdoc-parser) - Modern PHPDoc parser with AST support
- [nikic/php-parser](https://github.com/nikic/php-parser) - PHP parser (transitive)

## License

MIT
