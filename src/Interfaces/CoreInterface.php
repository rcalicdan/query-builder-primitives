<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface CoreInterface
{
    /**
     * Set the table for the query (alias for table).
     *
     * @param string $table The table name.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function from(string $table): static;

    /**
     * Set the columns to select.
     *
     * @param string ...$columns The columns to select. Omit to select all columns (*).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function select(string ...$columns): static;

    /**
     * Add columns to the existing select.
     *
     * @param string ...$columns The columns to add.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function addSelect(string ...$columns): static;

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
    public function selectRaw(string $expression, array $bindings = []): static;

    /**
     * Select distinct records.
     *
     * @param string ...$columns The columns to select. Omit to select all columns (*).
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function selectDistinct(string ...$columns): static;

    /**
     * Set the database driver.
     *
     * @param string $driver The driver name (mysql, pgsql, sqlite)
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function setDriver(string $driver): static;
}
