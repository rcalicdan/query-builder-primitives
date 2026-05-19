<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface LockingInterface
{
    /**
     * Lock selected rows for update (exclusive lock).
     * Prevents other transactions from reading or locking the rows until the transaction ends.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function lockForUpdate(): static;

    /**
     * Lock selected rows for share (shared lock).
     * Allows other transactions to read but not modify the rows.
     * Uses LOCK IN SHARE MODE for MySQL, FOR SHARE for PostgreSQL/SQLite.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function lockForShare(): static;

    /**
     * Add NOWAIT modifier to the lock clause.
     * Causes the query to fail immediately with an error instead of waiting
     * if any of the selected rows are currently locked.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \LogicException When no lock mode has been set.
     */
    public function noWait(): static;

    /**
     * Add SKIP LOCKED modifier to the lock clause.
     * Skips any rows that are currently locked by another transaction
     * instead of waiting or erroring.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \LogicException When no lock mode has been set.
     */
    public function skipLocked(): static;

    /**
     * Specify tables to lock (PostgreSQL OF clause).
     * Restricts the lock to the specified tables when using joins.
     *
     * @param string|array<string> $tables One or more table names to lock.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \LogicException When no lock mode has been set.
     */
    public function lockOf(string|array $tables): static;

    /**
     * Remove the lock clause from the query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function withoutLock(): static;
}
