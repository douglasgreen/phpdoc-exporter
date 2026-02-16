<?php

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php81\Rector\ClassMethod\NewInInitializerRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/bin/phpdoc-exporter',
        __DIR__ . '/.php-cs-fixer.php',
        __DIR__ . '/rector.php',
        __DIR__ . '/src',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        earlyReturn: true,
        phpunitCodeQuality: true,
    )
    ->withComposerBased(
        symfony: true,
    )
    ->withSkip([
        // This rule always injects Session into AppContainer, breaking unit tests.
        NewInInitializerRector::class => [
            __DIR__ . '/src/AppContainer.php',
        ],

        // These tags are still needed.
        RemoveUselessVarTagRector::class,
        RemoveUselessReturnTagRector::class,
    ])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withImportNames(removeUnusedImports: true)
    ->withPhpVersion(PhpVersion::PHP_83);
