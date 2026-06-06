<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

/**
 * Contract for building advanced JOIN conditions.
 * Extends QueryBuilderPrimitiveInterface to allow full query building (like subqueries)
 * while adding join-specific on() constraints.
 */
interface JoinClauseInterface extends QueryBuilderPrimitiveInterface
{
    /**
     * Add an "ON" clause to the join (alias for whereColumn).
     *
     * @param string $first The first column name.
     * @param string|null $operator The comparison operator or second column if only 2 arguments.
     * @param string|null $second The second column name.
     * @param string $boolean The logical operator ('AND' or 'OR').
     *
     * @return static Returns a new join clause instance for method chaining.
     */
    public function on(string $first, ?string $operator = null, ?string $second = null, string $boolean = 'AND'): static;

    /**
     * Add an "OR ON" clause to the join (alias for orWhereColumn).
     *
     * @param string $first The first column name.
     * @param string|null $operator The comparison operator or second column if only 2 arguments.
     * @param string|null $second The second column name.
     *
     * @return static Returns a new join clause instance for method chaining.
     */
    public function orOn(string $first, ?string $operator = null, ?string $second = null): static;
}
