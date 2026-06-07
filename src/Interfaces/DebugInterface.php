<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface DebugInterface
{
    /**
     * Get the built SQL query for debugging purposes.
     *
     * @return string The complete SQL query.
     */
    public function toSql(): string;

    /**
     * Get the parameter bindings for debugging purposes.
     *
     * @return array<mixed> The parameter bindings.
     */
    public function getBindings(): array;

    /**
     * Get the SQL query with bindings interpolated (for debugging only).
     * WARNING: This should NEVER be used for actual query execution due to SQL injection risks.
     *
     * @return string The SQL query with values interpolated.
     */
    public function toRawSql(): string;

    /**
     * Output the compiled SQL, raw SQL, and bindings, then continue execution.
     *
     * @return static Returns the same query builder instance for method chaining.
     */
    public function debug(): static;

    /**
     * Output the compiled SQL, raw SQL, and bindings, then immediately terminate execution.
     *
     * @return never
     */
    public function halt(): never;
}
