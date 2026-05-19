<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait QueryBuilderCore
{
    /**
     * @var string The table name for the query.
     */
    protected string $table = '';

    /**
     * @var array<string> The columns to select in the query.
     */
    protected array $select = ['*'];

    /**
     * @var array<string, array<mixed>> The parameter bindings for the query, grouped by type.
     */
    protected array $bindings = [
        'select' => [],
        'where' => [],
        'whereIn' => [],
        'whereNotIn' => [],
        'whereBetween' => [],
        'whereRaw' => [],
        'orWhere' => [],
        'orWhereRaw' => [],
        'having' => [],
        'union' => [],
    ];

    /**
     * @var int The current binding index counter.
     */
    protected int $bindingIndex = 0;

    /**
     * @var string|null The database driver name (mysql, pgsql, sqlite)
     */
    protected ?string $driver = null;

    /**
     * Set the table for the query.
     *
     * @param string $table The table name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function table(string $table): static
    {
        $instance = clone $this;
        $instance->table = $table;

        return $instance;
    }

    /**
     * Set the table for the query (alias for table).
     *
     * @param string $table The table name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function from(string $table): static
    {
        $instance = clone $this;
        $instance->table = $table;

        return $instance;
    }

    /**
     * Set the columns to select.
     *
     * @param string ...$columns The columns to select. Omit to select all columns (*).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function select(string ...$columns): static
    {
        $instance = clone $this;
        $instance->select = \count($columns) === 0 ? ['*'] : array_values($columns);
        $instance->bindings['select'] = [];

        return $instance;
    }

    /**
     * Add columns to the existing select.
     *
     * @param string ...$columns The columns to add.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function addSelect(string ...$columns): static
    {
        $instance = clone $this;
        $instance->select = [...$instance->select, ...$columns];

        return $instance;
    }

    /**
     * Add a raw select expression to the query.
     *
     * @param string $expression The raw SQL expression.
     * @param array<mixed> $bindings Parameter bindings for the expression.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \InvalidArgumentException When named bindings are provided.
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        if ($bindings !== [] && ! array_is_list($bindings)) {
            throw new \InvalidArgumentException('Query builder primitives only support positional bindings. Named bindings are not allowed.');
        }

        $instance = clone $this;

        if ($instance->select === ['*']) {
            $instance->select = [$expression];
        } else {
            $instance->select[] = $expression;
        }

        $instance->bindings['select'] = [...$instance->bindings['select'], ...$bindings];

        return $instance;
    }

    /**
     * Select distinct records.
     *
     * @param string ...$columns The columns to select. Omit to select all columns (*).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function selectDistinct(string ...$columns): static
    {
        $instance = $this->select(...$columns);
        $instance->select[0] = 'DISTINCT ' . $instance->select[0];

        return $instance;
    }

    /**
     * Set the database driver.
     *
     * @param string $driver The driver name (mysql, pgsql, sqlite)
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function setDriver(string $driver): static
    {
        $instance = clone $this;
        $instance->driver = strtolower($driver);

        return $instance;
    }

    /**
     * Get the database driver.
     *
     * @return string The database driver name.
     */
    protected function getDriver(): string
    {
        return $this->driver ?? 'mysql'; // Default to MySQL
    }

    /**
     * Generate a parameter placeholder for prepared statements.
     *
     * @return string The placeholder string.
     */
    protected function getPlaceholder(): string
    {
        return '?';
    }

    /**
     * Compiles the final bindings array in the correct order for execution.
     *
     * @return array<mixed>
     */
    protected function getCompiledBindings(): array
    {
        if (\count($this->conditionOrder) > 0) {
            $whereBindings = [];
            foreach ($this->conditionOrder as $item) {
                $whereBindings = [...$whereBindings, ...$item['bindings']];
            }

            return [
                ...$this->bindings['select'],
                ...$whereBindings,
                ...$this->bindings['having'],
                ...$this->bindings['union'],
            ];
        }

        $whereBindings = [
            ...$this->bindings['where'],
            ...$this->bindings['whereIn'],
            ...$this->bindings['whereNotIn'],
            ...$this->bindings['whereBetween'],
            ...$this->bindings['whereRaw'],
            ...$this->bindings['orWhere'],
            ...$this->bindings['orWhereRaw'],
        ];

        return [
            ...$this->bindings['select'],
            ...$whereBindings,
            ...$this->bindings['having'],
            ...$this->bindings['union'],
        ];
    }
}
