<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

/**
 * A comprehensive interface representing all query builder primitives.
 * This represents the "state" and "logic" of a query, but does not
 * include execution methods (get, first, insert, etc.).
 */
interface QueryBuilderPrimitiveInterface extends
    CoreInterface,
    ConditionalInterface,
    ConditionInterface,
    JsonConditionInterface,
    AdvancedConditionInterface,
    JoinInterface,
    GroupingInterface,
    LockingInterface,
    UnionInterface,
    CteInterface,
    DebugInterface
{
}
