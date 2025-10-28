<?php

declare(strict_types=1);

use Frosh\Rector\Set\ShopwareSetList;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use Spatie\Ray\Rector\RemoveRayCallRector;

return RectorConfig::configure()
    // Define paths to look into.
    ->withPaths([
        __DIR__ . '/src',
    ])
    // Set stuff to apply.
    ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true)
    // Pick up sets based on installed packages automatically.
    ->withComposerBased(symfony: true)
    // Migrate annotations to attributes where possible.
    ->withAttributesSets(all: true)
    // Define used sets.
    ->withSets([
        LevelSetList::UP_TO_PHP_82,
        ShopwareSetList::SHOPWARE_6_6_0,
    ])
    // Skip rules or files.
    ->withSkip([
        // We don't want to use arrow functions everywhere.
        ClosureToArrowFunctionRector::class,
        // Keep empty() function for now.
        DisallowedEmptyRuleFixerRector::class,
    ])
    // Rules to apply additionally.
    ->withRules([
        // All files should use strict typing.
        DeclareStrictTypesRector::class,
    ]);
