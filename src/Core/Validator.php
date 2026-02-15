<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Core;

use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;

/**
 * Validates PHPDoc comments against phpdoc.md standards.
 *
 * Checks for required tags, proper formatting, and compliance with
 * documentation standards for classes, methods, and properties.
 *
 * @package DouglasGreen\PhpDocExporter\Core
 * @api
 * @since 1.0.0
 */
final class Validator
{
    /**
     * Validation warning levels per RFC 2119.
     */
    public const LEVEL_MUST = 'MUST';
    public const LEVEL_SHOULD = 'SHOULD';
    public const LEVEL_MAY = 'MAY';

    /**
     * @var list<array{level: string, rule: string, element: string, message: string}>
     */
    private array $warnings = [];

    /**
     * Validates a PHPDoc node against standards for the given element type.
     *
     * @param PhpDocNode|null $doc Parsed PHPDoc node
     * @param string $elementType Type of element (class, method, property, etc.)
     * @param string $elementName Name of the element being validated
     * @param string $filePath File containing the element
     * @param int $lineNumber Line number of the element
     * @return list<array{level: string, rule: string, element: string, message: string}>
     */
    public function validate(
        ?PhpDocNode $doc,
        string $elementType,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): array {
        $this->warnings = [];

        if ($doc === null) {
            $this->addWarning(
                self::LEVEL_MUST,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Element '{$elementName}' ({$elementType}) lacks PHPDoc documentation"
            );
            return $this->warnings;
        }

        match ($elementType) {
            'class', 'interface', 'trait' => $this->validateClassLike($doc, $elementName, $filePath, $lineNumber),
            'method', 'function' => $this->validateCallable($doc, $elementName, $filePath, $lineNumber),
            'property' => $this->validateProperty($doc, $elementName, $filePath, $lineNumber),
            default => null,
        };

        $this->validateGeneral($doc, $elementName, $filePath, $lineNumber);

        return $this->warnings;
    }

    /**
     * Returns all warnings from the last validation.
     *
     * @return list<array{level: string, rule: string, element: string, message: string}>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Validates class-like elements (classes, interfaces, traits).
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $elementName Element name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     */
    private function validateClassLike(
        PhpDocNode $doc,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): void {
        // MUST include short description (summary line)
        $summary = $this->extractSummary($doc);
        if ($summary === null || $summary === '') {
            $this->addWarning(
                self::LEVEL_MUST,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Class/interface/trait '{$elementName}' lacks short description"
            );
        } else {
            $this->validateSummary($summary, $elementName, $filePath, $lineNumber);
        }

        // SHOULD include @package
        if (!$this->hasTag($doc, '@package')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Class '{$elementName}' missing @package tag"
            );
        }

        // SHOULD include @since
        if (!$this->hasTag($doc, '@since')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Class '{$elementName}' missing @since tag"
            );
        }

        // SHOULD include @api or @internal
        if (!$this->hasTag($doc, '@api') && !$this->hasTag($doc, '@internal')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Class '{$elementName}' should have @api or @internal marker"
            );
        }
    }

    /**
     * Validates callable elements (methods, functions).
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $elementName Element name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     */
    private function validateCallable(
        PhpDocNode $doc,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): void {
        // MUST include short description
        $summary = $this->extractSummary($doc);
        if ($summary === null || $summary === '') {
            $this->addWarning(
                self::LEVEL_MUST,
                '1.1',
                "{$filePath}:{$lineNumber}",
                "Method/function '{$elementName}' lacks short description"
            );
        }

        // MUST have @param for each parameter (warning only - can't verify count)
        // This is noted but not enforced without parameter reflection

        // SHOULD have @return (even for void)
        if (!$this->hasTag($doc, '@return')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '3.1',
                "{$filePath}:{$lineNumber}",
                "Method/function '{$elementName}' missing @return tag"
            );
        }
    }

    /**
     * Validates property elements.
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $elementName Element name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     */
    private function validateProperty(
        PhpDocNode $doc,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): void {
        // SHOULD have @var for complex types
        if (!$this->hasTag($doc, '@var')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '2.3',
                "{$filePath}:{$lineNumber}",
                "Property '{$elementName}' missing @var tag"
            );
        }
    }

    /**
     * Validates general PHPDoc standards applicable to all elements.
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $elementName Element name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     */
    private function validateGeneral(
        PhpDocNode $doc,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): void {
        // Check for deprecated elements
        if ($this->hasTag($doc, '@deprecated')) {
            $deprecatedTag = $this->getTag($doc, '@deprecated');
            if ($deprecatedTag !== null) {
                $content = (string) $deprecatedTag->value;
                if (!str_contains($content, '@see') && !str_contains($content, 'use ')) {
                    $this->addWarning(
                        self::LEVEL_SHOULD,
                        '4.2',
                        "{$filePath}:{$lineNumber}",
                        "Deprecated '{$elementName}' should reference replacement"
                    );
                }
            }
        }
    }

    /**
     * Validates the summary line format.
     *
     * @param string $summary Summary text
     * @param string $elementName Element name
     * @param string $filePath File path
     * @param int $lineNumber Line number
     */
    private function validateSummary(
        string $summary,
        string $elementName,
        string $filePath,
        int $lineNumber
    ): void {
        // SHOULD be under 80 characters
        if (strlen($summary) > 80) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.3',
                "{$filePath}:{$lineNumber}",
                "Summary for '{$elementName}' exceeds 80 characters"
            );
        }

        // SHOULD end with period
        if (!str_ends_with($summary, '.')) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.3',
                "{$filePath}:{$lineNumber}",
                "Summary for '{$elementName}' should end with period"
            );
        }

        // SHOULD NOT restate element name
        $lowerName = strtolower($elementName);
        $lowerSummary = strtolower($summary);
        if (str_contains($lowerSummary, $lowerName . ' ') ||
            str_starts_with($lowerSummary, $lowerName)) {
            $this->addWarning(
                self::LEVEL_SHOULD,
                '1.3',
                "{$filePath}:{$lineNumber}",
                "Summary for '{$elementName}' should not restate element name"
            );
        }
    }

    /**
     * Extracts the summary (first line) from a PHPDoc node.
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     */
    private function extractSummary(PhpDocNode $doc): ?string
    {
        $text = $doc->__toString();
        $lines = explode("\n", trim($text));

        // Find first non-empty, non-tag line
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B*");
            if ($line !== '' && !str_starts_with($line, '@')) {
                return $line;
            }
        }

        return null;
    }

    /**
     * Checks if a PHPDoc node has a specific tag.
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $tagName Tag name to check for
     */
    private function hasTag(PhpDocNode $doc, string $tagName): bool
    {
        foreach ($doc->children as $child) {
            if ($child instanceof PhpDocTagNode && str_starts_with($child->name, $tagName)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a specific tag from a PHPDoc node.
     *
     * @param PhpDocNode $doc Parsed PHPDoc node
     * @param string $tagName Tag name to retrieve
     */
    private function getTag(PhpDocNode $doc, string $tagName): ?PhpDocTagNode
    {
        foreach ($doc->children as $child) {
            if ($child instanceof PhpDocTagNode && str_starts_with($child->name, $tagName)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Adds a validation warning.
     *
     * @param string $level Warning level (MUST, SHOULD, MAY)
     * @param string $rule Rule reference from phpdoc.md
     * @param string $element Element identifier
     * @param string $message Warning message
     */
    private function addWarning(string $level, string $rule, string $element, string $message): void
    {
        $this->warnings[] = [
            'level' => $level,
            'rule' => $rule,
            'element' => $element,
            'message' => $message,
        ];
    }
}
