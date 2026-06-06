<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

interface ConditionalInterface
{
    /**
     * Apply the callback if the given "value" is truthy.
     *
     * @param mixed $value The condition to evaluate (or a Closure that resolves to one).
     * @param callable(static, mixed): static $callback The callback to run if true.
     * @param (callable(static, mixed): static)|null $default The callback to run if false.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static;

    /**
     * Apply the callback if the given "value" is falsy.
     *
     * @param mixed $value The condition to evaluate (or a Closure that resolves to one).
     * @param callable(static, mixed): static $callback The callback to run if false.
     * @param (callable(static, mixed): static)|null $default The callback to run if true.
     *
     * @return static Returns a new query builder instance for method chaining.
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static;
}
