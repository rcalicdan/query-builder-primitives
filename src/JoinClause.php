<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

use Rcalicdan\QueryBuilderPrimitives\Interfaces\JoinClauseInterface;

class JoinClause extends QueryBuilderBase implements JoinClauseInterface
{
    public string $type;

    public function __construct(string $table = '', string $type = 'INNER')
    {
        $this->table = $table;
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function on(string $first, ?string $operator = null, ?string $second = null, string $boolean = 'AND'): static
    {
        if (\func_num_args() === 2) {
            return $this->whereColumn($first, '=', $operator, $boolean);
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * {@inheritdoc}
     */
    public function orOn(string $first, ?string $operator = null, ?string $second = null): static
    {
        if (\func_num_args() === 2) {
            return $this->orWhereColumn($first, '=', $operator);
        }

        return $this->orWhereColumn($first, $operator, $second);
    }

    /**
     * Required for QueryAdvancedConditions to spawn nested sub-groups.
     */
    protected function newQuery(): static
    {
        return new static($this->table, $this->type);
    }

    /**
     * @internal
     *
     * Extract the raw condition string safely.
     */
    public function compileConditions(): string
    {
        return $this->buildWhereClause();
    }

    /**
     * @internal
     *
     * Extract the raw bindings.
     *
     * @return array<mixed>
     */
    public function getJoinBindings(): array
    {
        return $this->getCompiledBindings();
    }
}
