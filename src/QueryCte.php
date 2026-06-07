<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

use Rcalicdan\QueryBuilderPrimitives\Interfaces\QueryBuilderPrimitiveInterface;

trait QueryCte
{
    /**
     * @var array<array{name: string, sql: string, recursive: bool}> The tracked CTEs.
     */
    protected array $ctes = [];

    /**
     * Add a Common Table Expression (CTE) to the query.
     *
     * @param string $name The temporary table name for the CTE.
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Closure to build the CTE select query.
     * @param bool $recursive Whether to compile as a recursive CTE (adds WITH RECURSIVE).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function with(string $name, callable $callback, bool $recursive = false): static
    {
        $subBuilder = $this->newQuery();

        $subBuilder = $subBuilder->setDriver($this->getDriver());

        $result = $callback($subBuilder);

        /** @var static $subBuilder */
        $subBuilder = $result;

        $instance = clone $this;
        $instance->ctes[] = [
            'name' => $name,
            'sql' => $subBuilder->buildSelectQuery(),
            'recursive' => $recursive,
        ];

        $instance->bindings['cte'] = [
            ...($instance->bindings['cte'] ?? []),
            ...$subBuilder->getCompiledBindings(),
        ];

        return $instance;
    }

    /**
     * Add a recursive Common Table Expression (CTE) to the query.
     *
     * @param string $name The temporary table name for the CTE.
     * @param callable(QueryBuilderPrimitiveInterface): QueryBuilderPrimitiveInterface $callback Closure to build the CTE select query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function withRecursive(string $name, callable $callback): static
    {
        return $this->with($name, $callback, true);
    }
}
