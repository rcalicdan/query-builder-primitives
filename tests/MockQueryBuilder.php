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
    use QueryBuilderCore;
    use QueryConditions;
    use QueryAdvancedConditions;
    use QueryJoin;
    use QueryGrouping;
    use QueryDebug;
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