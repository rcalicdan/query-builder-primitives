<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface AdvancedConditionInterface
{
    /**
     * Add a custom condition group with specific logic (Local group - keeps parent type).
     *
     * @param callable(static): static $callback Callback function that receives a new query builder instance.
     * @param string $logicalOperator How this group connects to others ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereGroup(callable $callback, string $logicalOperator = 'AND'): static;

    /**
     * Add nested WHERE conditions with custom logic (Local group - keeps parent type).
     *
     * @param callable(static): static $callback Callback function for nested conditions.
     * @param string $operator How to connect with existing conditions.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereNested(callable $callback, string $operator = 'AND'): static;

    /**
     * Add a nested OR WHERE condition with custom logic (Local group - keeps parent type).
     *
     * @param callable(static): static $callback Callback function for nested conditions.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereNested(callable $callback): static;

    /**
     * Add conditions with EXISTS clause (Subquery - restricted to primitives).
     *
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Callback function for the EXISTS subquery.
     * @param string $operator Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereExists(callable $callback, string $operator = 'AND'): static;

    /**
     * Add conditions with NOT EXISTS clause (Subquery - restricted to primitives).
     *
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Callback function for the NOT EXISTS subquery.
     * @param string $operator Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereNotExists(callable $callback, string $operator = 'AND'): static;

    /**
     * Add an OR WHERE EXISTS clause (Subquery - restricted to primitives).
     *
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Callback function for the EXISTS subquery.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereExists(callable $callback): static;

    /**
     * Add an OR WHERE NOT EXISTS clause (Subquery - restricted to primitives).
     *
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Callback function for the NOT EXISTS subquery.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereNotExists(callable $callback): static;

    /**
     * Add a WHERE clause with a subquery (Subquery - restricted to primitives).
     *
     * @param string $column The column name.
     * @param string $operator The comparison operator.
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Callback function for the subquery.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereSub(string $column, string $operator, callable $callback): static;
}
