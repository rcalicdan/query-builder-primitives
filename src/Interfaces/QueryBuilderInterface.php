<?php

declare(strict_types=1);

namespace Rcalicdan\QueryBuilderPrimitives\Interfaces;

/**
 * A comprehensive interface representing all query builder primitives.
 */
interface QueryBuilderInterface extends
    CoreInterface,
    ConditionInterface,
    AdvancedConditionInterface,
    JoinInterface,
    GroupingInterface,
    LockingInterface,
    UnionInterface,
    DebugInterface
{
}
