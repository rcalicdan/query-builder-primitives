<?php

declare(strict_types=1);

namespace Tests;

use Rcalicdan\QueryBuilderPrimitives\QueryAdvancedConditions;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderCore;
use Rcalicdan\QueryBuilderPrimitives\QueryConditions;
use Rcalicdan\QueryBuilderPrimitives\QueryDebug;
use Rcalicdan\QueryBuilderPrimitives\QueryGrouping;
use Rcalicdan\QueryBuilderPrimitives\QueryJoin;
use Rcalicdan\QueryBuilderPrimitives\QueryLocking;
use Rcalicdan\QueryBuilderPrimitives\SqlBuilder;

class MockQueryBuilder
{
    use QueryAdvancedConditions;
    use QueryBuilderCore;
    use QueryConditions;
    use QueryDebug;
    use QueryGrouping;
    use QueryJoin;
    use QueryLocking;
    use SqlBuilder {
        buildAggregateQuery as protected traitBuildAggregateQuery;
        buildCountQuery as protected traitBuildCountQuery;
        buildSelectQuery as protected traitBuildSelectQuery;
        buildInsertQuery as protected traitBuildInsertQuery;
        buildInsertBatchQuery as protected traitBuildInsertBatchQuery;
        buildUpdateQuery as protected traitBuildUpdateQuery;
        buildDeleteQuery as protected traitBuildDeleteQuery;
        buildUpsertQuery as protected traitBuildUpsertQuery;
    }

    public function __construct(?string $table = null)
    {
        if ($table !== null) {
            $this->table = $table;
        }
    }

    public static function table(string $table): self
    {
        return new self($table);
    }

    public function buildAggregateQuery(string $function, string $column): string
    {
        return $this->traitBuildAggregateQuery($function, $column);
    }

    public function buildCountQuery(string $column = '*'): string
    {
        return $this->traitBuildCountQuery($column);
    }

    public function buildSelectQuery(): string
    {
        return $this->traitBuildSelectQuery();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function buildInsertQuery(array $data): string
    {
        return $this->traitBuildInsertQuery($data);
    }

    /**
     * @param array<array<string, mixed>> $data
     */
    public function buildInsertBatchQuery(array $data): string
    {
        return $this->traitBuildInsertBatchQuery($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function buildUpdateQuery(array $data): string
    {
        return $this->traitBuildUpdateQuery($data);
    }

    public function buildDeleteQuery(): string
    {
        return $this->traitBuildDeleteQuery();
    }

    /**
     * @param array<string, mixed>|array<array<string, mixed>> $data
     * @param string|array<string> $uniqueColumns
     * @param array<string>|null $updateColumns
     */
    public function buildUpsertQuery(array $data, string|array $uniqueColumns, ?array $updateColumns = null): string
    {
        return $this->traitBuildUpsertQuery($data, $uniqueColumns, $updateColumns);
    }
}
