<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

use Rcalicdan\QueryBuilderPrimitives\Interfaces\JoinClauseInterface;

trait QueryJoin
{
    /**
     * @var array<array{type: string, table: string, condition: string}> The join clauses for the query.
     */
    protected array $joins = [];

    /**
     * Add a join clause to the query.
     *
     * @param string $table The table to join.
     * @param string|(callable(JoinClauseInterface): JoinClauseInterface) $condition The join condition or a closure for advanced joins.
     * @param string $type The type of join (INNER, LEFT, RIGHT).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function join(string $table, string|callable $condition, string $type = 'INNER'): static
    {
        $instance = clone $this;

        if (\is_callable($condition)) {
            $joinClause = new JoinClause($table, $type);

            $joinClause = $joinClause->setDriver($instance->getDriver());

            $result = $condition($joinClause);

            /** @var JoinClause $result */
            $joinClause = $result;

            $instance->joins[] = [
                'type' => strtoupper($type),
                'table' => $table,
                'condition' => $joinClause->compileConditions(),
            ];

            $instance->bindings['join'] = [
                ...($instance->bindings['join'] ?? []),
                ...$joinClause->getJoinBindings(),
            ];
        } else {
            $instance->joins[] = [
                'type' => strtoupper($type),
                'table' => $table,
                'condition' => $condition,
            ];
        }

        return $instance;
    }

    /**
     * Add a left join clause to the query.
     *
     * @param string $table The table to join.
     * @param string|(callable(JoinClauseInterface): JoinClauseInterface) $condition The join condition or a closure for advanced joins.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function leftJoin(string $table, string|callable $condition): static
    {
        return $this->join($table, $condition, 'LEFT');
    }

    /**
     * Add a right join clause to the query.
     *
     * @param string $table The table to join.
     * @param string|(callable(JoinClauseInterface): JoinClauseInterface) $condition The join condition or a closure for advanced joins.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function rightJoin(string $table, string|callable $condition): static
    {
        return $this->join($table, $condition, 'RIGHT');
    }

    /**
     * Add an inner join clause to the query.
     *
     * @param string $table The table to join.
     * @param string|(callable(JoinClauseInterface): JoinClauseInterface) $condition The join condition or a closure for advanced joins.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function innerJoin(string $table, string|callable $condition): static
    {
        return $this->join($table, $condition, 'INNER');
    }

    /**
     * Add a cross join clause to the query.
     *
     * @param string $table The table to join.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function crossJoin(string $table): static
    {
        $instance = clone $this;
        $instance->joins[] = [
            'type' => 'CROSS',
            'table' => $table,
            'condition' => '',
        ];

        return $instance;
    }
}
