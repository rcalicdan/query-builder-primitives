<?php

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
        'where' => [],
        'whereIn' => [],
        'whereNotIn' => [],
        'whereBetween' => [],
        'whereRaw' => [],
        'orWhere' => [],
        'orWhereRaw' => [],
        'having' => [],
    ];

    /**
     * @var int The current binding index counter.
     */
    protected int $bindingIndex = 0;

    /**
     * @var string|null The database driver name (mysql, pgsql, sqlsrv, sqlite)
     */
    protected ?string $driver = null;

    /**
     * Set the table for the query.
     *
     * @param  string  $table  The table name.
     * @return static Returns a new query builder instance for method chaining.
     */
    public function table(string $table): static
    {
        $instance = clone $this;
        $instance->table = $table;

        return $instance;
    }

    /**
     * Set the columns to select.
     *
     * @param  string|array<string>  $columns  The columns to select.
     * @return static Returns a new query builder instance for method chaining.
     */
    public function select(string|array $columns = '*'): static
    {
        $instance = clone $this;
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $instance->select = $columns;

        return $instance;
    }

    /**
     * Add columns to the existing select.
     *
     * @param  string|array<string>  $columns  The columns to add.
     * @return static Returns a new query builder instance for method chaining.
     */
    public function addSelect(string|array $columns): static
    {
        $instance = clone $this;
        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }
        $instance->select = array_merge($instance->select, $columns);

        return $instance;
    }

    /**
     * Select distinct records.
     *
     * @param  string|array<string>  $columns  The columns to select.
     * @return static Returns a new query builder instance for method chaining.
     */
    public function selectDistinct(string|array $columns = '*'): static
    {
        $instance = $this->select($columns);

        if ($instance->select !== [] && $instance->select[0] !== '*') {
            $instance->select[0] = 'DISTINCT '.$instance->select[0];
        } elseif ($instance->select[0] === '*') {
            $instance->select[0] = 'DISTINCT *';
        }

        return $instance;
    }

    /**
     * Set the database driver.
     *
     * @param  string  $driver  The driver name (mysql, pgsql, sqlsrv, mssql, sqlite)
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
        if (count($this->conditionOrder) > 0) {
            $whereBindings = [];
            foreach ($this->conditionOrder as $item) {
                $whereBindings = array_merge($whereBindings, $item['bindings']);
            }

            return array_merge($whereBindings, $this->bindings['having']);
        }

        $whereBindings = array_merge(
            $this->bindings['where'],
            $this->bindings['whereIn'],
            $this->bindings['whereNotIn'],
            $this->bindings['whereBetween'],
            $this->bindings['whereRaw'],
            $this->bindings['orWhere'],
            $this->bindings['orWhereRaw']
        );

        return array_merge($whereBindings, $this->bindings['having']);
    }
}
