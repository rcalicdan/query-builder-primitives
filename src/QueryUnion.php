<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait QueryUnion
{
    /**
     * @var array<array{sql: string, all: bool}> The union queries.
     */
    protected array $unions = [];

    /**
     * Add a UNION statement to the query.
     *
     * @param callable(static): static $callback Callback function for the union query.
     * @param bool $all Whether to use UNION ALL (keeps duplicate records).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function union(callable $callback, bool $all = false): static
    {
        $subBuilder = $this->newQuery();

        if (\is_callable([$this, 'getDriver']) && \is_callable([$subBuilder, 'setDriver'])) {
            $driver = $this->getDriver();
            $subBuilder = $subBuilder->setDriver($driver);
        }

        $subBuilder = $callback($subBuilder);

        $hasTable = property_exists($subBuilder, 'table') && $subBuilder->table !== null && $subBuilder->table !== '';

        if (! $hasTable) {
            throw new \InvalidArgumentException('Union query must specify a table using table() method.');
        }

        $instance = clone $this;

        if (! \is_callable([$subBuilder, 'buildSelectQuery'])) {
            throw new \LogicException('The query builder must use the SqlBuilder trait to compile a union.');
        }

        $instance->unions[] = [
            'sql' => $subBuilder->buildSelectQuery(),
            'all' => $all,
        ];

        if (! \is_callable([$subBuilder, 'getCompiledBindings'])) {
            throw new \LogicException('The query builder must use the QueryBuilderCore trait to compile bindings.');
        }

        $instance->bindings['union'] = [...$instance->bindings['union'], ...$subBuilder->getCompiledBindings()];

        return $instance;
    }

    /**
     * Add a UNION ALL statement to the query.
     *
     * @param callable(static): static $callback Callback function for the union query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function unionAll(callable $callback): static
    {
        return $this->union($callback, true);
    }
}
