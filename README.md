Of course. Here is the documentation presented in a standard format.

***

# Query Builder Primitives

A collection of PHP traits for building immutable, fluent query builders. This library provides low-level primitives without forcing any specific implementation.

## Installation

```bash
composer require rcalicdan/query-builder-primitives
```

## Philosophy

This library provides **building blocks**, not a complete query builder. You compose the traits you need to create your own custom query builder implementation.

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

QueryJoin (depends on: QueryBuilderCore)
QueryGrouping (depends on: QueryBuilderCore)
QueryDebug (depends on: all traits)
```

### Trait Descriptions

| Trait | Purpose | Dependencies |
| :--- | :--- | :--- |
| `QueryBuilderCore` | Core properties and table/select methods | None (foundation) |
| `SqlBuilder` | Builds SQL query strings | QueryBuilderCore + condition/join/grouping traits |
| `QueryConditions` | Basic WHERE, HAVING, LIKE clauses | QueryBuilderCore |
| `QueryAdvancedConditions` | Nested conditions, EXISTS, subqueries | QueryConditions, SqlBuilder |
| `QueryJoin` | JOIN operations (INNER, LEFT, RIGHT, CROSS) | QueryBuilderCore |
| `QueryGrouping` | GROUP BY, ORDER BY, LIMIT, OFFSET | QueryBuilderCore |
| `QueryDebug` | Debug utilities (toSql, dump, dd) | All traits |

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
    
    public function __construct(?string $table = null)
    {
        if ($table !== null) {
            $this->table = $table;
        }
    }
}

// Usage
$qb = new QueryBuilder();
$sql = $qb->table('users')
    ->select('id, name, email')
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

use Rcalicdan\QueryBuilderPrimitives\{
    QueryBuilderCore,
    QueryConditions,
    QueryAdvancedConditions,
    QueryJoin,
    QueryGrouping,
    QueryDebug,
    SqlBuilder
};

class FullQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryAdvancedConditions;
    use QueryJoin;
    use QueryGrouping;
    use QueryDebug;
    
    public function __construct(?string $table = null)
    {
        if ($table !== null) {
            $this->table = $table;
        }
    }
}

// Usage with advanced features
$qb = new FullQueryBuilder();
$qb->table('users')
    ->select('users.*, orders.total')
    ->leftJoin('orders', 'orders.user_id = users.id')
    ->whereGroup(function($query) {
        return $query
            ->where('status', 'active')
            ->orWhere('status', 'pending');
    })
    ->groupBy('users.id')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->dd(); // Debug and die
```

## Trait Details

### QueryBuilderCore

Foundation trait providing core functionality.

**Properties:**
*   `$table` - Table name
*   `$select` - Select columns
*   `$bindings` - Parameter bindings array

**Methods:**
```php
table(string $table): static
select(string|array $columns): static
addSelect(string|array $columns): static
selectDistinct(string|array $columns): static
```

**Example:**
```php
$qb->table('users')
    ->select(['id', 'name'])
    ->addSelect('email');
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
like(string $column, string $value, string $side = 'both'): static
having(string $column, mixed $operator, mixed $value): static
havingRaw(string $condition, array $bindings = []): static
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

// WHERE IN
$qb->whereIn('id', [1, 2, 3, 4, 5]);

// WHERE BETWEEN
$qb->whereBetween('age', [18, 65]);

// NULL checks
$qb->whereNull('deleted_at')
   ->whereNotNull('email');

// LIKE clauses
$qb->like('name', 'John', 'both');  // %John%
$qb->like('email', '@gmail.com', 'before'); // %@gmail.com
$qb->like('username', 'admin', 'after'); // admin%

// Raw WHERE
$qb->whereRaw('DATE(created_at) = CURDATE()');
$qb->whereRaw('age > ? AND status = ?', [18, 'active']);

// OR WHERE
$qb->where('status', 'active')
   ->orWhere('status', 'pending');

// HAVING
$qb->groupBy('user_id')
   ->having('COUNT(*)', '>', 5);
```

---

### QueryAdvancedConditions

Advanced nested conditions and subqueries.

**Dependencies:** Requires `QueryConditions` and `SqlBuilder`

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
$qb->table('users')
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
$qb->table('users')
    ->whereExists(function($query) {
        return $query
            ->table('orders')
            ->whereRaw('orders.user_id = users.id')
            ->where('orders.total', '>', 1000);
    });
// WHERE EXISTS (SELECT * FROM orders WHERE orders.user_id = users.id AND orders.total > ?)

// NOT EXISTS
$qb->table('users')
    ->whereNotExists(function($query) {
        return $query
            ->table('orders')
            ->whereRaw('orders.user_id = users.id');
    });

// Subquery in WHERE
$qb->table('users')
    ->whereSub('total_orders', '>', function($query) {
        return $query
            ->table('orders')
            ->selectRaw('COUNT(*)')
            ->whereRaw('orders.user_id = users.id');
    });
```

---

### QueryJoin

JOIN operations.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**```php
join(string $table, string $condition, string $type = 'INNER'): static
leftJoin(string $table, string $condition): static
rightJoin(string $table, string $condition): static
innerJoin(string $table, string $condition): static
crossJoin(string $table): static
```

**Examples:**
```php
// INNER JOIN
$qb->table('users')
    ->innerJoin('profiles', 'profiles.user_id = users.id');

// LEFT JOIN
$qb->table('users')
    ->leftJoin('orders', 'orders.user_id = users.id');

// Multiple joins
$qb->table('users')
    ->leftJoin('profiles', 'profiles.user_id = users.id')
    ->leftJoin('orders', 'orders.user_id = users.id')
    ->leftJoin('payments', 'payments.order_id = orders.id');

// CROSS JOIN
$qb->table('colors')
    ->crossJoin('sizes');
```

---

### QueryGrouping

Grouping, ordering, and pagination.

**Dependencies:** Requires `QueryBuilderCore`

**Methods:**
```php
groupBy(string|array $columns): static
orderBy(string $column, string $direction = 'ASC'): static
orderByAsc(string $column): static
orderByDesc(string $column): static
limit(int $limit, ?int $offset = null): static
offset(int $offset): static
paginate(int $page, int $perPage = 15): static
```

**Examples:**
```php
// GROUP BY
$qb->select('user_id, COUNT(*) as total')
    ->groupBy('user_id');

// Multiple GROUP BY
$qb->groupBy(['user_id', 'status']);

// ORDER BY
$qb->orderBy('created_at', 'DESC')
    ->orderBy('name', 'ASC');

// Shorthand
$qb->orderByDesc('created_at')
    ->orderByAsc('name');

// LIMIT and OFFSET
$qb->limit(10)
    ->offset(20);

// Or combined
$qb->limit(10, 20); // LIMIT 10 OFFSET 20

// Pagination helper
$qb->paginate(2, 25); // Page 2, 25 per page
// Equivalent to: limit(25, 25)
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
dump(): static
dd(): never
```

**Examples:**
```php
// Get SQL query
$sql = $qb->table('users')
    ->where('status', 'active')
    ->toSql();
echo $sql; // SELECT * FROM users WHERE status = ?

// Get bindings
$bindings = $qb->getBindings();
var_dump($bindings); // ['active']

// Get interpolated SQL (DEBUG ONLY - never use for execution!)
$rawSql = $qb->toRawSql();
echo $rawSql; // SELECT * FROM users WHERE status = 'active'

// Dump and continue
$qb->table('users')
    ->where('status', 'active')
    ->dump() // Prints debug info
    ->where('age', '>=', 18)
    ->dump();

// Dump and die (stops execution)
$qb->table('users')
    ->where('status', 'active')
    ->dd();
// Prints debug info and exits

// Debug output includes:
// - Formatted SQL with syntax highlighting
// - Bindings with types
// - Raw SQL with values interpolated
// - Query statistics (table, binding count, joins, conditions, etc.)
```

---

### SqlBuilder

Builds SQL query strings from accumulated state.

**Dependencies:** Requires `QueryBuilderCore` and properties from condition/join/grouping traits

**Protected Methods** (used internally):
```php
buildSelectQuery(): string
buildCountQuery(string $column = '*'): string
buildInsertQuery(array $data): string
buildInsertBatchQuery(array $data): string
buildUpdateQuery(array $data): string
buildDeleteQuery(): string
buildWhereClause(): string
```

> **Note:** These are protected methods intended for internal use or for extending the query builder with execution methods.

## Immutability

All methods return a **new instance** of the query builder, ensuring immutability:

```php
$baseQuery = $qb->table('users')->where('status', 'active');

$query1 = $baseQuery->where('age', '>=', 18);
$query2 = $baseQuery->where('country', 'US');

// $baseQuery remains unchanged
// $query1 and $query2 are different queries
```

## Extending with Execution

Since this is a primitive library, execution is not included. Here's how you'd add it:

```php
<?php

namespace App\Database;

use PDO;

class ExecutableQueryBuilder extends FullQueryBuilder
{
    public function __construct(
        private PDO $pdo,
        ?string $table = null
    ) {
        parent::__construct($table);
    }
    
    public function get(): array
    {
        $sql = $this->buildSelectQuery();
        $bindings = $this->getCompiledBindings();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function first(): ?array
    {
        $result = $this->limit(1)->get();
        return $result[0] ?? null;
    }
    
    public function count(string $column = '*'): int
    {
        $sql = $this->buildCountQuery($column);
        $bindings = $this->getCompiledBindings();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        
        return (int) $stmt->fetchColumn();
    }
    
    public function insert(array $data): bool
    {
        $sql = $this->buildInsertQuery($data);
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(array_values($data));
    }
    
    public function update(array $data): int
    {
        $sql = $this->buildUpdateQuery($data);
        $bindings = array_merge(array_values($data), $this->getCompiledBindings());
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->rowCount();
    }
    
    public function delete(): int
    {
        $sql = $this->buildDeleteQuery();
        $bindings = $this->getCompiledBindings();
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($bindings);
        
        return $stmt->rowCount();
    }
}

// Usage
$pdo = new PDO('mysql:host=localhost;dbname=mydb', 'user', 'pass');
$qb = new ExecutableQueryBuilder($pdo);

$users = $qb->table('users')
    ->where('status', 'active')
    ->orderBy('created_at', 'DESC')
    ->limit(10)
    ->get();
```

## Recommended Compositions

### 1. Read-Only Query Builder

```php
class ReadOnlyQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryJoin;
    use QueryGrouping;
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
    use QueryGrouping;
}
```

### 3. Reporting Query Builder (Heavy on Joins/Grouping)

```php
class ReportingQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryJoin;
    use QueryGrouping;
    use QueryDebug;
}
```

### 4. Complex Query Builder (All Features)

```php
class ComplexQueryBuilder
{
    use QueryBuilderCore;
    use SqlBuilder;
    use QueryConditions;
    use QueryAdvancedConditions;
    use QueryJoin;
    use QueryGrouping;
    use QueryDebug;
}
```

## Common Patterns

### Complex WHERE Logic

```php
// (status = 'active' AND role = 'admin') OR (status = 'pending' AND invited = true)
$qb->table('users')
    ->whereGroup(function($q) {
        return $q->where('status', 'active')
                 ->where('role', 'admin');
    })
    ->orWhereGroup(function($q) {
        return $q->where('status', 'pending')
                 ->where('invited', true);
    });
```

### Subquery Patterns

```php
// Users who have placed orders over $1000
$qb->table('users')
    ->whereExists(function($q) {
        return $q->table('orders')
                 ->whereRaw('orders.user_id = users.id')
                 ->where('total', '>', 1000);
    });

// Users with more orders than average
$qb->table('users')
    ->whereSub('total_orders', '>', function($q) {
        return $q->table('orders')
                 ->selectRaw('AVG(order_count)')
                 ->table('(SELECT user_id, COUNT(*) as order_count FROM orders GROUP BY user_id) as subquery');
    });
```

### Reporting Queries

```php
$qb->table('orders')
    ->select([
        'users.name',
        'COUNT(orders.id) as total_orders',
        'SUM(orders.total) as total_spent',
        'AVG(orders.total) as avg_order'
    ])
    ->leftJoin('users', 'users.id = orders.user_id')
    ->where('orders.status', 'completed')
    ->whereBetween('orders.created_at', ['2024-01-01', '2024-12-31'])
    ->groupBy('users.id')
    ->having('total_orders', '>', 5)
    ->orderByDesc('total_spent')
    ->limit(100);
```

## Requirements

*   PHP 8.1 or higher
*   PDO extension (for execution implementations)

## License

MIT

## Contributing

This is a primitive library - keep it simple and focused on building blocks, not opinions.