<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives;

trait QueryConditionable
{
    /**
     * Apply the callback if the given "value" is truthy.
     *
     * @param mixed $value The value to check.
     * @param callable(static, mixed): static $callback The callback to execute if the condition is true.
     * @param (callable(static, mixed): static)|null $default The default callback to execute if the condition is false.
     *
     * @return static
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): static
    {
        if (\is_object($value) && \is_callable($value)) {
            $value = $value($this);
        }

        if ((bool) $value) {
            return $callback($this, $value);
        }

        if ($default !== null) {
            return $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply the callback if the given "value" is falsy.
     *
     * @param mixed $value The value to check.
     * @param callable(static, mixed): static $callback The callback to execute if the condition is false.
     * @param (callable(static, mixed): static)|null $default The default callback to execute if the condition is true.
     *
     * @return static
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): static
    {
        if (\is_object($value) && \is_callable($value)) {
            $value = $value($this);
        }

        if (! (bool) $value) {
            return $callback($this, $value);
        }

        if ($default !== null) {
            return $default($this, $value);
        }

        return $this;
    }
}
