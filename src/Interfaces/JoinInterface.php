<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface JoinInterface
{
    /**
     * Add a join clause to the query.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     * @param string $type The type of join (INNER, LEFT, RIGHT).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function join(string $table, string $condition, string $type = 'INNER'): static;

    /**
     * Add a left join clause to the query.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function leftJoin(string $table, string $condition): static;

    /**
     * Add a right join clause to the query.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function rightJoin(string $table, string $condition): static;

    /**
     * Add an inner join clause to the query.
     *
     * @param string $table The table to join.
     * @param string $condition The join condition.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function innerJoin(string $table, string $condition): static;

    /**
     * Add a cross join clause to the query.
     *
     * @param string $table The table to join.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function crossJoin(string $table): static;
}
