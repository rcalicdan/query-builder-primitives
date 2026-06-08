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
  - [QueryCte](#querycte)
  - [QueryJson](#queryjson)
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
  - [CTE Patterns](#cte-patterns)
  - [JSON Patterns](#json-patterns)
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

Queries are built through **immutable method chaining** — every method returns a new instance rather than mutating the current one. This is a deliberate design choice with real practical benefits:

- **Safe reuse.** A base query can be forked into multiple independent queries without any of them affecting each other. Build a `$base` once, branch it as many times as you need.
- **No hidden state.** In a mutable builder, calling methods on a shared instance from different parts of your code produces unpredictable results. With immutable chaining, each chain is self-contained and its SQL is exactly what you wrote.
- **Composable defaults.** You can define a pre-configured query (scoped to a tenant, filtered by status, ordered by default) and pass it around freely, knowing no callee can corrupt it.
- **Easier debugging.** Because each step produces a discrete value, you can call `.halt()` or `.toSql()` at any point in the chain without affecting the final query.
- **Safe for asynchronous execution.** When multiple coroutines or fibers execute concurrently and share a mutable builder, one coroutine's `where()` call can bleed into another's query mid-flight — a race condition that is silent, non-deterministic, and extremely difficult to reproduce. Because every method on this builder returns a new independent instance, each coroutine or fiber holds its own copy of the query state from the moment it branches off. There is no shared mutable object to race on, so concurrent query construction is safe by construction rather than by discipline.

```php
// Mutable builders share state — this is the problem immutability solves:
$base->where('status', 'active');   // mutates $base
$base->where('role', 'admin');      // mutates $base again — now both conditions are on it

// Immutable chaining — each call returns a new instance, $base is never touched:
$active = $base->where('status', 'active');
$admins = $base->where('role', 'admin');

// $base, $active, and $admins are three completely independent queries

// Safe concurrent use — each fiber/coroutine gets its own query state:
$base = $qb->from('orders')->where('status', 'pending');

$fiber1 = new Fiber(function() use ($base) {
    // Branches off $base into a new instance — completely isolated
    $query = $base->where('user_id', 1)->latest();
    // ... execute $query
});

$fiber2 = new Fiber(function() use ($base) {
    // Also branches off $base — no interference with fiber1
    $query = $base->where('user_id', 2)->oldest();
    // ... execute $query
});

// Both fibers work from the same $base without any risk of one
// overwriting the other's conditions, regardless of execution order
```

The trade-off is that you must always assign or chain the return value — discarding it silently does nothing. This is intentional: the builder never surprises you with side effects.

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
SqlBuilder (depends on: properties from condition/join/grouping/cte traits)
  ↓
QueryConditions (depends on: QueryBuilderCore)
  ↓
QueryAdvancedConditions (depends on: QueryConditions, SqlBuilder)

QueryConditionable (depends on: QueryBuilderCore)
QueryJoin (depends on: QueryBuilderCore, JoinClause)
QueryGrouping (depends on: QueryBuilderCore)
QueryLocking (depends on: QueryBuilderCore, SqlBuilder)
QueryUnion (depends on: QueryBuilderCore, SqlBuilder)
QueryCte (depends on: QueryBuilderCore, SqlBuilder)
QueryJson (depends on: QueryBuilderCore, QueryConditions)
QueryDebug (depends on: all traits)
```

### Trait Descriptions

| Trait | Purpose | Dependencies |
| :--- | :--- | :--- |
| `QueryBuilderCore` | Core properties, select, and `from()` | None (foundation) |
| `SqlBuilder` | Builds SQL query strings | QueryBuilderCore + condition/join/grouping/cte traits |
| `QueryConditions` | Basic WHERE, HAVING, LIKE clauses | QueryBuilderCore |
| `QueryAdvancedConditions` | Nested conditions, EXISTS, subqueries | QueryConditions, SqlBuilder |
| `QueryConditionable` | Conditional `when()` / `unless()` helpers | QueryBuilderCore |
| `QueryJoin` | JOIN operations (INNER, LEFT, RIGHT, CROSS) with simple string or advanced closure conditions | QueryBuilderCore, JoinClause |
| `QueryGrouping` | GROUP BY, ORDER BY, LIMIT, OFFSET, random order, reorder | QueryBuilderCore |
| `QueryLocking` | Pessimistic locking (FOR UPDATE, FOR SHARE, NOWAIT, SKIP LOCKED) | QueryBuilderCore, SqlBuilder |
| `QueryUnion` | UNION and UNION ALL operations | QueryBuilderCore, SqlBuilder |
| `QueryCte` | Common Table Expressions (WITH / WITH RECURSIVE) | QueryBuilderCore, SqlBuilder |
| `QueryJson` | JSON path conditions, contains, length, driver-aware compilation | QueryBuilderCore, QueryConditions |
| `QueryDebug` | Debug utilities (toSql, dump, dd) | All traits |

### Interfaces

Each trait has a corresponding contract under `Rcalicdan\QueryBuilderPrimitives\Interfaces\`:

| Interface | Covers |
| :--- | :--- |
| `CoreInterface` | `from`, `select`, `addSelect`, `selectRaw`, `selectDistinct` |
| `ConditionInterface` | All WHERE, HAVING, LIKE methods |
| `AdvancedConditionInterface` | `whereGroup`, `whereExists`, `whereSub`, etc. |
| `ConditionalInterface` | `when`, `unless` |
| `JoinInterface` | All JOIN methods (string and closure conditions) |
| `JoinClauseInterface` | `on`, `orOn` for advanced closure-based joins |
| `GroupingInterface` | `groupBy`, `groupByRaw`, `orderBy`, `orderByRaw`, `latest`, `oldest`, `limit`, `offset`, `forPage`, `inRandomOrder`, `reorder` |
| `LockingInterface` | All locking methods |
| `UnionInterface` | `union`, `unionAll` |
| `CteInterface` | `with` |
| `JsonConditionInterface` | `whereJson`, `whereJsonContains`, `whereJsonLength`, `whereJsonDoesntContain`, and OR variants |
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
    ->halt(); // Debug and die
```

---

## Trait Details

### QueryBuilderCore

Foundation trait providing core properties and select/driver management.

**Properties:**
*   `$table` - Table name
*   `$select` - Select columns
*   `$bindings` - Parameter bindings array (keyed by type: `cte`, `select`, `join`, `where`, `groupBy`, `having`, `orderBy`, `union`, etc.)

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

`newQuery()` is the internal factory used by `QueryAdvancedConditions`, `QueryUnion`, `QueryCte`, and `QueryJoin` whenever they need a fresh builder instance. The default implementation calls `new static()` with no arguments.

**If your concrete class has required constructor parameters** (e.g., a PDO connection, a service container, or a config object), you **must** override `newQuery()` to pass those dependencies. Without this, subquery methods (`whereExists`, `whereGroup`, `whereSub`, `union`, `with`, etc.) will throw a `\LogicException` at runtime.

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
- **Invokable objects** — any object implementing `__invoke` (including Closures). Called with the builder as argument; its return value becomes the condition.
- **Not supported as conditions** — string callables (`'MyClass::method'`) and array callables (`[$obj, 'method']`) are treated as plain values, not resolved as callables.

**Examples:**
```php
// Scalar value — applied when truthy
$qb->from('users')
    ->when($status, fn($q, $v) => $q->where('status', $v));

// With a default branch (runs when value is falsy)
$qb->from('users')
    ->when($role,
        fn($q, $v) => $q->where('role', $v),
        fn($q, $v) => $q->where('role', 'guest')
    );

// Invokable class as $value
class HasActiveSubscription
{
    public function __invoke(mixed $builder): bool
    {
        return auth()->user()?->hasActiveSubscription() ?? false;
    }
}

$qb->from('features')
    ->when(new HasActiveSubscription(), fn($q, $v) => $q->where('tier', 'premium'));

// Closure as $value (also an invokable object)
$qb->from('orders')
    ->when(fn($q) => auth()->user()->isAdmin(), fn($q, $v) => $q->where('user_id', auth()->id()));

// unless() — applies callback when value is falsy
$qb->from('posts')
    ->unless($isAdmin, fn($q, $v) => $q->where('published', true));

// Chaining multiple conditionals
$qb->from('users')
    ->when($search,    fn($q, $v) => $q->like('name', $v))
    ->when($sortField, fn($q, $v) => $q->orderBy($v, 'DESC'))
    ->unless($isAdmin, fn($q, $v) => $q->where('active', true));
```

---

### QueryJoin

JOIN operations. The condition argument accepts either a plain string (simple `ON` expression) or a closure that receives a `JoinClause` for building multi-condition, mixed column-and-value joins.

**Dependencies:** Requires `QueryBuilderCore`, `JoinClause`

**Methods:**
```php
join(string $table, string|callable $condition, string $type = 'INNER'): static
leftJoin(string $table, string|callable $condition): static
rightJoin(string $table, string|callable $condition): static
innerJoin(string $table, string|callable $condition): static
crossJoin(string $table): static
```

**`JoinClause` methods (available inside the closure):**
```php
on(string $first, ?string $operator, ?string $second, string $boolean = 'AND'): static
orOn(string $first, ?string $operator, ?string $second): static
// All QueryConditions methods are also available (where, orWhere, whereNull, etc.)
```

**Examples:**
```php
// Simple string condition
$qb->from('users')
    ->innerJoin('profiles', 'profiles.user_id = users.id');

// Advanced closure — multi-condition join
$qb->from('users')
    ->leftJoin('orders', function($join) {
        return $join
            ->on('orders.user_id', '=', 'users.id')
            ->on('orders.status', '=', 'users.preferred_status');
    });

// Mixed column comparison and value filter in a single join
$qb->from('users')
    ->leftJoin('orders', function($join) {
        return $join
            ->on('orders.user_id', '=', 'users.id')
            ->where('orders.status', 'completed')
            ->whereNull('orders.deleted_at');
    });

// OR ON condition
$qb->from('contacts')
    ->leftJoin('users', function($join) {
        return $join
            ->on('users.email', '=', 'contacts.primary_email')
            ->orOn('users.email', '=', 'contacts.secondary_email');
    });

// Multiple joins
$qb->from('users')
    ->leftJoin('profiles', 'profiles.user_id = users.id')
    ->leftJoin('orders', 'orders.user_id = users.id')
    ->leftJoin('payments', 'payments.order_id = orders.id');

// CROSS JOIN
$qb->from('colors')->crossJoin('sizes');
```

---

### QueryGrouping

Grouping, ordering, pagination, random ordering, and reordering.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**
```php
groupBy(string|array $columns): static
groupByRaw(string $sql, array $bindings = []): static
orderBy(string $column, string $direction = 'ASC'): static
orderByAsc(string $column): static
orderByDesc(string $column): static
orderByRaw(string $sql, array $bindings = []): static
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
// Standard GROUP BY
$qb->groupBy('status');
$qb->groupBy(['user_id', 'status']);
$qb->groupBy('user_id, status');   // comma-separated string also works

// Raw GROUP BY — for expressions, ROLLUP, CUBE, etc.
$qb->from('orders')->groupByRaw('ROLLUP(user_id)');
$qb->from('events')->groupByRaw('DATE_FORMAT(created_at, ?)', ['%Y-%m']);

// Standard ORDER BY
$qb->orderBy('created_at', 'DESC')->orderBy('name', 'ASC');
$qb->orderByDesc('created_at');
$qb->orderByAsc('name');

// Raw ORDER BY — for expressions, FIELD(), CASE WHEN, etc.
$qb->from('orders')->orderByRaw('FIELD(status, ?, ?, ?)', ['pending', 'active', 'closed']);
$qb->from('products')->orderByRaw('CASE WHEN featured = 1 THEN 0 ELSE 1 END, name ASC');

// Chaining standard and raw ORDER BY
$qb->from('products')
    ->orderByRaw('FIELD(status, ?, ?)', ['featured', 'active'])
    ->orderByAsc('name');

// latest() / oldest() — semantic aliases defaulting to 'created_at'
$qb->from('posts')->latest();               // ORDER BY created_at DESC
$qb->from('posts')->oldest();               // ORDER BY created_at ASC
$qb->from('posts')->latest('published_at'); // ORDER BY published_at DESC

// Random order — adapts per driver
$qb->from('products')->inRandomOrder();
// MySQL:          ORDER BY RAND()
// PgSQL / SQLite: ORDER BY RANDOM()

// reorder() — clear existing ORDER BY and optionally replace
$base    = $qb->from('users')->orderByDesc('created_at');
$fresh   = $base->reorder();                    // clears all ORDER BY
$renewed = $base->reorder('name', 'ASC');       // clears then sets ORDER BY name ASC
$base->reorder()->orderByRaw('RAND()');         // clear then raw

// LIMIT / OFFSET / pagination
$qb->limit(10)->offset(20);
$qb->limit(10, 20);        // combined — LIMIT 10 OFFSET 20
$qb->forPage(2, 25);       // page 2, 25 per page = LIMIT 25 OFFSET 25
$qb->forPage(1);           // page 1, default 15 per page
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
$qb->from('orders')->where('id', 1)->lockForUpdate()->toSql();
// MySQL/PgSQL: SELECT * FROM orders WHERE id = ? FOR UPDATE

// Shared lock
$qb->from('inventory')->where('product_id', 42)->lockForShare()->toSql();
// MySQL: ... LOCK IN SHARE MODE   PgSQL: ... FOR SHARE

// Fail immediately if locked
$qb->from('orders')->where('status', 'pending')->lockForUpdate()->noWait()->toSql();
// ... FOR UPDATE NOWAIT

// Skip locked rows (queue worker pattern)
$qb->from('jobs')->where('status', 'pending')->oldest()->limit(1)->lockForUpdate()->skipLocked()->toSql();
// ... FOR UPDATE SKIP LOCKED

// PostgreSQL OF clause
$qb->from('orders')->setDriver('pgsql')->join('users', 'orders.user_id = users.id')->lockForUpdate()->lockOf('orders')->toSql();
// ... FOR UPDATE OF orders

// Remove lock
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
        return $query->from('archived_users')->select('id', 'name', 'email');
    });

// UNION ALL (keeps duplicates)
$qb->from('orders_2023')
    ->select('id', 'total', 'created_at')
    ->unionAll(function($query) {
        return $query->from('orders_2024')->select('id', 'total', 'created_at');
    })
    ->latest('created_at');

// Chaining multiple UNIONs
$qb->from('employees')->select('id', 'name', 'department')->where('active', true)
    ->union(fn($q) => $q->from('contractors')->select('id', 'name', 'department')->where('active', true))
    ->union(fn($q) => $q->from('interns')->select('id', 'name', 'department'))
    ->orderBy('name');
```

> `ORDER BY`, `LIMIT`, and `OFFSET` placed on the outer query apply to the full union result set. Column counts and types must match across all unioned queries.

---

### QueryCte

Common Table Expressions (CTEs) — `WITH` and `WITH RECURSIVE` clauses. CTEs let you define named temporary result sets that can be referenced in the main query, improving readability and enabling recursive queries such as tree traversal.

**Dependencies:** Requires `QueryBuilderCore` and `SqlBuilder`

> **Note:** This trait uses `newQuery()` internally. If your builder has constructor arguments, override `newQuery()` — see the [QueryBuilderCore](#querybuildercore) section above.

**Methods:**
```php
with(string $name, callable $callback, bool $recursive = false): static
withRecursive(string $name, callable $callback): static
```

`withRecursive()` is a shorthand for `with($name, $callback, recursive: true)` — it exists purely for readability when the recursive intent should be obvious at the call site. Both compile identically. If any CTE in the chain is recursive, the entire `WITH` clause is compiled as `WITH RECURSIVE`.

The `$callback` receives a fresh `QueryBuilderPrimitiveInterface` instance and must return it after building the CTE's SELECT query. Multiple `with()` / `withRecursive()` calls accumulate CTEs in declaration order.

**Examples:**
```php
// Simple CTE — pre-filter before the main query
$qb->from('active_users')
    ->with('active_users', function($query) {
        return $query->from('users')->where('status', 'active');
    })
    ->select('id', 'name', 'email')
    ->where('created_at', '>=', '2024-01-01')
    ->toSql();
// WITH active_users AS (SELECT * FROM users WHERE status = ?)
// SELECT id, name, email FROM active_users WHERE created_at >= ?

// Multiple CTEs — each references the previous
$qb->from('final')
    ->with('base',   fn($q) => $q->from('orders')->where('status', 'completed'))
    ->with('totals', fn($q) => $q->from('base')->select('user_id')->selectRaw('SUM(total) as spent')->groupBy('user_id'))
    ->with('final',  fn($q) => $q->from('totals')->where('spent', '>', 1000))
    ->select('user_id', 'spent')
    ->orderByDesc('spent');
// WITH base AS (...), totals AS (...), final AS (...)
// SELECT user_id, spent FROM final ORDER BY spent DESC

// Recursive CTE using with() with explicit flag
$qb->from('category_tree')
    ->with('category_tree', function($query) {
        return $query
            ->from('categories')
            ->select('id', 'name', 'parent_id')
            ->where('parent_id', null)
            ->unionAll(function($q) {
                return $q
                    ->from('categories')
                    ->select('categories.id', 'categories.name', 'categories.parent_id')
                    ->join('category_tree', 'category_tree.id = categories.parent_id');
            });
    }, recursive: true)
    ->select('id', 'name', 'parent_id')
    ->toSql();

// Same recursive CTE using withRecursive() — cleaner, intent is obvious
$qb->from('category_tree')
    ->withRecursive('category_tree', function($query) {
        return $query
            ->from('categories')
            ->select('id', 'name', 'parent_id')
            ->where('parent_id', null)           // anchor: root nodes
            ->unionAll(function($q) {
                return $q
                    ->from('categories')
                    ->select('categories.id', 'categories.name', 'categories.parent_id')
                    ->join('category_tree', 'category_tree.id = categories.parent_id');
            });
    })
    ->select('id', 'name', 'parent_id')
    ->toSql();
// WITH RECURSIVE category_tree AS (
//   SELECT id, name, parent_id FROM categories WHERE parent_id IS NULL
//   UNION ALL
//   SELECT categories.id, categories.name, categories.parent_id
//   FROM categories INNER JOIN category_tree ON category_tree.id = categories.parent_id
// )
// SELECT id, name, parent_id FROM category_tree

// Mixing regular and recursive CTEs in the same chain
// The presence of withRecursive() causes the whole clause to be WITH RECURSIVE
$qb->from('result')
    ->with('active', fn($q) => $q->from('users')->where('status', 'active'))
    ->withRecursive('org_tree', function($q) {
        return $q->from('employees')
                 ->select('id', 'name', 'manager_id')
                 ->whereNull('manager_id')
                 ->unionAll(fn($u) => $u
                     ->from('employees')
                     ->select('employees.id', 'employees.name', 'employees.manager_id')
                     ->join('org_tree', 'org_tree.id = employees.manager_id'));
    })
    ->with('result', fn($q) => $q->from('org_tree')->join('active', 'active.id = org_tree.id'))
    ->get();
// WITH RECURSIVE active AS (...), org_tree AS (...), result AS (...)
// SELECT * FROM result

// CTE bindings compile before SELECT bindings — the order in getBindings() is:
// [cte bindings...] [select bindings...] [join bindings...] [where bindings...] ...
```

---

### QueryJson

Driver-aware JSON column conditions. Supports dot/arrow path notation (`options->preferences->theme`) and compiles to the correct dialect for each database.

**Dependencies:** Requires `QueryBuilderCore` and `QueryConditions`

**Methods:**
```php
whereJson(string $column, mixed $operator, mixed $value, string $boolean = 'AND'): static
orWhereJson(string $column, mixed $operator, mixed $value): static
whereJsonContains(string $column, mixed $value, string $boolean = 'AND'): static
orWhereJsonContains(string $column, mixed $value): static
whereJsonDoesntContain(string $column, mixed $value, string $boolean = 'AND'): static
orWhereJsonDoesntContain(string $column, mixed $value): static
whereJsonLength(string $column, string $operator, int $value, string $boolean = 'AND'): static
orWhereJsonLength(string $column, string $operator, int $value): static
```

#### Path notation

Use `->` to traverse JSON object keys. Both levels of nesting and flat column references are supported:

```
'options'                     — the root column
'options->theme'              — one level deep
'options->preferences->theme' — nested path
```

#### Driver compilation matrix

| Method | MySQL | PostgreSQL | SQLite |
| :--- | :--- | :--- | :--- |
| `whereJson` | `JSON_UNQUOTE(JSON_EXTRACT(col, '$.path')) op ?` | `col#>>'{path}' op ?` | `json_extract(col, '$.path') op ?` |
| `whereJsonContains` | `JSON_CONTAINS(col, ?, '$.path')` | `col::jsonb @> ?::jsonb` | `EXISTS (SELECT 1 FROM json_each(...) WHERE value = ?)` |
| `whereJsonDoesntContain` | `NOT JSON_CONTAINS(...)` | `NOT col::jsonb @> ?::jsonb` | `NOT EXISTS (SELECT 1 FROM json_each(...) ...)` |
| `whereJsonLength` | `JSON_LENGTH(JSON_EXTRACT(col, '$.path')) op ?` | `jsonb_array_length(col::jsonb) op ?` | `json_array_length(json_extract(col, '$.path')) op ?` |

**Examples:**
```php
// Compare a JSON scalar value
$qb->from('users')
    ->whereJson('options->theme', '=', 'dark');
// MySQL:  WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.theme')) = ?
// PgSQL:  WHERE options#>>'{theme}' = ?
// SQLite: WHERE json_extract(options, '$.theme') = ?

// Two-argument shorthand (defaults to '=')
$qb->from('users')->whereJson('options->notifications->email', true);

// Nested path
$qb->from('users')->whereJson('options->preferences->theme', 'dark');
// MySQL: WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.preferences.theme')) = ?

// OR variant
$qb->from('users')
    ->whereJson('settings->level', '>', 5)
    ->orWhereJson('settings->role', 'admin');

// Check if a JSON array contains a value
$qb->from('users')
    ->whereJsonContains('options->languages', 'en');
// MySQL:  WHERE JSON_CONTAINS(options, ?, '$.languages')
// PgSQL:  WHERE (options->'languages')::jsonb @> ?::jsonb
// SQLite: WHERE EXISTS (SELECT 1 FROM json_each(json_extract(options, '$.languages')) WHERE value = ?)

// NOT contains
$qb->from('users')
    ->whereJsonDoesntContain('options->blocked_countries', 'PH');

// OR contains variants
$qb->from('products')
    ->whereJsonContains('tags', 'sale')
    ->orWhereJsonContains('tags', 'featured');

// Check JSON array length
$qb->from('users')
    ->whereJsonLength('options->languages', '>', 2);
// MySQL:  WHERE JSON_LENGTH(JSON_EXTRACT(options, '$.languages')) > ?
// PgSQL:  WHERE jsonb_array_length((options->'languages')::jsonb) > ?
// SQLite: WHERE json_array_length(json_extract(options, '$.languages')) > ?

// Exact length
$qb->from('posts')
    ->whereJsonLength('metadata->tags', '=', 0);

// OR length variant
$qb->from('users')
    ->whereJsonLength('options->languages', '>=', 3)
    ->orWhereJsonLength('options->skills', '>', 5);

// Mixing JSON conditions with regular conditions
$qb->from('users')
    ->where('active', true)
    ->whereJson('options->theme', 'dark')
    ->whereJsonContains('options->languages', 'en')
    ->whereJsonLength('options->skills', '>', 2);
```

---

### QueryDebug

Debugging utilities.

**Dependencies:** Requires all other traits

**Methods:**
```php
toSql(): string
getBindings(): array
toRawSql(): string
debug(): static
halt(): never
```

**Examples:**
```php
// Get SQL query
$sql = $qb->from('users')->where('status', 'active')->toSql();
echo $sql; // SELECT * FROM users WHERE status = ?

// Get bindings
$bindings = $qb->getBindings();
var_dump($bindings); // ['active']

// Get interpolated SQL (DEBUG ONLY — never use for execution!)
$rawSql = $qb->toRawSql();
echo $rawSql; // SELECT * FROM users WHERE status = 'active'

// Dump and continue
$qb->from('users')->where('status', 'active')->halt()->where('age', '>=', 18)->halt();

// Dump and die (stops execution)
$qb->from('users')->where('status', 'active')->halt();
```

---

### SqlBuilder

Builds SQL query strings from accumulated state.

**Dependencies:** Requires `QueryBuilderCore` and properties from condition/join/grouping/cte traits

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
buildCteClause(): string
buildAggregateQuery(string $function, string $column): string
buildUpsertQuery(array $data, string|array $uniqueColumns, ?array $updateColumns = null): string
buildExistsQuery(): string
buildIncrementQuery(string $column, int|float $amount = 1, array $extra = []): string
buildDecrementQuery(string $column, int|float $amount = 1, array $extra = []): string
```

> `buildHavingClause()` handles both `AND` and `OR` conditions, driven by the `$boolean` parameter on `having()` and `havingRaw()`.
> `buildCteClause()` is prepended automatically by `buildSelectQuery()`, `buildCountQuery()`, and `buildAggregateQuery()` — you do not need to call it manually.

#### `buildExistsQuery()`

Wraps the current query in `SELECT EXISTS(...)`. Internally resets the select to `1` and strips `ORDER BY` (unless `LIMIT`/`OFFSET` is set) to keep the subquery lean.

```php
public function exists(): bool
{
    $stmt = $this->pdo->prepare($this->buildExistsQuery());
    $stmt->execute($this->getCompiledBindings());
    return (bool) $stmt->fetchColumn();
}

$exists = $qb->from('users')->where('email', 'john@example.com')->exists();
// SELECT EXISTS(SELECT 1 FROM users WHERE email = ?)
```

#### `buildIncrementQuery()`

Builds an atomic `UPDATE` that adds `$amount` to a column. `$extra` values bind before WHERE bindings.

```php
public function increment(string $column, int|float $amount = 1, array $extra = []): int
{
    $stmt = $this->pdo->prepare($this->buildIncrementQuery($column, $amount, $extra));
    $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
    return $stmt->rowCount();
}

$qb->from('products')->where('id', 5)->increment('stock', 3, ['updated_at' => now()]);
// UPDATE products SET stock = stock + 3, updated_at = ? WHERE id = ?
```

#### `buildDecrementQuery()`

Mirror of `buildIncrementQuery()` — subtracts `$amount`. Same binding order.

```php
public function decrement(string $column, int|float $amount = 1, array $extra = []): int
{
    $stmt = $this->pdo->prepare($this->buildDecrementQuery($column, $amount, $extra));
    $stmt->execute([...array_values($extra), ...$this->getCompiledBindings()]);
    return $stmt->rowCount();
}

$qb->from('accounts')->where('user_id', 12)->decrement('balance', 50.00, ['last_withdrawal' => now()]);
// UPDATE accounts SET balance = balance - 50, last_withdrawal = ? WHERE user_id = ?
```

#### `buildInsertIgnoreQuery()`

Builds a driver-aware insert that silently skips rows violating a unique constraint. Supports single-row and batch inserts.

| Driver | Syntax |
| :--- | :--- |
| MySQL | `INSERT IGNORE INTO ...` |
| PostgreSQL | `INSERT INTO ... ON CONFLICT DO NOTHING` |
| SQLite | `INSERT OR IGNORE INTO ...` |

```php
public function insertIgnore(array $data): bool
{
    $stmt = $this->pdo->prepare($this->buildInsertIgnoreQuery($data));
    $isBatch = is_array(reset($data));
    $values  = $isBatch ? array_merge(...array_map('array_values', $data)) : array_values($data);
    return $stmt->execute($values);
}

$qb->from('tags')->insertIgnore(['name' => 'php', 'slug' => 'php']);
```

---

## Immutability

All methods return a **new instance** of the query builder, ensuring immutability:

```php
$base = $qb->from('users')->where('status', 'active');

$query1 = $base->where('age', '>=', 18);
$query2 = $base->where('country', 'US');

// $base, $query1, and $query2 are three completely independent queries

// Same applies to locks, unions, CTEs, and ordering
$plain    = $qb->from('orders')->where('status', 'pending');
$locked   = $plain->lockForUpdate();
$union    = $plain->union(fn($q) => $q->from('archived_orders'));
$withCte  = $plain->with('base', fn($q) => $q->from('orders')->where('status', 'pending'));
$sorted   = $plain->latest();

// $plain is unchanged; every fork is completely independent
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
        return $stmt->execute(array_merge(...array_map('array_values', $data)));
    }

    public function insertIgnore(array $data): bool
    {
        $stmt   = $this->pdo->prepare($this->buildInsertIgnoreQuery($data));
        $isBatch = is_array(reset($data));
        $values  = $isBatch ? array_merge(...array_map('array_values', $data)) : array_values($data);
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

// Basic query
$users = $qb->from('users')->where('status', 'active')->latest()->limit(10)->get();

// CTE query
$report = $qb->from('summary')
    ->with('summary', fn($q) => $q->from('orders')->select('user_id')->selectRaw('SUM(total) as spent')->groupBy('user_id'))
    ->where('spent', '>', 500)
    ->get();

// JSON condition
$darkModeUsers = $qb->from('users')->whereJson('options->theme', 'dark')->get();

// Conditional filtering
$results = $qb->from('users')
    ->when($search, fn($q, $v) => $q->like('name', $v))
    ->when($role,   fn($q, $v) => $q->where('role', $v))
    ->latest()
    ->get();

// Advanced join with closure
$orders = $qb->from('orders')
    ->leftJoin('users', function($join) {
        return $join->on('users.id', '=', 'orders.user_id')->where('users.active', true);
    })
    ->get();
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
    use QueryCte;
    use QueryJson;
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
    use QueryCte;
    use QueryDebug;
}
```

### 4. Full-Featured (All Traits)

```php
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
    ->whereGroup(fn($q) => $q->where('status', 'active')->where('role', 'admin'))
    ->whereGroup(fn($q) => $q->where('status', 'pending')->where('invited', true));
```

### Conditional Query Building

```php
$qb->from('products')
    ->when($categoryId, fn($q, $v) => $q->where('category_id', $v))
    ->when($maxPrice,   fn($q, $v) => $q->where('price', '<=', $v))
    ->when($inStock,    fn($q, $v) => $q->where('stock', '>', 0))
    ->unless($showAll,  fn($q, $v) => $q->where('active', true))
    ->latest('updated_at');

// Dynamic sort with fallback
$qb->from('posts')
    ->when(
        $sortColumn,
        fn($q, $v) => $q->orderBy($v, $sortDirection ?? 'ASC'),
        fn($q, $v) => $q->latest()
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
```

### Subquery Patterns

```php
$qb->from('users')
    ->whereExists(function($q) {
        return $q->from('orders')
                 ->whereRaw('orders.user_id = users.id')
                 ->where('total', '>', 1000);
    });

$qb->from('audit_log')
    ->whereColumn('expected_hash', 'actual_hash')
    ->orWhereColumn('verified_at', '>', 'created_at');
```

### Existence Checks

```php
if ($qb->from('users')->where('email', $email)->exists()) {
    throw new \RuntimeException('Email already registered.');
}

$isEnrolled = $qb->from('enrollments')
    ->where('user_id', $userId)
    ->where('course_id', $courseId)
    ->whereNull('cancelled_at')
    ->exists();
```

### Atomic Counters

```php
$qb->from('posts')->where('id', $postId)->increment('views');

$qb->from('events')
    ->where('id', $eventId)
    ->decrement('seats_remaining', 1, ['last_booking_at' => now()]);
```

### Insert Ignore

```php
$qb->from('tags')->insertIgnore(['name' => 'php', 'slug' => 'php']);

$qb->from('user_roles')->insertIgnore([
    ['user_id' => 1, 'role' => 'editor'],
    ['user_id' => 2, 'role' => 'editor'],
    ['user_id' => 1, 'role' => 'editor'], // duplicate — silently skipped
]);
```

### CTE Patterns

```php
// Simple pre-filter CTE
$qb->from('active_users')
    ->with('active_users', fn($q) => $q->from('users')->where('status', 'active'))
    ->select('id', 'name')
    ->latest();

// Chained CTEs — each builds on the previous
$qb->from('final')
    ->with('raw',    fn($q) => $q->from('orders')->where('status', 'completed'))
    ->with('totals', fn($q) => $q->from('raw')->select('user_id')->selectRaw('SUM(total) as spent')->groupBy('user_id'))
    ->with('final',  fn($q) => $q->from('totals')->where('spent', '>', 500))
    ->orderByDesc('spent');

// Recursive CTE — withRecursive() makes the intent explicit
$qb->from('tree')
    ->withRecursive('tree', function($q) {
        return $q->from('categories')
                 ->select('id', 'name', 'parent_id')
                 ->where('parent_id', null)
                 ->unionAll(fn($u) => $u
                     ->from('categories')
                     ->select('categories.id', 'categories.name', 'categories.parent_id')
                     ->join('tree', 'tree.id = categories.parent_id'));
    })
    ->get();

// with() and withRecursive() are interchangeable for recursive CTEs —
// use whichever reads more clearly at the call site
$qb->with('tree', $callback, recursive: true);  // explicit flag
$qb->withRecursive('tree', $callback);           // shorthand — same output
```

### JSON Patterns

```php
// Scalar JSON field comparison
$qb->from('users')->whereJson('options->theme', 'dark')->get();

// Nested path
$qb->from('users')->whereJson('options->preferences->notifications', true)->get();

// Contains check
$qb->from('users')->whereJsonContains('options->languages', 'en')->get();

// Does not contain
$qb->from('users')->whereJsonDoesntContain('options->blocked', 'PH')->get();

// Array length
$qb->from('users')->whereJsonLength('options->skills', '>', 3)->get();

// Combining JSON and regular conditions
$qb->from('users')
    ->where('active', true)
    ->whereJson('options->theme', 'dark')
    ->whereJsonContains('options->languages', 'en')
    ->whereJsonLength('options->skills', '>=', 2)
    ->latest()
    ->get();

// OR JSON conditions
$qb->from('users')
    ->whereJson('settings->role', 'admin')
    ->orWhereJson('settings->role', 'moderator')
    ->get();
```

### Pessimistic Locking Patterns

```php
// Payment processing
$pdo->beginTransaction();
$order = $qb->from('orders')->where('id', $orderId)->where('status', 'pending')->lockForUpdate()->first();
$pdo->commit();

// Job queue with SKIP LOCKED
$pdo->beginTransaction();
$job = $qb->from('jobs')->where('status', 'available')->orderByDesc('priority')->oldest()->limit(1)->lockForUpdate()->skipLocked()->first();
$pdo->commit();
```

### UNION Patterns

```php
// Combine partitioned tables
$qb->from('logs_2024')
    ->select('id', 'user_id', 'action', 'created_at')
    ->unionAll(fn($q) => $q->from('logs_2025')->select('id', 'user_id', 'action', 'created_at'))
    ->latest('created_at')
    ->limit(100);

// Merge different record types
$qb->from('posts')
    ->select('id', 'title', 'created_at')->selectRaw("'post' as type")
    ->union(fn($q) => $q->from('comments')->select('id', 'body as title', 'created_at')->selectRaw("'comment' as type"))
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