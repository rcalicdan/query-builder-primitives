<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface UnionInterface
{
    /**
     * Add a UNION statement to the query.
     *
     * @param callable(static): static $callback Callback function for the union query.
     * @param bool $all Whether to use UNION ALL (keeps duplicate records).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function union(callable $callback, bool $all = false): static;

    /**
     * Add a UNION ALL statement to the query.
     *
     * @param callable(static): static $callback Callback function for the union query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function unionAll(callable $callback): static;
}
