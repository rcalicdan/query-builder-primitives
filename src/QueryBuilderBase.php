<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

use Rcalicdan\QueryBuilderPrimitives\Interfaces\QueryBuilderInterface;

/**
 * @phpstan-consistent-constructor
 */
class QueryBuilderBase implements QueryBuilderInterface
{
    use QueryAdvancedConditions;
    use QueryBuilderCore;
    use QueryConditions;
    use QueryDebug;
    use QueryGrouping;
    use QueryJoin;
    use QueryLocking;
    use QueryUnion;
    use SqlBuilder;
}
