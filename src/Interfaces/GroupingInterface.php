<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface GroupingInterface
{
    /**
     * Add a GROUP BY clause to the query.
     *
     * @param string|array<string> $columns The columns to group by.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function groupBy(string|array $columns): static;

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string $column The column name.
     * @param string $direction The sort direction (ASC or DESC).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orderBy(string $column, string $direction = 'ASC'): static;

    /**
     * Add an ORDER BY ASC clause to the query.
     *
     * @param string $column The column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orderByAsc(string $column): static;

    /**
     * Add an ORDER BY DESC clause to the query.
     *
     * @param string $column The column name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orderByDesc(string $column): static;

    /**
     * Set the LIMIT and optionally OFFSET for the query.
     *
     * @param int $limit The maximum number of records to return.
     * @param int|null $offset The number of records to skip.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function limit(int $limit, ?int $offset = null): static;

    /**
     * Set the OFFSET for the query.
     *
     * @param int $offset The number of records to skip.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function offset(int $offset): static;

    /**
     * Set pagination for the query.
     *
     * @param int $page The page number (1-based).
     * @param int $perPage The number of records per page.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function forPage(int $page, int $perPage = 15): static;
}
