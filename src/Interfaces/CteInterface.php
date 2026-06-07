<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface CteInterface
{
    /**
     * Add a Common Table Expression (CTE) to the query.
     *
     * @param string $name The temporary table name for the CTE.
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Closure to build the CTE select query.
     * @param bool $recursive Whether to compile as a recursive CTE (adds WITH RECURSIVE).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function with(string $name, callable $callback, bool $recursive = false): static;

    /**
     * Add a recursive Common Table Expression (CTE) to the query.
     *
     * @param string $name The temporary table name for the CTE.
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Closure to build the CTE select query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function withRecursive(string $name, callable $callback): static;
}
