<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface ConditionInterface
{
    /**
     * Add a WHERE clause to the query.
     *
     * @param string $column The column name.
     * @param mixed $operator The comparison operator or value if only 2 arguments.
     * @param mixed $value The value to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function where(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add an OR WHERE clause to the query.
     *
     * @param string $column The column name.
     * @param mixed $operator The comparison operator or value if only 2 arguments.
     * @param mixed $value The value to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhere(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a WHERE IN clause to the query.
     *
     * @param string $column The column name.
     * @param array<mixed> $values The values to check against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereIn(string $column, array $values): static;

    /**
     * Add a WHERE NOT IN clause to the query.
     *
     * @param string $column The column name.
     * @param array<mixed> $values The values to check against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereNotIn(string $column, array $values): static;

    /**
     * Add a WHERE BETWEEN clause to the query.
     *
     * @param array<mixed> $values An array with exactly 2 values for the range.
     * @param string $column The column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \InvalidArgumentException When values array doesn't contain exactly 2 elements.
     */
    public function whereBetween(string $column, array $values): static;

    /**
     * Add a WHERE NULL clause to the query.
     *
     * @param string $column The column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereNull(string $column): static;

    /**
     * Add a WHERE NOT NULL clause to the query.
     *
     * @param string $column The column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereNotNull(string $column): static;

    /**
     * Add a LIKE clause to the query.
     *
     * @param string $column The column name.
     * @param string $value The value to search for.
     * @param string $side The side to add wildcards ('before', 'after', 'both').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function like(string $column, string $value, string $side = 'both'): static;

    /**
     * Add a HAVING clause to the query.
     *
     * @param string $column The column name.
     * @param mixed $operator The comparison operator or value if only 2 arguments.
     * @param mixed $value The value to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function having(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a raw HAVING condition.
     *
     * @param string $condition The raw SQL condition.
     * @param array<mixed> $bindings Parameter bindings for the condition.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \InvalidArgumentException When named bindings are provided.
     */
    public function havingRaw(string $condition, array $bindings = []): static;

    /**
     * Add a raw WHERE condition.
     *
     * @param string $condition The raw SQL condition.
     * @param array<mixed> $bindings Parameter bindings for the condition.
     * @param string $operator Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \InvalidArgumentException When named bindings are provided.
     */
    public function whereRaw(string $condition, array $bindings = [], string $operator = 'AND'): static;

    /**
     * Add a raw OR WHERE condition.
     *
     * @param string $condition The raw SQL condition.
     * @param array<mixed> $bindings Parameter bindings for the condition.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereRaw(string $condition, array $bindings = []): static;

    /**
     * Add a WHERE clause that compares two columns.
     *
     * @param string $first The first column name.
     * @param string|null $operator The comparison operator or second column if only 2 arguments.
     * @param string|null $second The second column name.
     * @param string $boolean The logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereColumn(string $first, ?string $operator = null, ?string $second = null, string $boolean = 'AND'): static;

    /**
     * Add an OR WHERE clause that compares two columns.
     *
     * @param string $first The first column name.
     * @param string|null $operator The comparison operator or second column if only 2 arguments.
     * @param string|null $second The second column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereColumn(string $first, ?string $operator = null, ?string $second = null): static;

    /**
     * Reset all WHERE conditions and bindings.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function resetWhere(): static;
}
