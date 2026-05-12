<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait QueryLocking
{
    /**
     * @var string|null The locking clause for the query (e.g. FOR UPDATE, FOR SHARE).
     */
    protected ?string $lockMode = null;

    /**
     * @var string|null Lock modifier: 'NOWAIT' or 'SKIP LOCKED'.
     */
    protected ?string $lockModifier = null;

    /**
     * @var array<string> Specific tables to lock (PostgreSQL OF clause).
     */
    protected array $lockOf = [];

    /**
     * Lock selected rows for update (exclusive lock).
     * Prevents other transactions from reading or locking the rows until the transaction ends.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function lockForUpdate(): static
    {
        $instance = clone $this;
        $instance->lockMode = 'FOR UPDATE';
        $instance->lockModifier = null;

        return $instance;
    }

    /**
     * Lock selected rows for share (shared lock).
     * Allows other transactions to read but not modify the rows.
     * Uses LOCK IN SHARE MODE for MySQL, FOR SHARE for PostgreSQL/SQLite.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function lockForShare(): static
    {
        $instance = clone $this;
        $instance->lockMode = 'FOR SHARE';
        $instance->lockModifier = null;

        return $instance;
    }

    /**
     * Add NOWAIT modifier to the lock clause.
     * Causes the query to fail immediately with an error instead of waiting
     * if any of the selected rows are currently locked.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \LogicException When no lock mode has been set.
     */
    public function noWait(): static
    {
        if ($this->lockMode === null) {
            throw new \LogicException('Cannot add NOWAIT modifier without a lock mode. Call lockForUpdate() or lockForShare() first.');
        }

        $instance = clone $this;
        $instance->lockModifier = 'NOWAIT';

        return $instance;
    }

    /**
     * Add SKIP LOCKED modifier to the lock clause.
     * Skips any rows that are currently locked by another transaction
     * instead of waiting or erroring.
     *
     * @return static Returns a new query builder instance for method chaining.
     *
     * @throws \LogicException When no lock mode has been set.
     */
    public function skipLocked(): static
    {
        if ($this->lockMode === null) {
            throw new \LogicException('Cannot add SKIP LOCKED modifier without a lock mode. Call lockForUpdate() or lockForShare() first.');
        }

        $instance = clone $this;
        $instance->lockModifier = 'SKIP LOCKED';

        return $instance;
    }

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
    public function lockOf(string|array $tables): static
    {
        if ($this->lockMode === null) {
            throw new \LogicException(
                'Cannot add OF clause without a lock mode. Call lockForUpdate() or lockForShare() first.'
            );
        }

        if ($this->getDriver() === 'mysql') {
            throw new \LogicException(
                'MySQL does not support the OF clause for locking. '
                    . 'This feature is only available on PostgreSQL.'
            );
        }

        $instance = clone $this;
        $instance->lockOf = \is_string($tables) ? [$tables] : $tables;

        return $instance;
    }

    /**
     * Remove the lock clause from the query.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function withoutLock(): static
    {
        $instance = clone $this;
        $instance->lockMode = null;
        $instance->lockModifier = null;
        $instance->lockOf = [];

        return $instance;
    }

    /**
     * Build the locking SQL clause based on the current driver and lock settings.
     *
     * @return string The locking clause or empty string if no lock is set.
     */
    protected function buildLockClause(): string
    {
        if ($this->lockMode === null) {
            return '';
        }

        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => $this->buildMySqlLockClause(),
            'pgsql' => $this->buildPostgreSqlLockClause(),
            'sqlite' => $this->buildSqliteLockClause(),
            default => $this->buildMySqlLockClause(),
        };
    }

    /**
     * Build the MySQL-specific locking clause.
     * MySQL uses LOCK IN SHARE MODE instead of FOR SHARE, and does not support OF.
     *
     * @return string The MySQL locking clause.
     */
    protected function buildMySqlLockClause(): string
    {
        if ($this->lockMode === null) {
            return '';
        }

        if ($this->lockModifier !== null && $this->lockMode === 'FOR SHARE') {
            throw new \LogicException(
                "MySQL does not support the {$this->lockModifier} modifier with LOCK IN SHARE MODE. "
                    . 'Use lockForUpdate() if you need NOWAIT or SKIP LOCKED.'
            );
        }

        $clause = $this->lockMode === 'FOR SHARE'
            ? 'LOCK IN SHARE MODE'
            : 'FOR UPDATE';

        if ($this->lockModifier !== null) {
            $clause .= ' ' . $this->lockModifier;
        }

        return $clause;
    }

    /**
     * Build the PostgreSQL-specific locking clause.
     * PostgreSQL supports FOR UPDATE, FOR SHARE, OF, NOWAIT, and SKIP LOCKED.
     *
     * @return string The PostgreSQL locking clause.
     */
    protected function buildPostgreSqlLockClause(): string
    {
        if ($this->lockMode === null) {
            return '';
        }

        $clause = $this->lockMode;

        if ($this->lockOf !== []) {
            $clause .= ' OF ' . implode(', ', $this->lockOf);
        }

        if ($this->lockModifier !== null) {
            $clause .= ' ' . $this->lockModifier;
        }

        return $clause;
    }

    /**
     * Build the SQLite-specific locking clause.
     * SQLite has very limited locking support; only FOR UPDATE is approximated.
     * Modifiers and OF are silently ignored as SQLite handles concurrency at the file level.
     *
     * @return never Never returns a value.
     *
     * @throws \LogicException SQLite does not support row-level locking.
     */
    protected function buildSqliteLockClause(): never
    {
        throw new \LogicException(
            'SQLite does not support row-level locking. '
                . 'Use transactions (BEGIN EXCLUSIVE / BEGIN IMMEDIATE) at the connection level instead.'
        );
    }
}
