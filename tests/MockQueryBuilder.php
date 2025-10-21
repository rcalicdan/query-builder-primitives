<?php

namespace Tests;

use Rcalicdan\QueryBuilderPrimitives\QueryAdvancedConditions;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderCore;
use Rcalicdan\QueryBuilderPrimitives\QueryConditions;
use Rcalicdan\QueryBuilderPrimitives\QueryDebug;
use Rcalicdan\QueryBuilderPrimitives\QueryGrouping;
use Rcalicdan\QueryBuilderPrimitives\QueryJoin;
use Rcalicdan\QueryBuilderPrimitives\SqlBuilder;

class MockQueryBuilder
{
    use QueryAdvancedConditions;
    use QueryBuilderCore;
    use QueryConditions;
    use QueryDebug;
    use QueryGrouping;
    use QueryJoin;
    use SqlBuilder;

    public function __construct(?string $table = null)
    {
        if ($table !== null) {
            $this->table = $table;
        }
    }

    public static function table(string $table): self
    {
        return new self($table);
    }
}
