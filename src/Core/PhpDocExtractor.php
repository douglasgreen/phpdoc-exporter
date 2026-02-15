<?php

declare(strict_types=1);

namespace DouglasGreen\PhpDocExporter\Core;

use Exception;
use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Extracts PHPDoc comments from PHP files using PHPStan's parser.
 *
 * Parses PHP source files and extracts structured PHPDoc information
 * from file headers, classes, interfaces, traits, methods, functions, and properties.
 *
 * @package DouglasGreen\PhpDocExporter\Core
 *
 * @api
 *
 * @since 1.0.0
 */
final class PhpDocExtractor
{
    private Parser $phpParser;

    private PhpDocParser $phpDocParser;

    private Lexer $lexer;

    public function __construct()
    {
        $this->phpParser = (new ParserFactory())->createForNewestSupportedVersion();

        $config = new ParserConfig([]);
        $this->lexer = new Lexer($config);
        $this->phpDocParser = new PhpDocParser($config);
    }

    /**
     * Extracts all PHPDoc blocks from a PHP file.
     *
     * @param string $filePath Path to PHP file
     *
     * @return array{
     *     file: string,
     *     elements: list<array{
     *         type: string,
     *         name: string,
     *         namespace: string|null,
     *         startLine: int,
     *         endLine: int,
     *         doc: PhpDocNode|null,
     *         docText: string|null
     *     }>
     * }
     */
    public function extract(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return ['file' => $filePath, 'elements' => []];
        }

        $stmts = $this->phpParser->parse($content);
        if ($stmts === null) {
            return ['file' => $filePath, 'elements' => []];
        }

        $elements = [];

        // Extract file-level docblock (Standard 2.1)
        $this->extractFileDocBlock($stmts, $elements);

        // Traverse all nodes for other elements
        $this->traverseNodes($stmts, $elements);

        return [
            'file' => $filePath,
            'elements' => $elements,
        ];
    }

    /**
     * Parses a PHPDoc comment string into a structured node.
     *
     * @param string $docComment Raw PHPDoc comment text
     */
    public function parseDocComment(string $docComment): PhpDocNode
    {
        $tokens = new TokenIterator($this->lexer->tokenize($docComment));
        return $this->phpDocParser->parse($tokens);
    }

    /**
     * Extracts file-level docblock if present.
     *
     * File-level docblocks are attached to the first statement (usually declare or namespace)
     * in procedural files or scripts.
     *
     * @param array<Node> $stmts AST statements
     * @param list<array> $elements Output array for extracted elements
     */
    private function extractFileDocBlock(array $stmts, array &$elements): void
    {
        if ($stmts === []) {
            return;
        }

        $firstStmt = $stmts[0];

        // Only treat as file-level docblock if attached to declare/namespace/nop
        // If attached to Class/Interface/Trait, it belongs to that element.
        if ($firstStmt instanceof Class_
            || $firstStmt instanceof Interface_
            || $firstStmt instanceof Trait_
        ) {
            return;
        }

        $doc = $firstStmt->getDocComment();
        if ($doc instanceof Doc) {
            $docText = $doc->getText();
            $docNode = null;

            try {
                $docNode = $this->parseDocComment($docText);
            } catch (Exception) {
                // Keep docText but docNode remains null for invalid PHPDoc
            }

            $elements[] = [
                'type' => 'file',
                'name' => 'file',
                'namespace' => null,
                'startLine' => $firstStmt->getStartLine(),
                'endLine' => $firstStmt->getEndLine(),
                'doc' => $docNode,
                'docText' => $docText,
            ];
        }
    }

    /**
     * Recursively traverses AST nodes to extract PHPDoc blocks.
     *
     * @param array<Node> $nodes AST nodes to traverse
     * @param list<array> $elements Output array for extracted elements
     */
    private function traverseNodes(array $nodes, array &$elements): void
    {
        foreach ($nodes as $node) {
            $this->processNode($node, $elements);

            if (property_exists($node, 'stmts') && is_array($node->stmts)) {
                $this->traverseNodes($node->stmts, $elements);
            }
        }
    }

    /**
     * Processes a single AST node for PHPDoc extraction.
     *
     * @param Node $node AST node to process
     * @param list<array> $elements Output array for extracted elements
     */
    private function processNode(Node $node, array &$elements): void
    {
        $type = $this->getNodeType($node);
        if ($type === null) {
            return;
        }

        $name = $this->getNodeName($node);
        if ($name === null) {
            return;
        }

        $doc = $node->getDocComment();
        $docNode = null;
        $docText = null;

        if ($doc instanceof Doc) {
            $docText = $doc->getText();
            try {
                $docNode = $this->parseDocComment($docText);
            } catch (Exception) {
                // Keep docText but docNode remains null for invalid PHPDoc
            }
        }

        $elements[] = [
            'type' => $type,
            'name' => $name,
            'namespace' => $this->getNamespace($node),
            'startLine' => $node->getStartLine(),
            'endLine' => $node->getEndLine(),
            'doc' => $docNode,
            'docText' => $docText,
        ];
    }

    /**
     * Determines the type of AST node.
     *
     * @param Node $node AST node to check
     *
     * @return string|null Node type or null if not documentable
     */
    private function getNodeType(Node $node): ?string
    {
        return match (true) {
            $node instanceof Class_ => 'class',
            $node instanceof Interface_ => 'interface',
            $node instanceof Trait_ => 'trait',
            $node instanceof ClassMethod => 'method',
            $node instanceof Function_ => 'function',
            $node instanceof Property => 'property',
            default => null,
        };
    }

    /**
     * Extracts the name from an AST node.
     *
     * @param Node $node AST node to extract name from
     */
    private function getNodeName(Node $node): ?string
    {
        if ($node instanceof Class_) {
            return $node->name?->toString();
        }
        if ($node instanceof Interface_) {
            return $node->name->toString();
        }
        if ($node instanceof Trait_) {
            return $node->name->toString();
        }
        if ($node instanceof ClassMethod) {
            return $node->name->toString();
        }
        if ($node instanceof Function_) {
            return $node->name->toString();
        }
        if ($node instanceof Property) {
            return implode(', ', array_map(
                fn ($prop) => $prop->name->toString(),
                $node->props,
            ));
        }

        return null;
    }

    /**
     * Extracts the namespace for a node.
     *
     * @param Node $node AST node to extract namespace from
     */
    private function getNamespace(Node $node): ?string
    {
        $namespace = null;

        // Traverse up to find namespace
        $parent = $node->getAttribute('parent');
        while ($parent !== null) {
            if ($parent instanceof Namespace_) {
                $namespace = $parent->name?->toString();
                break;
            }
            $parent = $parent->getAttribute('parent');
        }

        return $namespace;
    }
}
