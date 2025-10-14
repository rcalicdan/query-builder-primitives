<?php
namespace Rcalicdan\QueryBuilderPrimitives;

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
