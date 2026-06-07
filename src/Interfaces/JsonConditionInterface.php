<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface JsonConditionInterface
{
    /**
     * Add a JSON path comparison condition to the query.
     * Supports dot/arrow notation: e.g. 'options->preferences->theme'
     *
     * @param string $column The column name with path (e.g., 'options->preferences->theme').
     * @param mixed $operator Comparison operator (or value if only 2 arguments).
     * @param mixed $value The value to compare against.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJson(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static;

    /**
     * Add an OR JSON path comparison condition to the query.
     *
     * @param string $column The column name with path.
     * @param mixed $operator Comparison operator (or value if only 2 arguments).
     * @param mixed $value The value to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJson(string $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a condition checking if a JSON array contains a specific value.
     *
     * @param string $column The column name with path (e.g. 'options->languages').
     * @param mixed $value The value to search for.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJsonContains(string $column, mixed $value, string $boolean = 'AND'): static;

    /**
     * Add an OR condition checking if a JSON array contains a specific value.
     *
     * @param string $column The column name with path.
     * @param mixed $value The value to search for.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJsonContains(string $column, mixed $value): static;

    /**
     * Add a condition checking the length of a JSON array.
     *
     * @param string $column The column name with path (e.g. 'options->tags').
     * @param string $operator Comparison operator (e.g., '>', '=').
     * @param int $value The length to compare against.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJsonLength(string $column, string $operator, int $value, string $boolean = 'AND'): static;

    /**
     * Add an OR condition checking the length of a JSON array.
     *
     * @param string $column The column name with path.
     * @param string $operator Comparison operator.
     * @param int $value The length to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJsonLength(string $column, string $operator, int $value): static;

    /**
     * Add a condition checking if a JSON array does NOT contain a specific value.
     *
     * @param string $column The column name with path (e.g. 'options->languages').
     * @param mixed $value The value to search for.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJsonDoesntContain(string $column, mixed $value, string $boolean = 'AND'): static;

    /**
     * Add an OR condition checking if a JSON array does NOT contain a specific value.
     *
     * @param string $column The column name with path.
     * @param mixed $value The value to search for.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJsonDoesntContain(string $column, mixed $value): static;
}
