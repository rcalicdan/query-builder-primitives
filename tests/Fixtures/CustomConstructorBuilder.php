<?php 

namespace Tests\Fixtures;

use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

class CustomConstructorBuilder extends QueryBuilderBase
{
    /**
     * Developer adds a required constructor parameter
     */
    public function __construct(private DummyConnection $connection)
    {
    }

    /**
     * Developer overrides the protected hook to pass the required dependency
     */
    protected function newQuery(): static
    {
        return new static($this->connection);
    }

    /**
     * Helper just to verify the connection carried over in tests
     */
    public function getConnection(): DummyConnection
    {
        return $this->connection;
    }
}