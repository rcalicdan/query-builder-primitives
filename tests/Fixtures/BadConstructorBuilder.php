<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

/**
 * Developer FORGETS to override newQuery()!
 */
class BadConstructorBuilder extends QueryBuilderBase
{
    public function __construct(private DummyConnection $connection)
    {
    }
}
