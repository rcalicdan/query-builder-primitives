<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\FuncCall\ArrayMergeOfNonArraysToSimpleArrayRector;
use Rector\CodingStyle\Rector\FuncCall\ArraySpreadInsteadOfArrayMergeRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withRules([
        ArraySpreadInsteadOfArrayMergeRector::class,
        ArrayMergeOfNonArraysToSimpleArrayRector::class,
    ])
;
