<?php

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

        if ($this->having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }

        // Apply ORDER BY before pagination for SQL Server compatibility
        if ($this->orderBy !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        // Apply database-specific pagination
        $sql = $this->applyPagination($sql);

        return $sql;
    }

    /**
     * Apply database-specific pagination syntax.
     *
     * @param  string  $sql  The SQL query string.
     * @return string The SQL query with pagination applied.
     */
    protected function applyPagination(string $sql): string
    {
        $driver = $this->getDriver();

        // No pagination needed
        if ($this->limit === null && $this->offset === null) {
            return $sql;
        }

        switch ($driver) {
            case 'sqlsrv':
            case 'mssql':
                return $this->applySqlServerPagination($sql);

            case 'mysql':
            case 'pgsql':
            case 'sqlite':
            default:
                return $this->applyStandardPagination($sql);
        }
    }

    /**
     * Apply SQL Server pagination (OFFSET...FETCH).
     *
     * @param  string  $sql  The SQL query string.
     * @return string The SQL query with SQL Server pagination.
     */
    protected function applySqlServerPagination(string $sql): string
    {
        // SQL Server requires ORDER BY for OFFSET/FETCH
        if ($this->orderBy === [] && ($this->limit !== null || $this->offset !== null)) {
            // Add a default ORDER BY if none exists
            $sql .= ' ORDER BY (SELECT NULL)';
        }

        $offset = $this->offset ?? 0;
        $sql .= " OFFSET {$offset} ROWS";

        if ($this->limit !== null) {
            $sql .= " FETCH NEXT {$this->limit} ROWS ONLY";
        }

        return $sql;
    }

    /**
     * Apply standard pagination (LIMIT...OFFSET).
     *
     * @param  string  $sql  The SQL query string.
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
     * @param  string  $column  The column to count.
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

        if ($this->having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $this->having);
        }

        return $sql;
    }

    /**
     * Build the INSERT SQL query string.
     *
     * @param  array<string, mixed>  $data  The data to insert.
     * @return string The complete INSERT SQL query.
     */
    protected function buildInsertQuery(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        return "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
    }

    /**
     * Build the INSERT BATCH SQL query string.
     *
     * @param  array<array<string, mixed>>  $data  The data array for batch insert.
     * @return string The complete INSERT SQL query.
     *
     * @throws \InvalidArgumentException When data format is invalid.
     */
    protected function buildInsertBatchQuery(array $data): string
    {
        $firstRow = $data[0];
        if (! is_array($firstRow)) {
            throw new \InvalidArgumentException('Invalid data format for batch insert');
        }

        $columns = implode(', ', array_keys($firstRow));
        $placeholders = '(' . implode(', ', array_fill(0, count($firstRow), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($data), $placeholders));

        return "INSERT INTO {$this->table} ({$columns}) VALUES {$allPlaceholders}";
    }

    /**
     * Build the UPSERT SQL query string (INSERT with conflict resolution).
     * Now supports both single and batch upserts.
     *
     * @param  array<string, mixed>|array<array<string, mixed>>  $data  The data to insert/update.
     * @param  string|array<string>  $uniqueColumns  Column(s) that determine uniqueness.
     * @param  array<string>|null  $updateColumns  Columns to update on conflict (null = all except unique).
     * @return string The complete UPSERT SQL query.
     * 
     * @throws \InvalidArgumentException When parameters are invalid.
     */
    protected function buildUpsertQuery(array $data, string|array $uniqueColumns, ?array $updateColumns = null): string
    {
        if ($data === []) {
            throw new \InvalidArgumentException('Data cannot be empty for upsert');
        }

        $isBatch = is_array(reset($data)) && is_array(reset($data));

        if (!$isBatch) {
            $data = [$data];
        }

        /** @var array<array<string, mixed>> $data */
        $uniqueColumns = is_string($uniqueColumns) ? [$uniqueColumns] : $uniqueColumns;

        if ($uniqueColumns === []) {
            throw new \InvalidArgumentException('Unique columns must be specified for upsert');
        }

        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => $this->buildMySqlUpsert($data, $uniqueColumns, $updateColumns),
            'pgsql' => $this->buildPostgreSqlUpsert($data, $uniqueColumns, $updateColumns),
            'sqlite' => $this->buildSqliteUpsert($data, $uniqueColumns, $updateColumns),
            'sqlsrv', 'mssql' => $this->buildSqlServerUpsert($data, $uniqueColumns, $updateColumns),
            default => throw new \InvalidArgumentException("Unsupported driver for upsert: {$driver}"),
        };
    }

    /**
     * Build MySQL upsert query using ON DUPLICATE KEY UPDATE.
     * Now supports batch inserts.
     *
     * @param  array<array<string, mixed>>  $data  The data to insert/update (array of records).
     * @param  array<string>  $uniqueColumns  Column(s) that determine uniqueness.
     * @param  array<string>|null  $updateColumns  Columns to update on conflict.
     * @return string The MySQL upsert query.
     */
    protected function buildMySqlUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
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
     * Now supports batch inserts.
     *
     * @param  array<array<string, mixed>>  $data  The data to insert/update (array of records).
     * @param  array<string>  $uniqueColumns  Column(s) that determine uniqueness.
     * @param  array<string>|null  $updateColumns  Columns to update on conflict.
     * @return string The PostgreSQL upsert query.
     */
    protected function buildPostgreSqlUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
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
     * Now supports batch inserts.
     *
     * @param  array<array<string, mixed>>  $data  The data to insert/update (array of records).
     * @param  array<string>  $uniqueColumns  Column(s) that determine uniqueness.
     * @param  array<string>|null  $updateColumns  Columns to update on conflict.
     * @return string The SQLite upsert query.
     */
    protected function buildSqliteUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = implode(', ', array_keys($firstRow));

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
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
     * Build SQL Server upsert query using MERGE statement.
     * Now supports batch inserts.
     *
     * @param  array<array<string, mixed>>  $data  The data to insert/update (array of records).
     * @param  array<string>  $uniqueColumns  Column(s) that determine uniqueness.
     * @param  array<string>|null  $updateColumns  Columns to update on conflict.
     * @return string The SQL Server upsert query.
     */
    protected function buildSqlServerUpsert(array $data, array $uniqueColumns, ?array $updateColumns): string
    {
        $firstRow = $data[0];
        $columns = array_keys($firstRow);
        $columnsStr = implode(', ', $columns);

        $rowPlaceholders = [];
        foreach ($data as $row) {
            $rowPlaceholders[] = '(' . implode(', ', array_fill(0, count($row), '?')) . ')';
        }
        $allPlaceholders = implode(', ', $rowPlaceholders);

        $matchConditions = [];
        foreach ($uniqueColumns as $column) {
            $matchConditions[] = "target.{$column} = source.{$column}";
        }
        $matchCondition = implode(' AND ', $matchConditions);

        $columnsToUpdate = $updateColumns ?? array_diff($columns, $uniqueColumns);

        $sql = "MERGE INTO {$this->table} AS target ";
        $sql .= "USING (VALUES {$allPlaceholders}) AS source ({$columnsStr}) ";
        $sql .= "ON {$matchCondition} ";

        if ($columnsToUpdate !== []) {
            $updateParts = [];
            foreach ($columnsToUpdate as $column) {
                $updateParts[] = "target.{$column} = source.{$column}";
            }
            $sql .= "WHEN MATCHED THEN UPDATE SET " . implode(', ', $updateParts) . " ";
        }

        $sql .= "WHEN NOT MATCHED THEN INSERT ({$columnsStr}) VALUES (" .
            implode(', ', array_map(fn($col) => "source.{$col}", $columns)) . ");";

        return $sql;
    }

    /**
     * Build the UPDATE SQL query string.
     *
     * @param  array<string, mixed>  $data  The data to update.
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
        $allParts = $this->collectAllConditionParts();

        if ($allParts === []) {
            return '';
        }

        return $this->combineConditionParts($allParts);
    }

    /**
     * Collect all condition parts from different sources.
     *
     * @return array<array{conditions: array<string>, operator: string, priority: int}> All condition parts.
     */
    protected function collectAllConditionParts(): array
    {
        $parts = [];

        $andConditions = array_merge(
            $this->where,
            $this->whereIn,
            $this->whereNotIn,
            $this->whereBetween,
            $this->whereNull,
            $this->whereNotNull,
            $this->whereRaw
        );

        $filteredAnd = array_filter($andConditions, fn($condition) => trim($condition) !== '');
        if ($filteredAnd !== []) {
            $parts[] = ['conditions' => $filteredAnd, 'operator' => 'AND', 'priority' => 1];
        }

        $orConditions = array_merge($this->orWhere, $this->orWhereRaw);
        $filteredOr = array_filter($orConditions, fn($condition) => trim($condition) !== '');
        if ($filteredOr !== []) {
            $parts[] = ['conditions' => $filteredOr, 'operator' => 'OR', 'priority' => 2];
        }

        return $parts;
    }

    /**
     * Build a group of conditions with the same logical operator.
     *
     * @param  array<string>  $conditions  Array of condition strings.
     * @param  string  $operator  The logical operator (AND/OR).
     * @return string The built condition group.
     */
    protected function buildConditionGroup(array $conditions, string $operator): string
    {
        $filteredConditions = array_filter($conditions, fn($condition) => trim($condition) !== '');

        if ($filteredConditions === []) {
            return '';
        }

        // Just join with the operator, no extra parentheses
        return implode(' ' . strtoupper($operator) . ' ', $filteredConditions);
    }

    /**
     * Combine different condition parts with appropriate logic.
     *
     * @param  array<array{conditions: array<string>, operator: string, priority: int}>  $parts  Array of condition parts.
     * @return string The combined condition string.
     */
    protected function combineConditionParts(array $parts): string
    {
        if ($parts === []) {
            return '';
        }

        usort($parts, fn($a, $b) => $a['priority'] <=> $b['priority']);

        $andParts = [];
        $orParts = [];

        foreach ($parts as $part) {
            if ($part['conditions'] === []) {
                continue;
            }

            $conditionString = $this->buildConditionGroup($part['conditions'], $part['operator']);

            if ($conditionString === '') {
                continue;
            }

            if ($part['operator'] === 'AND') {
                $andParts[] = $conditionString;
            } else {
                $orParts[] = $conditionString;
            }
        }

        return $this->combineAndOrParts($andParts, $orParts);
    }

    /**
     * Combine AND and OR parts with proper precedence.
     *
     * @param  array<string>  $andParts  AND condition parts.
     * @param  array<string>  $orParts  OR condition parts.
     * @return string The combined condition string.
     */
    protected function combineAndOrParts(array $andParts, array $orParts): string
    {
        if ($orParts === []) {
            if ($andParts === []) {
                return '';
            }

            return implode(' AND ', $andParts);
        }

        $finalParts = [];

        if ($andParts !== []) {
            $combinedAnd = implode(' AND ', $andParts);

            if (count($andParts) > 1 || str_contains($combinedAnd, ' AND ')) {
                $finalParts[] = '(' . $combinedAnd . ')';
            } else {
                $finalParts[] = $combinedAnd;
            }
        }

        foreach ($orParts as $orPart) {
            $finalParts[] = $orPart;
        }

        return implode(' OR ', $finalParts);
    }

    /**
     * Get all conditions organized by their logical operators.
     *
     * @return array<string, array<string>> Array of conditions grouped by operator type.
     */
    protected function getAllConditions(): array
    {
        return [
            'AND' => array_merge(
                $this->where,
                $this->whereIn,
                $this->whereNotIn,
                $this->whereBetween,
                $this->whereNull,
                $this->whereNotNull,
                $this->whereRaw
            ),
            'OR' => array_merge($this->orWhere, $this->orWhereRaw),
        ];
    }
}
