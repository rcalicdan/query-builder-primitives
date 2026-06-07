<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait QueryJson
{
    /**
     * @var array<array{type: string, column: string, operator: string, value: mixed}> Stored JSON conditions.
     */
    protected array $jsonConditions = [];

    /**
     * Add a JSON path comparison condition to the query.
     * Supports dot/arrow notation: e.g. 'options->preferences->theme'
     *
     * @param string $column The column name with path (e.g., 'options->preferences->theme').
     * @param mixed $operator Comparison operator (or value if only 2 arguments).
     * @param mixed $value The value to compare against.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJson(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): static
    {
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        if (! \is_string($operator)) {
            $operator = '=';
        }

        $instance = clone $this;

        $index = \count($instance->jsonConditions);
        $instance->jsonConditions[] = [
            'type' => 'whereJson',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $instance->conditionOrder[] = [
            'type' => strtolower($boolean),
            'sql' => "json:{$index}", // Compiled dynamically at build time
            'bindings' => [$value],
        ];

        return $instance;
    }

    /**
     * Add an OR JSON path comparison condition to the query.
     *
     * @param string $column The column name with path.
     * @param mixed $operator Comparison operator (or value if only 2 arguments).
     * @param mixed $value The value to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJson(string $column, mixed $operator = null, mixed $value = null): static
    {
        if (\func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->whereJson($column, $operator, $value, 'OR');
    }

    /**
     * Add a condition checking if a JSON array contains a specific value.
     *
     * @param string $column The column name with path (e.g. 'options->languages').
     * @param mixed $value The value to search for.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJsonContains(string $column, mixed $value, string $boolean = 'AND'): static
    {
        $instance = clone $this;

        $index = \count($instance->jsonConditions);
        $instance->jsonConditions[] = [
            'type' => 'whereJsonContains',
            'column' => $column,
            'operator' => '',
            'value' => $value,
        ];

        $instance->conditionOrder[] = [
            'type' => strtolower($boolean),
            'sql' => "json:{$index}",
            'bindings' => [$value],
        ];

        return $instance;
    }

    /**
     * Add an OR condition checking if a JSON array contains a specific value.
     *
     * @param string $column The column name with path.
     * @param mixed $value The value to search for.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJsonContains(string $column, mixed $value): static
    {
        return $this->whereJsonContains($column, $value, 'OR');
    }

    /**
     * Add a condition checking the length of a JSON array.
     *
     * @param string $column The column name with path (e.g. 'options->tags').
     * @param string $operator Comparison operator (e.g., '>', '=').
     * @param int $value The length to compare against.
     * @param string $boolean Logical operator ('AND' or 'OR').
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function whereJsonLength(string $column, string $operator, int $value, string $boolean = 'AND'): static
    {
        $instance = clone $this;

        $index = \count($instance->jsonConditions);
        $instance->jsonConditions[] = [
            'type' => 'whereJsonLength',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        $instance->conditionOrder[] = [
            'type' => strtolower($boolean),
            'sql' => "json:{$index}",
            'bindings' => [$value],
        ];

        return $instance;
    }

    /**
     * Add an OR condition checking the length of a JSON array.
     *
     * @param string $column The column name with path.
     * @param string $operator Comparison operator.
     * @param int $value The length to compare against.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function orWhereJsonLength(string $column, string $operator, int $value): static
    {
        return $this->whereJsonLength($column, $operator, $value, 'OR');
    }

    /**
     * Compile a JSON condition into its database-specific dialect.
     *
     * @param int $index
     *
     * @return array{sql: string, bindings: array<mixed>}
     */
    protected function compileJsonCondition(int $index): array
    {
        $condition = $this->jsonConditions[$index];
        $column = $condition['column'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Normalize both dot and arrow notations: e.g. "options->preferences->theme" or "options->preferences.theme"
        $parts = explode('->', $column);
        $columnName = array_shift($parts);
        $pathString = implode('.', $parts);
        $pathParts = $parts === [] ? [] : explode('.', str_replace('->', '.', $pathString));

        $driver = $this->getDriver();

        return match ($condition['type']) {
            'whereJson' => $this->compileJsonExtract($driver, $columnName, $pathParts, $operator, $value),
            'whereJsonContains' => $this->compileJsonContains($driver, $columnName, $pathParts, $value),
            'whereJsonLength' => $this->compileJsonLength($driver, $columnName, $pathParts, $operator, $value),
            default => ['sql' => '0=1', 'bindings' => []],
        };
    }

    /**
     * @param array<string> $pathParts
     *
     * @return array{sql: string, bindings: array<mixed>}
     */
    private function compileJsonExtract(string $driver, string $column, array $pathParts, string $operator, mixed $value): array
    {
        $path = $pathParts === [] ? '$' : '$.' . implode('.', $pathParts);

        return match ($driver) {
            'pgsql' => [
                'sql' => "{$column}#>>'{" . implode(',', $pathParts) . "}' {$operator} ?",
                'bindings' => [$value],
            ],
            'sqlite' => [
                'sql' => "json_extract({$column}, '{$path}') {$operator} ?",
                'bindings' => [$value],
            ],
            default => [
                'sql' => "JSON_UNQUOTE(JSON_EXTRACT({$column}, '{$path}')) {$operator} ?",
                'bindings' => [$value],
            ],
        };
    }

    /**
     * @param array<string> $pathParts
     *
     * @return array{sql: string, bindings: array<mixed>}
     */
    private function compileJsonContains(string $driver, string $column, array $pathParts, mixed $value): array
    {
        $path = $pathParts === [] ? '$' : '$.' . implode('.', $pathParts);

        return match ($driver) {
            'pgsql' => [
                'sql' => $pathParts === []
                    ? "{$column}::jsonb @> ?::jsonb"
                    : "({$column}->" . implode('->', array_map(fn ($p) => "'{$p}'", $pathParts)) . ')::jsonb @> ?::jsonb',
                'bindings' => [json_encode($value)],
            ],
            'sqlite' => [
                'sql' => "EXISTS (SELECT 1 FROM json_each(json_extract({$column}, '{$path}')) WHERE value = ?)",
                'bindings' => [$value],
            ],
            default => [
                'sql' => "JSON_CONTAINS({$column}, ?, '{$path}')",
                'bindings' => [json_encode($value)],
            ],
        };
    }

    /**
     * @param array<string> $pathParts
     *
     * @return array{sql: string, bindings: array<mixed>}
     */
    private function compileJsonLength(string $driver, string $column, array $pathParts, string $operator, mixed $value): array
    {
        $path = $pathParts === [] ? '$' : '$.' . implode('.', $pathParts);

        return match ($driver) {
            'pgsql' => [
                'sql' => $pathParts === []
                    ? "jsonb_array_length({$column}::jsonb) {$operator} ?"
                    : "jsonb_array_length(({$column}->" . implode('->', array_map(fn ($p) => "'{$p}'", $pathParts)) . ")::jsonb) {$operator} ?",
                'bindings' => [$value],
            ],
            'sqlite' => [
                'sql' => "json_array_length(json_extract({$column}, '{$path}')) {$operator} ?",
                'bindings' => [$value],
            ],
            default => [
                'sql' => "JSON_LENGTH(JSON_EXTRACT({$column}, '{$path}')) {$operator} ?",
                'bindings' => [$value],
            ],
        };
    }
}
