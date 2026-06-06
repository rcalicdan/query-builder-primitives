<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait SqlBuilder
{
    /**
     * Build the SELECT SQL query string.
     *
     * @return string The complete SELECT SQL query.
     */
    protected function buildSelectQuery(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->select);
        $sql .= ' FROM ' . $this->table;

        foreach ($this->joins as $join) {
            if ($join['type'] === 'CROSS') {
                $sql .= " CROSS JOIN {$join['table']}";
            } else {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }
        }

        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        $havingSql = $this->buildHavingClause();
        if ($havingSql !== '') {
            $sql .= ' HAVING ' . $havingSql;
        }

        if ($this->unions !== []) {
            foreach ($this->unions as $union) {
                $keyword = $union['all'] ? 'UNION ALL' : 'UNION';
                $sql .= " {$keyword} {$union['sql']}";
            }
        }

        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        $sql = $this->applyPagination($sql);

        $lockClause = $this->buildLockClause();
        if ($lockClause !== '') {
            $sql .= ' ' . $lockClause;
        }

        return $sql;
    }

    /**
     * Apply database-specific pagination syntax.
     *
     * @param string $sql The SQL query string.
     *
     * @return string The SQL query with pagination applied.
     */
    protected function applyPagination(string $sql): string
    {
        if ($this->limit === null && $this->offset === null) {
            return $sql;
        }

        return $this->applyStandardPagination($sql);
    }

    /**
     * Apply standard pagination (LIMIT...OFFSET) for MySQL, PostgreSQL, and SQLite.
     *
     * @param string $sql The SQL query string.
     *
     * @return string The SQL query with standard pagination.
     */
    protected function applyStandardPagination(string $sql): string
    {
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }

    /**
     * Build the COUNT SQL query string.
     *
     * @param string $column The column to count.
     *
     * @return string The complete COUNT SQL query.
     */
    protected function buildCountQuery(string $column = '*'): string
    {
        $sql = "SELECT COUNT({$column}) FROM " . $this->table;

        foreach ($this->joins as $join) {
            if ($join['type'] === 'CROSS') {
                $sql .= " CROSS JOIN {$join['table']}";
            } else {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }
        }

        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        $havingSql = $this->buildHavingClause();
        if ($havingSql !== '') {
            $sql .= ' HAVING ' . $havingSql;
        }

        return $sql;
    }

    /**
     * Build the INSERT SQL query string.
     *
     * @param array<string, mixed> $data The data to insert.
     *
     * @return string The complete INSERT SQL query.
     */
    protected function buildInsertQuery(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, \count($data), '?'));

        return "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
    }

    /**
     * Build the INSERT BATCH SQL query string.
     *
     * @param array<array<string, mixed>> $data The data array for batch insert.
     *
     * @return string The complete INSERT SQL query.
     *
     * @throws \InvalidArgumentException When data format is invalid.
     */
    protected function buildInsertBatchQuery(array $data): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Invalid data format for batch insert');
        }

        $firstRow = reset($data);

        if (! \is_array($firstRow)) {
            throw new \InvalidArgumentException('Invalid data format for batch insert');
        }

        $columns = implode(', ', array_keys($firstRow));
        $placeholders = '(' . implode(', ', array_fill(0, \count($firstRow), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, \count($data), $placeholders));

        return "INSERT INTO {$this->table} ({$columns}) VALUES {$allPlaceholders}";
    }

    /**
     * Build the UPSERT SQL query string (INSERT with conflict resolution).
     * Supports both single and batch upserts for MySQL, PostgreSQL, and SQLite.
     *
     * @param array<string, mixed>|array<array<string, mixed>> $data The data to insert/update.
     * @param string|array<string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<string>|null $updateColumns Columns to update on conflict (null = all except unique).
     *
     * @return string The complete UPSERT SQL query.
     *
     * @throws \InvalidArgumentException When parameters are invalid.
     */
    protected function buildUpsertQuery(array $data, string|array $uniqueColumns, ?array $updateColumns = null): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Data cannot be empty for upsert');
        }

        $isBatch = \is_array(reset($data)) && \is_array(reset($data));

        if (! $isBatch) {
            $data = [$data];
        }

        /** @var array<array<string, mixed>> $data */
        $uniqueColumns = \is_string($uniqueColumns) ? [$uniqueColumns] : $uniqueColumns;

        if ($uniqueColumns === []) {
            throw new \InvalidArgumentException('Unique columns must be specified for upsert');
        }

        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => $this->buildMySqlUpsert($data, $uniqueColumns, $updateColumns),
            'pgsql' => $this->buildPostgreSqlUpsert($data, $uniqueColumns, $updateColumns),
            'sqlite' => $this->buildSqliteUpsert($data, $uniqueColumns, $updateColumns),
            default => throw new \InvalidArgumentException("Unsupported driver: {$driver}. Supported drivers are mysql, pgsql, and sqlite."),
        };
    }

    /**
     * Build MySQL upsert query using ON DUPLICATE KEY UPDATE.
     * Supports batch inserts.
     *
     * @param array<array<string, mixed>> $data The data to insert/update (array of records).
     * @param array<string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<string>|null $updateColumns Columns to update on conflict.
     *
     * @return string The MySQL upsert query.
     */
    protected function buildMySqlUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, \count($row), '?')) . ')';
        }
        $allPlaceholders = implode(', ', $rowPlaceholders);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES {$allPlaceholders}";

        $columnsToUpdate = $updateColumns ?? array_diff(array_keys($firstRow), $uniqueColumns);

        if ($columnsToUpdate !== []) {
            $sql .= ' AS new';

            $updateParts = [];
            foreach ($columnsToUpdate as $column) {
                $updateParts[] = "{$column} = new.{$column}";
            }
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
        }

        return $sql;
    }

    /**
     * Build PostgreSQL upsert query using ON CONFLICT DO UPDATE.
     * Supports batch inserts.
     *
     * @param array<array<string, mixed>> $data The data to insert/update (array of records).
     * @param array<string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<string>|null $updateColumns Columns to update on conflict.
     *
     * @return string The PostgreSQL upsert query.
     */
    protected function buildPostgreSqlUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, \count($row), '?')) . ')';
        }
        $allPlaceholders = implode(', ', $rowPlaceholders);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES {$allPlaceholders}";

        $conflictColumns = implode(', ', $uniqueColumns);
        $sql .= " ON CONFLICT ({$conflictColumns})";

        $columnsToUpdate = $updateColumns ?? array_diff(array_keys($firstRow), $uniqueColumns);

        if ($columnsToUpdate !== []) {
            $updateParts = [];
            foreach ($columnsToUpdate as $column) {
                $updateParts[] = "{$column} = EXCLUDED.{$column}";
            }
            $sql .= ' DO UPDATE SET ' . implode(', ', $updateParts);
        } else {
            $sql .= ' DO NOTHING';
        }

        return $sql;
    }

    /**
     * Build SQLite upsert query using ON CONFLICT DO UPDATE.
     * Supports batch inserts.
     *
     * @param array<array<string, mixed>> $data The data to insert/update (array of records).
     * @param array<string> $uniqueColumns Column(s) that determine uniqueness.
     * @param array<string>|null $updateColumns Columns to update on conflict.
     *
     * @return string The SQLite upsert query.
     */
    protected function buildSqliteUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, \count($row), '?')) . ')';
        }
        $allPlaceholders = implode(', ', $rowPlaceholders);

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES {$allPlaceholders}";

        $conflictColumns = implode(', ', $uniqueColumns);
        $sql .= " ON CONFLICT ({$conflictColumns})";

        $columnsToUpdate = $updateColumns ?? array_diff(array_keys($firstRow), $uniqueColumns);

        if ($columnsToUpdate !== []) {
            $updateParts = [];
            foreach ($columnsToUpdate as $column) {
                $updateParts[] = "{$column} = excluded.{$column}";
            }
            $sql .= ' DO UPDATE SET ' . implode(', ', $updateParts);
        } else {
            $sql .= ' DO NOTHING';
        }

        return $sql;
    }

    /**
     * Build the UPDATE SQL query string.
     *
     * @param array<string, mixed> $data The data to update.
     *
     * @return string The complete UPDATE SQL query.
     */
    protected function buildUpdateQuery(array $data): string
    {
        $setClauses = [];
        foreach (array_keys($data) as $column) {
            $setClauses[] = "{$column} = ?";
        }
        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);
        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        return $sql;
    }

    /**
     * Build the DELETE SQL query string.
     *
     * @return string The complete DELETE SQL query.
     */
    protected function buildDeleteQuery(): string
    {
        $sql = "DELETE FROM {$this->table}";
        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        return $sql;
    }

    /**
     * Build the WHERE clause portion of the SQL query.
     *
     * @return string The WHERE clause string or empty string if no conditions.
     */
    protected function buildWhereClause(): string
    {
        if ($this->conditionOrder === []) {
            return '';
        }

        $sql = '';
        foreach ($this->conditionOrder as $index => $condition) {
            $sqlString = $condition['sql'] ?? '';

            if ($index === 0) {
                $sql .= $sqlString;
            } else {
                $operator = strtoupper($condition['type']);
                $sql .= " {$operator} {$sqlString}";
            }
        }

        return $sql;
    }

    /**
     * Build the HAVING clause portion of the SQL query.
     *
     * @return string The HAVING clause string or empty string if no conditions.
     */
    protected function buildHavingClause(): string
    {
        if ($this->having === []) {
            return '';
        }

        $sql = '';
        foreach ($this->having as $index => $condition) {
            $type = 'AND';
            $sqlString = '';

            if (\is_array($condition)) {
                $type = strtoupper($condition['type']);
                $sqlString = $condition['sql'];
            } else {
                $sqlString = $condition;
            }

            if ($index === 0) {
                $sql .= $sqlString;
            } else {
                $sql .= " {$type} {$sqlString}";
            }
        }

        return $sql;
    }

    /**
     * Build an aggregate query (MAX, MIN, AVG, SUM, COUNT).
     *
     * @param string $function The aggregate function name (MAX, MIN, AVG, SUM, COUNT).
     * @param string $column The column to aggregate.
     *
     * @return string The complete SQL query.
     */
    protected function buildAggregateQuery(string $function, string $column): string
    {
        $sql = "SELECT {$function}({$column}) FROM {$this->table}";

        foreach ($this->joins as $join) {
            if ($join['type'] === 'CROSS') {
                $sql .= " CROSS JOIN {$join['table']}";
            } else {
                $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
            }
        }

        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        if ($this->groupBy !== []) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        $havingSql = $this->buildHavingClause();
        if ($havingSql !== '') {
            $sql .= ' HAVING ' . $havingSql;
        }

        return $sql;
    }

    /**
     * Build the EXISTS SQL query string.
     *
     * @return string The complete EXISTS SQL query.
     */
    protected function buildExistsQuery(): string
    {
        $innerQuery = clone $this;
        $innerQuery->select = ['1'];
        $innerQuery->bindings['select'] = [];

        if ($innerQuery->limit === null && $innerQuery->offset === null) {
            $innerQuery->orderBy = [];
        }

        return 'SELECT EXISTS(' . $innerQuery->buildSelectQuery() . ')';
    }

    /**
     * Build the INCREMENT SQL query string.
     *
     * @param string $column The column to increment.
     * @param int|float $amount The amount to increment by.
     * @param array<string, mixed> $extra Extra columns to update simultaneously.
     *
     * @return string The complete UPDATE SQL query.
     */
    protected function buildIncrementQuery(string $column, int|float $amount = 1, array $extra = []): string
    {
        $setClauses = ["{$column} = {$column} + {$amount}"];

        foreach (array_keys($extra) as $extraColumn) {
            $setClauses[] = "{$extraColumn} = ?";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        return $sql;
    }

    /**
     * Build the DECREMENT SQL query string.
     *
     * @param string $column The column to decrement.
     * @param int|float $amount The amount to decrement by.
     * @param array<string, mixed> $extra Extra columns to update simultaneously.
     *
     * @return string The complete UPDATE SQL query.
     */
    protected function buildDecrementQuery(string $column, int|float $amount = 1, array $extra = []): string
    {
        $setClauses = ["{$column} = {$column} - {$amount}"];

        foreach (array_keys($extra) as $extraColumn) {
            $setClauses[] = "{$extraColumn} = ?";
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setClauses);

        $whereSql = $this->buildWhereClause();
        if ($whereSql !== '') {
            $sql .= ' WHERE ' . $whereSql;
        }

        return $sql;
    }
}
