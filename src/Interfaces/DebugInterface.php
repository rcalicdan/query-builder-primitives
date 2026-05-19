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
     * Dump the query and continue execution (like Laravel's dump()).
     *
     * @return static Returns the same query builder instance for method chaining.
     */
    public function dump(): static;

    /**
     * Dump the query and die (like Laravel's dd()).
     */
    public function dd(): never;
}
