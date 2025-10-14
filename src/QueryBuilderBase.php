<?php
namespace Rcalicdan\QueryBuilderPrimitives;

/** @phpstan-consistent-constructor */
class QueryBuilderBase
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryGrouping;
    use QueryConditions;
    use QueryJoin;
    use QueryAdvancedConditions;
    use QueryDebug;
}
