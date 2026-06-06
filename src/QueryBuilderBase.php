<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

use Rcalicdan\QueryBuilderPrimitives\Interfaces\QueryBuilderPrimitiveInterface;

/**
 * @phpstan-consistent-constructor
 */
class QueryBuilderBase implements QueryBuilderPrimitiveInterface
{
    use QueryAdvancedConditions;
    use QueryBuilderCore;
    use QueryConditions;
    use QueryConditionable;
    use QueryDebug;
    use QueryGrouping;
    use QueryJoin;
    use QueryLocking;
    use QueryUnion;
    use SqlBuilder;
}
