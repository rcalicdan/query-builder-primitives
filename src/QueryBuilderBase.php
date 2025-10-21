<?php

namespace Rcalicdan\QueryBuilderPrimitives;

/** @phpstan-consistent-constructor */
class QueryBuilderBase
{
    use QueryAdvancedConditions;
    use QueryBuilderCore;
    use QueryConditions;
    use QueryDebug;
    use QueryGrouping;
    use QueryJoin;
    use SqlBuilder;
}
