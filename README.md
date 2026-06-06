# Query Builder Primitives

A collection of PHP traits for building immutable, fluent query builders. This library provides low-level primitives without forcing any specific implementation.

## Table of Contents

- [Installation](#installation)
- [Philosophy](#philosophy)
- [Supported Database Drivers](#supported-database-drivers)
- [Architecture Overview](#architecture-overview)
  - [Dependency Map](#dependency-map)
  - [Trait Descriptions](#trait-descriptions)
  - [Interfaces](#interfaces)
- [Quick Start](#quick-start)
  - [Minimal Query Builder](#minimal-query-builder)
  - [Full-Featured Query Builder](#full-featured-query-builder)
- [Trait Details](#trait-details)
  - [QueryBuilderCore](#querybuildercore)
    - [The newQuery() Method](#the-newquery-method--required-override-for-custom-constructors)
  - [QueryConditions](#queryconditions)
  - [QueryAdvancedConditions](#queryadvancedconditions)
  - [QueryConditionable](#queryconditionable)
  - [QueryJoin](#queryjoin)
  - [QueryGrouping](#querygrouping)
  - [QueryLocking](#querylocking)
  - [QueryUnion](#queryunion)
  - [QueryDebug](#querydebug)
  - [SqlBuilder](#sqlbuilder)
    - [buildExistsQuery()](#buildexistsquery)
    - [buildIncrementQuery()](#buildincrementquery)
    - [buildDecrementQuery()](#builddecrementquery)
    - [buildInsertIgnoreQuery()](#buildinsertignorequery)
- [Immutability](#immutability)
- [Extending with Execution](#extending-with-execution)
- [Recommended Compositions](#recommended-compositions)
  - [1. Read-Only Query Builder](#1-read-only-query-builder)
  - [2. Simple Query Builder](#2-simple-query-builder-no-advanced-features)
  - [3. Reporting Query Builder](#3-reporting-query-builder-heavy-on-joingrouping)
  - [4. Full-Featured](#4-full-featured-all-traits)
- [Common Patterns](#common-patterns)
  - [Complex WHERE Logic](#complex-where-logic)
  - [Conditional Query Building](#conditional-query-building)
  - [OR HAVING](#or-having)
  - [Subquery Patterns](#subquery-patterns)
  - [Existence Checks](#existence-checks)
  - [Atomic Counters](#atomic-counters)
  - [Insert Ignore](#insert-ignore)
  - [Pessimistic Locking Patterns](#pessimistic-locking-patterns)
  - [UNION Patterns](#union-patterns)
  - [Reporting Queries](#reporting-queries)
- [Requirements](#requirements)
- [License](#license)
- [Contributing](#contributing)

---

## Installation

```bash
composer require rcalicdan/query-builder-primitives
```

## Philosophy

This library provides **building blocks**, not a complete query builder. You compose the traits you need to create your own custom query builder implementation.

## Supported Database Drivers
*   MySQL/MariaDB
*   PostgreSQL
*   SQLite

---

## Architecture Overview

### Dependency Map

```
QueryBuilderCore (foundation - required)
  ↓
SqlBuilder (depends on: properties from condition/join/grouping traits)
  ↓
QueryConditions (depends on: QueryBuilderCore)
  ↓
QueryAdvancedConditions (depends on: QueryConditions, SqlBuilder)

QueryConditionable (depends on: QueryBuilderCore)
QueryJoin (depends on: QueryBuilderCore)
QueryGrouping (depends on: QueryBuilderCore)
QueryLocking (depends on: QueryBuilderCore, SqlBuilder)
QueryUnion (depends on: QueryBuilderCore, SqlBuilder)
QueryDebug (depends on: all traits)
```

### Trait Descriptions

| Trait | Purpose | Dependencies |
| :--- | :--- | :--- |
| `QueryBuilderCore` | Core properties, select, and `from()` | None (foundation) |
| `SqlBuilder` | Builds SQL query strings | QueryBuilderCore + condition/join/grouping traits |
| `QueryConditions` | Basic WHERE, HAVING, LIKE clauses | QueryBuilderCore |
| `QueryAdvancedConditions` | Nested conditions, EXISTS, subqueries | QueryConditions, SqlBuilder |
| `QueryConditionable` | Conditional `when()` / `unless()` helpers | QueryBuilderCore |
| `QueryJoin` | JOIN operations (INNER, LEFT, RIGHT, CROSS) | QueryBuilderCore |
| `QueryGrouping` | GROUP BY, ORDER BY, LIMIT, OFFSET, random order, reorder | QueryBuilderCore |
| `QueryLocking` | Pessimistic locking (FOR UPDATE, FOR SHARE, NOWAIT, SKIP LOCKED) | QueryBuilderCore, SqlBuilder |
| `QueryUnion` | UNION and UNION ALL operations | QueryBuilderCore, SqlBuilder |
| `QueryDebug` | Debug utilities (toSql, dump, dd) | All traits |

### Interfaces

Each trait has a corresponding contract under `Rcalicdan\QueryBuilderPrimitives\Interfaces\`:

| Interface | Covers |
| :--- | :--- |
| `CoreInterface` | `from`, `select`, `addSelect`, `selectRaw`, `selectDistinct` |
| `ConditionInterface` | All WHERE, HAVING, LIKE methods |
| `AdvancedConditionInterface` | `whereGroup`, `whereExists`, `whereSub`, etc. |
| `ConditionalInterface` | `when`, `unless` |
| `JoinInterface` | All JOIN methods |
| `GroupingInterface` | `groupBy`, `orderBy`, `latest`, `oldest`, `limit`, `offset`, `forPage`, `inRandomOrder`, `reorder` |
| `LockingInterface` | All locking methods |
| `UnionInterface` | `union`, `unionAll` |
| `DebugInterface` | `toSql`, `getBindings`, `toRawSql`, `dump`, `dd` |
| `QueryBuilderPrimitiveInterface` | Extends all of the above |

`QueryBuilderBase` implements `QueryBuilderPrimitiveInterface` and uses all traits, making it a ready-made full implementation you can extend.

---

## Quick Start

### Minimal Query Builder

```php
<?php

namespace App\Database;

use Rcalicdan\QueryBuilderPrimitives\{
    QueryBuilderCore,
    QueryConditions,
    SqlBuilder
};

class QueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
}

// Usage
$qb = new QueryBuilder();
$sql = $qb->from('users')
    ->select('id', 'name', 'email')
    ->where('status', 'active')
    ->where('age', '>=', 18)
    ->toSql();

echo $sql;
// SELECT id, name, email FROM users WHERE status = ? AND age >= ?

$bindings = $qb->getBindings();
// ['active', 18]
```

### Full-Featured Query Builder

```php
<?php

namespace App\Database;

use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

// QueryBuilderBase already composes every trait and implements QueryBuilderPrimitiveInterface.
// Extend it directly, or compose your own from individual traits.
class FullQueryBuilder extends QueryBuilderBase {}

// Usage with advanced features
$qb = new FullQueryBuilder();
$qb->from('users')
    ->select('users.*', 'orders.total')
    ->leftJoin('orders', 'orders.user_id = users.id')
    ->whereGroup(function($query) {
        return $query
            ->where('status', 'active')
            ->orWhere('status', 'pending');
    })
    ->groupBy('users.id')
    ->latest()
    ->limit(10)
    ->dd(); // Debug and die
```

---

## Trait Details

### QueryBuilderCore

Foundation trait providing core properties and select/driver management.

**Properties:**
*   `$table` - Table name
*   `$select` - Select columns
*   `$bindings` - Parameter bindings array

**Public Methods:**
```php
from(string $table): static
select(string ...$columns): static
addSelect(string ...$columns): static
selectRaw(string $expression, array $bindings = []): static
selectDistinct(string ...$columns): static
setDriver(string $driver): static       // 'mysql' | 'pgsql' | 'sqlite'
```

**Protected Methods:**
```php
newQuery(): static          // Returns a fresh instance for subqueries/unions — override when your constructor takes arguments
getDriver(): string
getPlaceholder(): string
getCompiledBindings(): array
```

**Examples:**
```php
// Basic select
$qb->from('users')
    ->select('id', 'name')
    ->addSelect('email')
    ->setDriver('pgsql');

// Select all (default)
$qb->from('users')->select();

// Raw expression in select
$qb->from('orders')
    ->select('user_id')
    ->selectRaw('SUM(total) as total_spent')
    ->selectRaw('COUNT(*) as order_count');

// Parameterised raw expression
$qb->from('products')
    ->selectRaw('CASE WHEN price > ? THEN ? ELSE ? END as tier', [100, 'premium', 'standard']);

// DISTINCT
$qb->from('users')->selectDistinct('country');
```

#### The `newQuery()` Method — Required Override for Custom Constructors

`newQuery()` is the internal factory used by `QueryAdvancedConditions` and `QueryUnion` whenever they need a fresh builder instance for subqueries or unions. The default implementation calls `new static()` with no arguments.

**If your concrete class has required constructor parameters** (e.g., a PDO connection, a service container, or a config object), you **must** override `newQuery()` to pass those dependencies. Without this, subquery methods (`whereExists`, `whereGroup`, `whereSub`, `union`, etc.) will throw a `\LogicException` at runtime.

```php
class ExecutableQueryBuilder extends QueryBuilderBase
{
    public function __construct(private PDO $pdo) {}

    // REQUIRED: pass the PDO dependency to the fresh instance
    protected function newQuery(): static
    {
        return new static($this->pdo);
    }
}
```

If you forget to override `newQuery()` and call a subquery method, you will get:

```
LogicException: Cannot instantiate subquery builder for class "App\Database\ExecutableQueryBuilder".
Because your constructor requires arguments, you must override the protected `newQuery(): static`
method in your class to manually pass your dependencies.
```

---

### QueryConditions

Basic WHERE and HAVING clauses.

**Methods:**
```php
where(string $column, mixed $operator, mixed $value): static
orWhere(string $column, mixed $operator, mixed $value): static
whereIn(string $column, array $values): static
whereNotIn(string $column, array $values): static
whereBetween(string $column, array $values): static
whereNull(string $column): static
whereNotNull(string $column): static
whereColumn(string $first, ?string $operator, ?string $second, string $boolean = 'AND'): static
orWhereColumn(string $first, ?string $operator, ?string $second): static
like(string $column, string $value, string $side = 'both'): static
having(string $column, mixed $operator, mixed $value, string $boolean = 'AND'): static
orHaving(string $column, mixed $operator, mixed $value): static
havingRaw(string $condition, array $bindings = [], string $boolean = 'AND'): static
orHavingRaw(string $condition, array $bindings = []): static
whereRaw(string $condition, array $bindings = [], string $operator = 'AND'): static
orWhereRaw(string $condition, array $bindings = []): static
resetWhere(): static
```

**Examples:**
```php
// Basic WHERE
$qb->where('status', 'active')
   ->where('age', '>=', 18);

// Two-argument shorthand (defaults to '=')
$qb->where('status', 'active');

// WHERE IN / NOT IN
$qb->whereIn('id', [1, 2, 3]);
$qb->whereNotIn('role', ['guest', 'banned']);

// WHERE BETWEEN
$qb->whereBetween('age', [18, 65]);

// NULL checks
$qb->whereNull('deleted_at')
   ->whereNotNull('email');

// Column-to-column comparison (no binding, no injection risk)
$qb->whereColumn('created_at', 'updated_at');          // created_at = updated_at
$qb->whereColumn('price', '>', 'discounted_price');
$qb->orWhereColumn('verified_at', 'created_at');

// LIKE clauses
$qb->like('name', 'John', 'both');          // %John%
$qb->like('email', '@gmail.com', 'before'); // %@gmail.com
$qb->like('username', 'admin', 'after');    // admin%

// Raw WHERE
$qb->whereRaw('DATE(created_at) = CURDATE()');
$qb->whereRaw('age > ? AND status = ?', [18, 'active']);

// OR WHERE
$qb->where('status', 'active')
   ->orWhere('status', 'pending');

// HAVING — AND (default)
$qb->from('orders')
   ->select('user_id')
   ->selectRaw('COUNT(*) as total')
   ->groupBy('user_id')
   ->having('total', '>', 5);

// HAVING — OR
$qb->groupBy('user_id')
   ->having('total_orders', '>', 10)
   ->orHaving('total_spent', '>', 1000);
// HAVING total_orders > ? OR total_spent > ?

// Raw HAVING
$qb->groupBy('department')
   ->havingRaw('SUM(salary) > ?', [50000])
   ->orHavingRaw('COUNT(*) > ?', [20]);
// HAVING SUM(salary) > ? OR COUNT(*) > ?
```

---

### QueryAdvancedConditions

Advanced nested conditions and subqueries.

**Dependencies:** Requires `QueryConditions` and `SqlBuilder`

> **Note:** These methods use `newQuery()` internally. If your builder has constructor arguments, override `newQuery()` — see the [QueryBuilderCore](#querybuildercore) section above.

**Methods:**
```php
whereGroup(callable $callback, string $logicalOperator = 'AND'): static
whereNested(callable $callback, string $operator = 'AND'): static
orWhereNested(callable $callback): static
whereExists(callable $callback, string $operator = 'AND'): static
whereNotExists(callable $callback, string $operator = 'AND'): static
orWhereExists(callable $callback): static
orWhereNotExists(callable $callback): static
whereSub(string $column, string $operator, callable $callback): static
```

**Examples:**
```php
// Nested conditions with grouping
$qb->from('users')
    ->where('role', 'admin')
    ->whereGroup(function($query) {
        return $query
            ->where('status', 'active')
            ->orWhere('status', 'pending');
    });
// WHERE role = ? AND (status = ? OR status = ?)

// OR nested groups
$qb->where('type', 'premium')
    ->orWhereNested(function($query) {
        return $query
            ->where('trial_active', true)
            ->where('trial_ends_at', '>', date('Y-m-d'));
    });

// EXISTS subquery
$qb->from('users')
    ->whereExists(function($query) {
        return $query
            ->from('orders')
            ->whereRaw('orders.user_id = users.id')
            ->where('orders.total', '>', 1000);
    });
// WHERE EXISTS (SELECT * FROM orders WHERE orders.user_id = users.id AND orders.total > ?)

// NOT EXISTS
$qb->from('users')
    ->whereNotExists(function($query) {
        return $query
            ->from('orders')
            ->whereRaw('orders.user_id = users.id');
    });

// Subquery in WHERE
$qb->from('users')
    ->whereSub('total_orders', '>', function($query) {
        return $query
            ->from('orders')
            ->selectRaw('COUNT(*)')
            ->whereRaw('orders.user_id = users.id');
    });
```

---

### QueryConditionable

Conditional query building via `when()` and `unless()`. These let you apply query constraints only when a given condition is truthy or falsy, keeping your query construction logic clean and branch-free.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**
```php
when(mixed $value, callable $callback, ?callable $default = null): static
unless(mixed $value, callable $callback, ?callable $default = null): static
```

The `$value` parameter accepts:
- **Scalars** — strings, integers, booleans, `null`, etc. Evaluated directly as truthy/falsy.
- **Invokable objects** — any object implementing `__invoke`. Called with the builder as argument; its return value becomes the condition.
- **Not supported as conditions** — string callables (`'MyClass::method'`) and array callables (`[$obj, 'method']`) are treated as plain values, not resolved as callables.

The `$callback` and `$default` parameters are standard PHP callables and always receive `($builder, $resolvedValue)` as arguments.

**Examples:**
```php
// Scalar value — truthy/falsy evaluated directly
$status = 'active';

$qb->from('users')
    ->when($status, function($query, $value) {
        return $query->where('status', $value);
    });
// WHERE status = ?  (applied because $status is truthy)

// Null / falsy — condition skipped
$qb->from('users')
    ->when(null, function($query, $value) {
        return $query->where('status', $value); // never runs
    });

// With a default branch (runs when value is falsy)
$role = null;

$qb->from('users')
    ->when($role, function($query, $value) {
        return $query->where('role', $value);
    }, function($query, $value) {
        return $query->where('role', 'guest');
    });
// WHERE role = 'guest'  (default branch ran)

// Invokable class as $value — resolved by calling __invoke($builder)
class HasActiveSubscription
{
    public function __invoke(mixed $builder): bool
    {
        return auth()->user()?->hasActiveSubscription() ?? false;
    }
}

$qb->from('features')
    ->when(new HasActiveSubscription(), function($query, $value) {
        return $query->where('tier', 'premium');
    });
// WHERE tier = ?  (only if HasActiveSubscription returned true)

// Closure as $value — also an invokable object, resolved the same way
$qb->from('orders')
    ->when(
        fn($query) => auth()->user()->isAdmin(),
        fn($query, $value) => $query->where('user_id', auth()->id())
    );

// String and array callables are NOT resolved — treated as plain values
// Both of these will always apply the callback (non-empty string/array is truthy)
$qb->from('users')
    ->when('SomeClass::method', fn($q, $v) => $q->where('flag', true));  // always runs
$qb->from('users')
    ->when([$obj, 'method'],   fn($q, $v) => $q->where('flag', true));  // always runs

// unless() — mirror of when(), applies callback when value is falsy
$isAdmin = false;

$qb->from('posts')
    ->unless($isAdmin, function($query, $value) {
        return $query->where('published', true);
    });
// WHERE published = ?  (applied because $isAdmin is falsy)

// Invokable class with unless()
class IsGuestUser
{
    public function __invoke(mixed $builder): bool
    {
        return auth()->guest();
    }
}

$qb->from('posts')
    ->unless(new IsGuestUser(), function($query, $value) {
        return $query->where('draft', false);
    });
// WHERE draft = ?  (only if user is NOT a guest)

// Chaining multiple conditionals cleanly
$search    = 'john';
$sortField = 'created_at';
$isAdmin   = false;

$qb->from('users')
    ->when($search,    fn($q, $v) => $q->like('name', $v))
    ->when($sortField, fn($q, $v) => $q->orderBy($v, 'DESC'))
    ->unless($isAdmin, fn($q, $v) => $q->where('active', true));
// WHERE name LIKE ? AND active = ? ORDER BY created_at DESC
```

---

### QueryJoin

JOIN operations.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**
```php
join(string $table, string $condition, string $type = 'INNER'): static
leftJoin(string $table, string $condition): static
rightJoin(string $table, string $condition): static
innerJoin(string $table, string $condition): static
crossJoin(string $table): static
```

**Examples:**
```php
// INNER JOIN
$qb->from('users')
    ->innerJoin('profiles', 'profiles.user_id = users.id');

// LEFT JOIN
$qb->from('users')
    ->leftJoin('orders', 'orders.user_id = users.id');

// Multiple joins
$qb->from('users')
    ->leftJoin('profiles', 'profiles.user_id = users.id')
    ->leftJoin('orders', 'orders.user_id = users.id')
    ->leftJoin('payments', 'payments.order_id = orders.id');

// CROSS JOIN
$qb->from('colors')
    ->crossJoin('sizes');
```

---

### QueryGrouping

Grouping, ordering, pagination, random ordering, and reordering.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**
```php
groupBy(string|array $columns): static
orderBy(string $column, string $direction = 'ASC'): static
orderByAsc(string $column): static
orderByDesc(string $column): static
latest(string $column = 'created_at'): static
oldest(string $column = 'created_at'): static
inRandomOrder(): static
reorder(?string $column = null, string $direction = 'ASC'): static
limit(int $limit, ?int $offset = null): static
offset(int $offset): static
forPage(int $page, int $perPage = 15): static
```

**Examples:**
```php
// GROUP BY
$qb->select('user_id')
    ->selectRaw('COUNT(*) as total')
    ->groupBy('user_id');

// Multiple GROUP BY
$qb->groupBy(['user_id', 'status']);

// Standard ORDER BY
$qb->orderBy('created_at', 'DESC')
    ->orderBy('name', 'ASC');

// Shorthand direction methods
$qb->orderByDesc('created_at')
    ->orderByAsc('name');

// latest() / oldest() — aliases defaulting to 'created_at'
$qb->from('posts')->latest();
// ORDER BY created_at DESC

$qb->from('posts')->oldest();
// ORDER BY created_at ASC

$qb->from('posts')->latest('published_at');
// ORDER BY published_at DESC

// Random order — adapts syntax per driver
$qb->from('products')->inRandomOrder();
// MySQL:           ORDER BY RAND()
// PgSQL / SQLite:  ORDER BY RANDOM()

// reorder() — clear existing ORDER BY and optionally set a new one
$base    = $qb->from('users')->orderByDesc('created_at');
$fresh   = $base->reorder();                        // clears all ORDER BY
$renewed = $base->reorder('name', 'ASC');           // clears then sets ORDER BY name ASC

// LIMIT and OFFSET
$qb->limit(10)->offset(20);
$qb->limit(10, 20);    // combined shorthand — LIMIT 10 OFFSET 20

// Pagination helper
$qb->forPage(2, 25);   // Page 2, 25 per page = LIMIT 25 OFFSET 25
```

---

### QueryLocking

Pessimistic locking for concurrency control within database transactions.

**Dependencies:** Requires `QueryBuilderCore` and `SqlBuilder`

> **Important:** Lock clauses are only meaningful inside a database transaction. Always wrap locking queries in `BEGIN` / `COMMIT`.

**Methods:**
```php
lockForUpdate(): static
lockForShare(): static
noWait(): static
skipLocked(): static
lockOf(string|array $tables): static    // PostgreSQL only
withoutLock(): static
```

#### Driver support matrix

| Feature | MySQL | PostgreSQL | SQLite |
| :--- | :---: | :---: | :---: |
| `lockForUpdate()` | ✅ `FOR UPDATE` | ✅ `FOR UPDATE` | ❌ ignored |
| `lockForShare()` | ✅ `LOCK IN SHARE MODE` | ✅ `FOR SHARE` | ❌ ignored |
| `noWait()` on `FOR UPDATE` | ✅ | ✅ | ❌ ignored |
| `noWait()` on `FOR SHARE` | ❌ silently ignored | ✅ | ❌ ignored |
| `skipLocked()` on `FOR UPDATE` | ✅ | ✅ | ❌ ignored |
| `skipLocked()` on `FOR SHARE` | ❌ silently ignored | ✅ | ❌ ignored |
| `lockOf()` | ❌ throws | ✅ | ❌ ignored |

> **SQLite note:** SQLite has no row-level locking. Use `BEGIN EXCLUSIVE` or `BEGIN IMMEDIATE` at the connection level instead.

**Examples:**
```php
// Exclusive lock
$qb->from('orders')
    ->where('id', 1)
    ->lockForUpdate()
    ->toSql();
// MySQL/PgSQL: SELECT * FROM orders WHERE id = ? FOR UPDATE

// Shared lock
$qb->from('inventory')
    ->where('product_id', 42)
    ->lockForShare()
    ->toSql();
// MySQL: SELECT * FROM inventory WHERE product_id = ? LOCK IN SHARE MODE
// PgSQL: SELECT * FROM inventory WHERE product_id = ? FOR SHARE

// Fail immediately if rows are locked
$qb->from('orders')
    ->where('status', 'pending')
    ->lockForUpdate()
    ->noWait()
    ->toSql();
// SELECT * FROM orders WHERE status = ? FOR UPDATE NOWAIT

// Queue worker pattern — skip rows locked by other workers
$qb->from('jobs')
    ->where('status', 'pending')
    ->orderBy('created_at')
    ->limit(1)
    ->lockForUpdate()
    ->skipLocked()
    ->toSql();
// SELECT * FROM jobs WHERE status = ? ORDER BY created_at ASC LIMIT 1 FOR UPDATE SKIP LOCKED

// PostgreSQL OF clause
$qb->from('orders')
    ->setDriver('pgsql')
    ->join('users', 'orders.user_id = users.id')
    ->lockForUpdate()
    ->lockOf('orders')
    ->toSql();
// SELECT * FROM orders INNER JOIN users ON orders.user_id = users.id FOR UPDATE OF orders

// Remove lock from a reused base query
$base     = $qb->from('orders')->lockForUpdate();
$unlocked = $base->withoutLock();
```

#### Clause ordering

```
SELECT ... FROM ... JOIN ... WHERE ... GROUP BY ... HAVING ... ORDER BY ... LIMIT ... OFFSET ... <LOCK>
```

---

### QueryUnion

UNION and UNION ALL operations.

**Dependencies:** Requires `QueryBuilderCore` and `SqlBuilder`

> **Note:** This trait uses `newQuery()` internally. If your builder has constructor arguments, override `newQuery()` — see the [QueryBuilderCore](#querybuildercore) section above.

**Methods:**
```php
union(callable $callback, bool $all = false): static
unionAll(callable $callback): static
```

**Examples:**
```php
// Basic UNION (deduplicates rows)
$qb->from('active_users')
    ->select('id', 'name', 'email')
    ->union(function($query) {
        return $query
            ->from('archived_users')
            ->select('id', 'name', 'email');
    })
    ->toSql();
// SELECT id, name, email FROM active_users
// UNION SELECT id, name, email FROM archived_users

// UNION ALL (keeps duplicates)
$qb->from('orders_2023')
    ->select('id', 'total', 'created_at')
    ->unionAll(function($query) {
        return $query
            ->from('orders_2024')
            ->select('id', 'total', 'created_at');
    })
    ->latest('created_at')
    ->toSql();
// SELECT id, total, created_at FROM orders_2023
// UNION ALL SELECT id, total, created_at FROM orders_2024
// ORDER BY created_at DESC

// Chaining multiple UNIONs
$qb->from('employees')
    ->select('id', 'name', 'department')
    ->where('active', true)
    ->union(function($query) {
        return $query->from('contractors')
                     ->select('id', 'name', 'department')
                     ->where('active', true);
    })
    ->union(function($query) {
        return $query->from('interns')
                     ->select('id', 'name', 'department');
    })
    ->orderBy('name');

// Bindings are correctly propagated across all union branches
$qb->from('products')
    ->select('id', 'name', 'price')
    ->where('category', 'electronics')
    ->unionAll(function($query) {
        return $query->from('products')
                     ->select('id', 'name', 'price')
                     ->where('category', 'accessories')
                     ->where('price', '<', 50);
    });

$bindings = $qb->getBindings();
// ['electronics', 'accessories', 50]
```

> `ORDER BY`, `LIMIT`, and `OFFSET` placed on the outer query apply to the full union result set. Column counts and types must match across all unioned queries.

---

### QueryDebug

Debugging utilities.

**Dependencies:** Requires all other traits

**Methods:**
```php
toSql(): string
getBindings(): array
toRawSql(): string
dump(): static
dd(): never
```

**Examples:**
```php
// Get SQL query
$sql = $qb->from('users')
    ->where('status', 'active')
    ->toSql();
echo $sql; // SELECT * FROM users WHERE status = ?

// Get bindings
$bindings = $qb->getBindings();
var_dump($bindings); // ['active']

// Get interpolated SQL (DEBUG ONLY — never use for execution!)
$rawSql = $qb->toRawSql();
echo $rawSql; // SELECT * FROM users WHERE status = 'active'

// Dump and continue
$qb->from('users')
    ->where('status', 'active')
    ->dump()
    ->where('age', '>=', 18)
    ->dump();

// Dump and die (stops execution)
$qb->from('users')
    ->where('status', 'active')
    ->dd();
```

---

### SqlBuilder

Builds SQL query strings from accumulated state.

**Dependencies:** Requires `QueryBuilderCore` and properties from condition/join/grouping traits

**Protected Methods** (used internally or for extension):
```php
buildSelectQuery(): string
buildCountQuery(string $column = '*'): string
buildInsertQuery(array $data): string
buildInsertBatchQuery(array $data): string
buildInsertIgnoreQuery(array $data): string
buildUpdateQuery(array $data): string
buildDeleteQuery(): string
buildWhereClause(): string
buildHavingClause(): string
buildAggregateQuery(string $function, string $column): string
buildUpsertQuery(array $data, string|array $uniqueColumns, ?array $updateColumns = null): string
buildExistsQuery(): string
buildIncrementQuery(string $column, int|float $amount = 1, array $extra = []): string
buildDecrementQuery(string $column, int|float $amount = 1, array $extra = []): string
```

> `buildHavingClause()` handles both `AND` and `OR` conditions, driven by the `$boolean` parameter on `having()` and `havingRaw()`.

#### `buildExistsQuery()`

Wraps the current query in `SELECT EXISTS(...)`. Internally resets the select to `1` and strips `ORDER BY` (unless `LIMIT`/`OFFSET` is set) to keep the subquery lean.

```php
// In your concrete builder:
public function exists(): bool
{
    $stmt = $this->pdo->prepare($this->buildExistsQuery());
    $stmt->execute($this->getCompiledBindings());
    return (bool) $stmt->fetchColumn();
}

// Usage
$exists = $qb->from('users')
    ->where('email', 'john@example.com')
    ->exists();
// SELECT EXISTS(SELECT 1 FROM users WHERE email = ?)

$exists = $qb->from('orders')
    ->where('user_id', 42)
    ->where('status', 'pending')
    ->exists();
// SELECT EXISTS(SELECT 1 FROM orders WHERE user_id = ? AND status = ?)
```

> `ORDER BY` is silently stripped when no `LIMIT`/`OFFSET` is present, since ordering has no effect on existence checks.

#### `buildIncrementQuery()`

Builds an `UPDATE` that adds `$amount` to a column atomically. The optional `$extra` array lets you update additional columns in the same statement — their values bind to the `SET` placeholders, which appear **before** the `WHERE` bindings in the final `execute()` call.

```php
// In your concrete builder:
public function increment(string $column, int|float $amount = 1, array $extra = []): int
{
    $stmt = $this->pdo->prepare($this->buildIncrementQuery($column, $amount, $extra));
    $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
    return $stmt->rowCount();
}

// Usage
$qb->from('products')->where('id', 5)->increment('stock');
// UPDATE products SET stock = stock + 1 WHERE id = ?

$qb->from('products')->where('id', 5)->increment('stock', 10);
// UPDATE products SET stock = stock + 10 WHERE id = ?

$qb->from('products')->where('id', 5)->increment('stock', 3, ['updated_at' => now()]);
// UPDATE products SET stock = stock + 3, updated_at = ? WHERE id = ?
```

#### `buildDecrementQuery()`

Mirror of `buildIncrementQuery()` — subtracts `$amount` from the column instead. Binding order is the same: `$extra` values first, then WHERE bindings.

```php
// In your concrete builder:
public function decrement(string $column, int|float $amount = 1, array $extra = []): int
{
    $stmt = $this->pdo->prepare($this->buildDecrementQuery($column, $amount, $extra));
    $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
    return $stmt->rowCount();
}

// Usage
$qb->from('products')->where('id', 5)->decrement('stock');
// UPDATE products SET stock = stock - 1 WHERE id = ?

$qb->from('accounts')->where('user_id', 12)->decrement('balance', 50.00, ['last_withdrawal' => now()]);
// UPDATE accounts SET balance = balance - 50, last_withdrawal = ? WHERE user_id = ?
```

#### `buildInsertIgnoreQuery()`

Builds a driver-aware insert that silently skips rows that would violate a unique constraint. Supports both single-row and batch inserts.

| Driver | Syntax used |
| :--- | :--- |
| MySQL | `INSERT IGNORE INTO ...` |
| PostgreSQL | `INSERT INTO ... ON CONFLICT DO NOTHING` |
| SQLite | `INSERT OR IGNORE INTO ...` |

```php
// In your concrete builder:
public function insertIgnore(array $data): bool
{
    $stmt = $this->pdo->prepare($this->buildInsertIgnoreQuery($data));

    // Single row
    if (! is_array(reset($data))) {
        return $stmt->execute(array_values($data));
    }

    // Batch
    $values = array_merge(...array_map('array_values', $data));
    return $stmt->execute($values);
}

// Single-row usage
$qb->from('tags')->insertIgnore(['name' => 'php', 'slug' => 'php']);
// MySQL:  INSERT IGNORE INTO tags (name, slug) VALUES (?, ?)
// PgSQL:  INSERT INTO tags (name, slug) VALUES (?, ?) ON CONFLICT DO NOTHING
// SQLite: INSERT OR IGNORE INTO tags (name, slug) VALUES (?, ?)

// Batch usage
$qb->from('tags')->insertIgnore([
    ['name' => 'php',        'slug' => 'php'],
    ['name' => 'javascript', 'slug' => 'javascript'],
]);
// Inserts all rows; duplicate-key rows are silently skipped
```

---

## Immutability

All methods return a **new instance** of the query builder, ensuring immutability:

```php
$base = $qb->from('users')->where('status', 'active');

$query1 = $base->where('age', '>=', 18);
$query2 = $base->where('country', 'US');

// $base remains unchanged; $query1 and $query2 are independent

// Same applies to locks, unions, and ordering
$plain    = $qb->from('orders')->where('status', 'pending');
$locked   = $plain->lockForUpdate();
$union    = $plain->union(fn($q) => $q->from('archived_orders'));
$sorted   = $plain->latest();

// $plain has no lock, no union, and no ORDER BY; each fork is independent
```

---

## Extending with Execution

```php
<?php

namespace App\Database;

use PDO;
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

class ExecutableQueryBuilder extends QueryBuilderBase
{
    public function __construct(private PDO $pdo) {}

    /**
     * REQUIRED when your constructor takes arguments.
     * Called internally by whereExists(), whereGroup(), union(), etc.
     */
    protected function newQuery(): static
    {
        return new static($this->pdo);
    }

    public function get(): array
    {
        $stmt = $this->pdo->prepare($this->buildSelectQuery());
        $stmt->execute($this->getCompiledBindings());
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): ?array
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    public function count(string $column = '*'): int
    {
        $stmt = $this->pdo->prepare($this->buildCountQuery($column));
        $stmt->execute($this->getCompiledBindings());
        return (int) $stmt->fetchColumn();
    }

    public function exists(): bool
    {
        $stmt = $this->pdo->prepare($this->buildExistsQuery());
        $stmt->execute($this->getCompiledBindings());
        return (bool) $stmt->fetchColumn();
    }

    public function insert(array $data): bool
    {
        $stmt = $this->pdo->prepare($this->buildInsertQuery($data));
        return $stmt->execute(array_values($data));
    }

    public function insertBatch(array $data): bool
    {
        $stmt = $this->pdo->prepare($this->buildInsertBatchQuery($data));
        $values = array_merge(...array_map('array_values', $data));
        return $stmt->execute($values);
    }

    public function insertIgnore(array $data): bool
    {
        $stmt = $this->pdo->prepare($this->buildInsertIgnoreQuery($data));
        $isBatch = is_array(reset($data));
        $values  = $isBatch
            ? array_merge(...array_map('array_values', $data))
            : array_values($data);
        return $stmt->execute($values);
    }

    public function update(array $data): int
    {
        $stmt = $this->pdo->prepare($this->buildUpdateQuery($data));
        $stmt->execute([...array_values($data), ...$this->getCompiledBindings()]);
        return $stmt->rowCount();
    }

    public function delete(): int
    {
        $stmt = $this->pdo->prepare($this->buildDeleteQuery());
        $stmt->execute($this->getCompiledBindings());
        return $stmt->rowCount();
    }

    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $stmt = $this->pdo->prepare($this->buildIncrementQuery($column, $amount, $extra));
        $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
        return $stmt->rowCount();
    }

    public function decrement(string $column, int|float $amount = 1, array $extra = []): int
    {
        $stmt = $this->pdo->prepare($this->buildDecrementQuery($column, $amount, $extra));
        $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
        return $stmt->rowCount();
    }
}

// Usage
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$qb  = new ExecutableQueryBuilder($pdo);

$users = $qb->from('users')
    ->where('status', 'active')
    ->latest()
    ->limit(10)
    ->get();

// Conditional filtering
$search = 'john';
$users  = $qb->from('users')
    ->when($search, fn($q, $v) => $q->like('name', $v))
    ->oldest()
    ->get();

// Random sample
$featured = $qb->from('products')
    ->where('featured', true)
    ->inRandomOrder()
    ->limit(5)
    ->get();

// EXISTS check
$hasOrders = $qb->from('orders')->where('user_id', 42)->exists();

// Insert ignore
$qb->from('tags')->insertIgnore(['name' => 'php', 'slug' => 'php']);

// Increment / decrement
$qb->from('products')->where('id', 5)->increment('stock', 10);
$qb->from('accounts')->where('user_id', 12)->decrement('balance', 50.00);

// Locking inside a transaction
$pdo->beginTransaction();
$job = $qb->from('jobs')
    ->where('status', 'pending')
    ->oldest()
    ->limit(1)
    ->lockForUpdate()
    ->skipLocked()
    ->first();
// process $job ...
$pdo->commit();
```

---

## Recommended Compositions

### 1. Read-Only Query Builder

```php
class ReadOnlyQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryConditionable;
    use QueryJoin;
    use QueryGrouping;
    use QueryUnion;
    use QueryDebug;
}
```

### 2. Simple Query Builder (No Advanced Features)

```php
class SimpleQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryConditionable;
    use QueryGrouping;
}
```

### 3. Reporting Query Builder (Heavy on Join/Grouping)

```php
class ReportingQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryConditionable;
    use QueryJoin;
    use QueryGrouping;
    use QueryUnion;
    use QueryDebug;
}
```

### 4. Full-Featured (All Traits)

```php
// Use QueryBuilderBase directly — it already composes everything.
use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

class MyQueryBuilder extends QueryBuilderBase
{
    public function __construct(private PDO $pdo) {}

    protected function newQuery(): static
    {
        return new static($this->pdo);
    }
}
```

---

## Common Patterns

### Complex WHERE Logic

```php
// (status = 'active' AND role = 'admin') OR (status = 'pending' AND invited = true)
$qb->from('users')
    ->whereGroup(function($q) {
        return $q->where('status', 'active')
                 ->where('role', 'admin');
    })
    ->whereGroup(function($q) {
        return $q->where('status', 'pending')
                 ->where('invited', true);
    });
```

### Conditional Query Building

```php
// Apply filters only when present — no if/else branches needed
$qb->from('products')
    ->when($categoryId, fn($q, $v) => $q->where('category_id', $v))
    ->when($maxPrice,   fn($q, $v) => $q->where('price', '<=', $v))
    ->when($inStock,    fn($q, $v) => $q->where('stock', '>', 0))
    ->unless($showAll,  fn($q, $v) => $q->where('active', true))
    ->latest('updated_at')
    ->get();

// Dynamic sort with fallback
$qb->from('posts')
    ->when(
        $sortColumn,
        fn($q, $v) => $q->orderBy($v, $sortDirection ?? 'ASC'),
        fn($q, $v) => $q->latest()   // default sort
    );
```

### OR HAVING

```php
$qb->from('orders')
    ->select('user_id')
    ->selectRaw('COUNT(*) as order_count')
    ->selectRaw('SUM(total) as total_spent')
    ->groupBy('user_id')
    ->having('order_count', '>', 10)
    ->orHaving('total_spent', '>', 5000);
// HAVING order_count > ? OR total_spent > ?

$qb->from('stats')
    ->groupBy('team_id')
    ->havingRaw('SUM(points) > ?', [100])
    ->orHavingRaw('COUNT(wins) >= ?', [20]);
```

### Subquery Patterns

```php
// Users who have placed orders over $1000
$qb->from('users')
    ->whereExists(function($q) {
        return $q->from('orders')
                 ->whereRaw('orders.user_id = users.id')
                 ->where('total', '>', 1000);
    });

// Column-to-column comparison
$qb->from('audit_log')
    ->whereColumn('expected_hash', 'actual_hash')
    ->orWhereColumn('verified_at', '>', 'created_at');
```

### Existence Checks

```php
// Simple record check — no need to fetch data
if ($qb->from('users')->where('email', $email)->exists()) {
    throw new \RuntimeException('Email already registered.');
}

// Check with joins
$isEnrolled = $qb->from('enrollments')
    ->where('user_id', $userId)
    ->where('course_id', $courseId)
    ->whereNull('cancelled_at')
    ->exists();
```

### Atomic Counters

```php
// Increment page views
$qb->from('posts')->where('id', $postId)->increment('views');

// Decrement remaining seats and record the timestamp
$qb->from('events')
    ->where('id', $eventId)
    ->decrement('seats_remaining', 1, ['last_booking_at' => now()]);

// Batch-safe stock adjustment
$qb->from('products')
    ->where('sku', $sku)
    ->where('stock', '>', 0)
    ->decrement('stock', $quantity);
```

### Insert Ignore

```php
// Safely insert without failing on duplicate keys
$qb->from('tags')->insertIgnore(['name' => 'php', 'slug' => 'php']);

// Batch variant — duplicates are skipped, rest are inserted
$qb->from('user_roles')->insertIgnore([
    ['user_id' => 1, 'role' => 'editor'],
    ['user_id' => 2, 'role' => 'editor'],
    ['user_id' => 1, 'role' => 'editor'], // duplicate — silently skipped
]);
```

### Pessimistic Locking Patterns

```php
// Payment processing
$pdo->beginTransaction();
$order = $qb->from('orders')
    ->where('id', $orderId)
    ->where('status', 'pending')
    ->lockForUpdate()
    ->first();
$pdo->commit();

// Job queue with SKIP LOCKED
$pdo->beginTransaction();
$job = $qb->from('jobs')
    ->where('status', 'available')
    ->orderByDesc('priority')
    ->oldest()
    ->limit(1)
    ->lockForUpdate()
    ->skipLocked()
    ->first();
$pdo->commit();
```

### UNION Patterns

```php
// Combine partitioned tables
$qb->from('logs_2024')
    ->select('id', 'user_id', 'action', 'created_at')
    ->unionAll(function($q) {
        return $q->from('logs_2025')
                 ->select('id', 'user_id', 'action', 'created_at');
    })
    ->latest('created_at')
    ->limit(100);

// Merge different record types into a single feed
$qb->from('posts')
    ->select('id', 'title', 'created_at')
    ->selectRaw("'post' as type")
    ->union(function($q) {
        return $q->from('comments')
                 ->select('id', 'body as title', 'created_at')
                 ->selectRaw("'comment' as type");
    })
    ->latest('created_at');
```

### Reporting Queries

```php
$qb->from('orders')
    ->select('users.name')
    ->selectRaw('COUNT(orders.id) as total_orders')
    ->selectRaw('SUM(orders.total) as total_spent')
    ->selectRaw('AVG(orders.total) as avg_order')
    ->leftJoin('users', 'users.id = orders.user_id')
    ->where('orders.status', 'completed')
    ->whereBetween('orders.created_at', ['2024-01-01', '2024-12-31'])
    ->groupBy('users.id')
    ->having('total_orders', '>', 5)
    ->orHaving('total_spent', '>', 10000)
    ->latest('total_spent')
    ->limit(100);
```

---

## Requirements

*   PHP 8.2 or higher

## License

MIT

## Contributing

This is a primitive library — keep it simple and focused on building blocks, not opinions.