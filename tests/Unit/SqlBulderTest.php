<?php

use Tests\MockQueryBuilder;

beforeEach(function () {
    $this->builder = new MockQueryBuilder();
});

describe('QueryBuilderCore', function () {
    test('sets table name', function () {
        $query = $this->builder->table('users');
        
        expect($query->toSql())->toContain('FROM users');
    });

    test('sets select columns', function () {
        $query = $this->builder
            ->table('users')
            ->select(['id', 'name', 'email']);
        
        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('sets select columns from string', function () {
        $query = $this->builder
            ->table('users')
            ->select('id, name, email');
        
        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('adds select columns', function () {
        $query = $this->builder
            ->table('users')
            ->select('id')
            ->addSelect(['name', 'email']);
        
        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('selects distinct', function () {
        $query = $this->builder
            ->table('users')
            ->selectDistinct('email');
        
        expect($query->toSql())->toBe('SELECT DISTINCT email FROM users');
    });

    test('selects distinct with multiple columns', function () {
        $query = $this->builder
            ->table('users')
            ->selectDistinct(['name', 'email']);
        
        expect($query->toSql())->toContain('DISTINCT name');
    });

    test('returns compiled bindings', function () {
        $query = $this->builder
            ->table('users')
            ->where('id', 1)
            ->where('name', 'John');
        
        expect($query->getBindings())->toBe([1, 'John']);
    });
});

describe('QueryConditions', function () {
    test('adds where clause', function () {
        $query = $this->builder
            ->table('users')
            ->where('id', 1);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id = ?');
        expect($query->getBindings())->toBe([1]);
    });

    test('adds where clause with operator', function () {
        $query = $this->builder
            ->table('users')
            ->where('age', '>', 18);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE age > ?');
        expect($query->getBindings())->toBe([18]);
    });

    test('adds multiple where clauses', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->where('age', '>=', 21);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE status = ? AND age >= ?');
        expect($query->getBindings())->toBe(['active', 21]);
    });

    test('adds or where clause', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin');
        
        expect($query->toSql())->toContain('WHERE status = ? OR role = ?');
        expect($query->getBindings())->toBe(['active', 'admin']);
    });

    test('adds where in clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('id', [1, 2, 3]);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id IN (?, ?, ?)');
        expect($query->getBindings())->toBe([1, 2, 3]);
    });

    test('adds where in with empty array', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('id', []);
        
        expect($query->toSql())->toContain('0=1');
    });

    test('adds where not in clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereNotIn('status', ['banned', 'suspended']);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE status NOT IN (?, ?)');
        expect($query->getBindings())->toBe(['banned', 'suspended']);
    });

    test('adds where not in with empty array', function () {
        $query = $this->builder
            ->table('users')
            ->whereNotIn('id', []);
        
        expect($query->toSql())->toBe('SELECT * FROM users');
    });

    test('adds where between clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereBetween('age', [18, 65]);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE age BETWEEN ? AND ?');
        expect($query->getBindings())->toBe([18, 65]);
    });

    test('throws exception for invalid where between values', function () {
        expect(fn() => $this->builder
            ->table('users')
            ->whereBetween('age', [18])
        )->toThrow(\InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
    });

    test('adds where null clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereNull('deleted_at');
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE deleted_at IS NULL');
        expect($query->getBindings())->toBe([]);
    });

    test('adds where not null clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereNotNull('email_verified_at');
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE email_verified_at IS NOT NULL');
    });

    test('adds like clause with both wildcards', function () {
        $query = $this->builder
            ->table('users')
            ->like('name', 'John');
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE name LIKE ?');
        expect($query->getBindings())->toBe(['%John%']);
    });

    test('adds like clause with before wildcard', function () {
        $query = $this->builder
            ->table('users')
            ->like('name', 'John', 'before');
        
        expect($query->getBindings())->toBe(['%John']);
    });

    test('adds like clause with after wildcard', function () {
        $query = $this->builder
            ->table('users')
            ->like('name', 'John', 'after');
        
        expect($query->getBindings())->toBe(['John%']);
    });

    test('adds having clause', function () {
        $query = $this->builder
            ->table('users')
            ->groupBy('status')
            ->having('COUNT(*)', '>', 5);
        
        expect($query->toSql())->toContain('HAVING COUNT(*) > ?');
        expect($query->getBindings())->toBe([5]);
    });

    test('adds having raw clause', function () {
        $query = $this->builder
            ->table('users')
            ->groupBy('status')
            ->havingRaw('COUNT(*) > ?', [10]);
        
        expect($query->toSql())->toContain('HAVING COUNT(*) > ?');
        expect($query->getBindings())->toBe([10]);
    });

    test('adds where raw clause', function () {
        $query = $this->builder
            ->table('users')
            ->whereRaw('DATE(created_at) = ?', ['2024-01-01']);
        
        expect($query->toSql())->toContain('WHERE DATE(created_at) = ?');
        expect($query->getBindings())->toBe(['2024-01-01']);
    });

    test('adds or where raw clause', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhereRaw('is_admin = ?', [true]);
        
        expect($query->toSql())->toContain('WHERE status = ? OR is_admin = ?');
        expect($query->getBindings())->toBe(['active', true]);
    });

    test('resets where conditions', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->whereIn('id', [1, 2, 3])
            ->resetWhere();
        
        expect($query->toSql())->toBe('SELECT * FROM users');
        expect($query->getBindings())->toBe([]);
    });
});

describe('QueryAdvancedConditions', function () {
    test('adds where group', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->whereGroup(function ($query) {
                return $query
                    ->where('age', '>', 18)
                    ->where('country', 'US');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? AND (age > ? AND country = ?)');
        expect($query->getBindings())->toBe(['active', 18, 'US']);
    });

    test('adds where nested', function () {
        $query = $this->builder
            ->table('users')
            ->whereNested(function ($query) {
                return $query
                    ->where('name', 'John')
                    ->orWhere('name', 'Jane');
            });
        
        expect($query->toSql())->toContain('WHERE (name = ? OR name = ?)');
        expect($query->getBindings())->toBe(['John', 'Jane']);
    });

    test('adds or where nested', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhereNested(function ($query) {
                return $query
                    ->where('role', 'admin')
                    ->where('verified', true);
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR (role = ? AND verified = ?)');
        expect($query->getBindings())->toBe(['active', 'admin', true]);
    });

    test('adds where exists', function () {
        $query = $this->builder
            ->table('users')
            ->whereExists(function ($query) {
                return $query
                    ->table('orders')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE EXISTS (SELECT * FROM orders');
    });

    test('throws exception for where exists without table', function () {
        expect(fn() => $this->builder
            ->table('users')
            ->whereExists(function ($query) {
                return $query->where('id', 1);
            })
        )->toThrow(\InvalidArgumentException::class, 'Subquery must specify a table');
    });

    test('adds where not exists', function () {
        $query = $this->builder
            ->table('users')
            ->whereNotExists(function ($query) {
                return $query
                    ->table('bans')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE NOT EXISTS (SELECT * FROM bans');
    });

    test('adds or where exists', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhereExists(function ($query) {
                return $query
                    ->table('premium_memberships')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR EXISTS');
    });

    test('adds or where not exists', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhereNotExists(function ($query) {
                return $query
                    ->table('suspensions')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR NOT EXISTS');
    });

    test('adds where sub', function () {
        $query = $this->builder
            ->table('users')
            ->whereSub('user_count', '>', function ($query) {
                return $query
                    ->table('orders')
                    ->select('COUNT(*)');
            });
        
        expect($query->toSql())->toContain('WHERE user_count > (SELECT COUNT(*) FROM orders)');
    });
});

describe('QueryJoin', function () {
    test('adds inner join', function () {
        $query = $this->builder
            ->table('users')
            ->join('orders', 'users.id = orders.user_id');
        
        expect($query->toSql())->toContain('FROM users INNER JOIN orders ON users.id = orders.user_id');
    });

    test('adds left join', function () {
        $query = $this->builder
            ->table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id');
        
        expect($query->toSql())->toContain('LEFT JOIN profiles ON users.id = profiles.user_id');
    });

    test('adds right join', function () {
        $query = $this->builder
            ->table('users')
            ->rightJoin('teams', 'users.team_id = teams.id');
        
        expect($query->toSql())->toContain('RIGHT JOIN teams ON users.team_id = teams.id');
    });

    test('adds cross join', function () {
        $query = $this->builder
            ->table('users')
            ->crossJoin('settings');
        
        expect($query->toSql())->toContain('CROSS JOIN settings');
    });

    test('adds multiple joins', function () {
        $query = $this->builder
            ->table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id')
            ->innerJoin('orders', 'users.id = orders.user_id');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LEFT JOIN profiles');
        expect($sql)->toContain('INNER JOIN orders');
    });
});

describe('QueryGrouping', function () {
    test('adds group by', function () {
        $query = $this->builder
            ->table('orders')
            ->groupBy('status');
        
        expect($query->toSql())->toContain('GROUP BY status');
    });

    test('adds multiple group by columns', function () {
        $query = $this->builder
            ->table('orders')
            ->groupBy(['status', 'user_id']);
        
        expect($query->toSql())->toContain('GROUP BY status, user_id');
    });

    test('adds group by from string', function () {
        $query = $this->builder
            ->table('orders')
            ->groupBy('status, user_id');
        
        expect($query->toSql())->toContain('GROUP BY status, user_id');
    });

    test('adds order by', function () {
        $query = $this->builder
            ->table('users')
            ->orderBy('created_at', 'DESC');
        
        expect($query->toSql())->toContain('ORDER BY created_at DESC');
    });

    test('adds order by asc', function () {
        $query = $this->builder
            ->table('users')
            ->orderByAsc('name');
        
        expect($query->toSql())->toContain('ORDER BY name ASC');
    });

    test('adds order by desc', function () {
        $query = $this->builder
            ->table('users')
            ->orderByDesc('created_at');
        
        expect($query->toSql())->toContain('ORDER BY created_at DESC');
    });

    test('adds limit', function () {
        $query = $this->builder
            ->table('users')
            ->limit(10);
        
        expect($query->toSql())->toContain('LIMIT 10');
    });

    test('adds limit with offset', function () {
        $query = $this->builder
            ->table('users')
            ->limit(10, 20);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 20');
    });

    test('adds offset', function () {
        $query = $this->builder
            ->table('users')
            ->limit(10)
            ->offset(5);
        
        expect($query->toSql())->toContain('OFFSET 5');
    });

    test('paginates results', function () {
        $query = $this->builder
            ->table('users')
            ->paginate(2, 15);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 15');
        expect($sql)->toContain('OFFSET 15');
    });

    test('paginates first page', function () {
        $query = $this->builder
            ->table('users')
            ->paginate(1, 10);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 0');
    });
});

describe('QueryDebug', function () {
    test('converts to sql', function () {
        $query = $this->builder
            ->table('users')
            ->where('id', 1);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id = ?');
    });

    test('gets bindings', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'user']);
        
        expect($query->getBindings())->toBe(['active', 'admin', 'user']);
    });

    test('converts to raw sql', function () {
        $query = $this->builder
            ->table('users')
            ->where('name', 'John')
            ->where('age', 25);
        
        $rawSql = $query->toRawSql();
        expect($rawSql)->toContain("'John'");
        expect($rawSql)->toContain('25');
        expect($rawSql)->not->toContain('?');
    });

    test('dump returns same instance', function () {
        $query = $this->builder->table('users');
        
        ob_start();
        $result = $query->dump();
        ob_end_clean();
        
        expect($result)->toBe($query);
    });
});

describe('SqlBuilder', function () {
    test('builds select query', function () {
        $query = $this->builder
            ->table('users')
            ->select('id, name')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10);
        
        expect($query->toSql())->toBe('SELECT id, name FROM users WHERE status = ? ORDER BY name ASC LIMIT 10');
    });

    test('builds complex query with joins and groups', function () {
        $query = $this->builder
            ->table('orders')
            ->select('user_id, COUNT(*) as order_count')
            ->leftJoin('users', 'orders.user_id = users.id')
            ->where('orders.status', 'completed')
            ->groupBy('user_id')
            ->having('COUNT(*)', '>', 5)
            ->orderBy('order_count', 'DESC');
        
        $sql = $query->toSql();
        expect($sql)->toContain('SELECT user_id, COUNT(*) as order_count');
        expect($sql)->toContain('LEFT JOIN users');
        expect($sql)->toContain('WHERE orders.status = ?');
        expect($sql)->toContain('GROUP BY user_id');
        expect($sql)->toContain('HAVING COUNT(*) > ?');
    });

    test('combines and and or conditions correctly', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->where('verified', true)
            ->orWhere('role', 'admin');
        
        expect($query->toSql())->toContain('WHERE (status = ? AND verified = ?) OR role = ?');
    });
});

describe('Immutability', function () {
    test('query builder returns new instances', function () {
        $query1 = $this->builder->table('users');
        $query2 = $query1->where('id', 1);
        
        expect($query1)->not->toBe($query2);
        expect($query1->toSql())->toBe('SELECT * FROM users');
        expect($query2->toSql())->toBe('SELECT * FROM users WHERE id = ?');
    });

    test('multiple branches from same query', function () {
        $base = $this->builder->table('users')->where('status', 'active');
        
        $admins = $base->where('role', 'admin');
        $users = $base->where('role', 'user');
        
        expect($admins->toSql())->toContain("role = ?");
        expect($users->toSql())->toContain("role = ?");
        expect($admins->getBindings())->toBe(['active', 'admin']);
        expect($users->getBindings())->toBe(['active', 'user']);
    });
});

describe('Edge Cases', function () {
    test('handles empty table name gracefully', function () {
        $query = $this->builder->select('id');
        
        expect($query->toSql())->toBe('SELECT id FROM ');
    });

    test('handles very long column lists', function () {
        $columns = array_map(fn($i) => "column{$i}", range(1, 50));
        $query = $this->builder
            ->table('users')
            ->select($columns);
        
        expect($query->toSql())->toContain('column1');
        expect($query->toSql())->toContain('column50');
    });

    test('handles special characters in values', function () {
        $query = $this->builder
            ->table('users')
            ->where('name', "O'Brien")
            ->where('description', 'Quote: "test"');
        
        expect($query->getBindings())->toBe(["O'Brien", 'Quote: "test"']);
    });

    test('handles null values in where clause', function () {
        $query = $this->builder
            ->table('users')
            ->where('deleted_at', null);
        
        expect($query->getBindings())->toBe([null]);
    });

    test('handles boolean values', function () {
        $query = $this->builder
            ->table('users')
            ->where('is_active', true)
            ->where('is_deleted', false);
        
        expect($query->getBindings())->toBe([true, false]);
    });

    test('handles zero and empty string', function () {
        $query = $this->builder
            ->table('users')
            ->where('count', 0)
            ->where('name', '');
        
        expect($query->getBindings())->toBe([0, '']);
    });
});

describe('Complex Nested Conditions', function () {
    test('deeply nested where groups', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->whereNested(function ($q) {
                return $q
                    ->where('role', 'admin')
                    ->orWhereNested(function ($q2) {
                        return $q2
                            ->where('role', 'moderator')
                            ->where('verified', true);
                    });
            });
        
        $sql = $query->toSql();
        expect($sql)->toContain('status = ?');
        expect($query->getBindings())->toHaveCount(4);
    });

    test('multiple nested groups with AND', function () {
        $query = $this->builder
            ->table('users')
            ->whereNested(function ($q) {
                return $q->where('age', '>', 18)->where('age', '<', 65);
            })
            ->whereNested(function ($q) {
                return $q->where('country', 'US')->orWhere('country', 'CA');
            });
        
        expect($query->getBindings())->toBe([18, 65, 'US', 'CA']);
    });

    test('combines whereIn with nested conditions', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereNested(function ($q) {
                return $q->where('age', '>', 18)->orWhere('verified', true);
            });
        
        $sql = $query->toSql();
        expect($sql)->toContain('IN (?, ?)');
        expect($query->getBindings())->toBe(['active', 'pending', 18, true]);
    });
});

describe('Multiple Join Scenarios', function () {
    test('joins with where conditions', function () {
        $query = $this->builder
            ->table('users')
            ->join('profiles', 'users.id = profiles.user_id')
            ->join('orders', 'users.id = orders.user_id')
            ->where('users.status', 'active')
            ->where('orders.total', '>', 100);
        
        $sql = $query->toSql();
        expect($sql)->toContain('INNER JOIN profiles');
        expect($sql)->toContain('INNER JOIN orders');
        expect($sql)->toContain('WHERE');
    });

    test('mixed join types', function () {
        $query = $this->builder
            ->table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id')
            ->innerJoin('orders', 'users.id = orders.user_id')
            ->rightJoin('payments', 'orders.id = payments.order_id');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LEFT JOIN');
        expect($sql)->toContain('INNER JOIN');
        expect($sql)->toContain('RIGHT JOIN');
    });
});

describe('Subquery Edge Cases', function () {
    test('nested whereExists', function () {
        $query = $this->builder
            ->table('users')
            ->whereExists(function ($q) {
                return $q
                    ->table('orders')
                    ->where('user_id', 'users.id')
                    ->whereExists(function ($q2) {
                        return $q2
                            ->table('payments')
                            ->where('order_id', 'orders.id');
                    });
            });
        
        $sql = $query->toSql();
        expect($sql)->toContain('EXISTS');
        expect(substr_count($sql, 'EXISTS'))->toBe(2);
    });

    test('multiple whereSub clauses', function () {
        $query = $this->builder
            ->table('users')
            ->whereSub('order_count', '>', function ($q) {
                return $q->table('orders')->select('COUNT(*)');
            })
            ->whereSub('total_spent', '>', function ($q) {
                return $q->table('orders')->select('SUM(total)');
            });
        
        $sql = $query->toSql();
        expect(substr_count($sql, 'SELECT'))->toBeGreaterThan(1);
    });
});

describe('Order and Grouping Edge Cases', function () {
    test('multiple order by clauses', function () {
        $query = $this->builder
            ->table('users')
            ->orderBy('status', 'DESC')
            ->orderBy('name', 'ASC')
            ->orderBy('created_at', 'DESC');
        
        $sql = $query->toSql();
        expect($sql)->toContain('ORDER BY status DESC, name ASC, created_at DESC');
    });

    test('group by with having and order by', function () {
        $query = $this->builder
            ->table('orders')
            ->select('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->having('COUNT(*)', '>', 5)
            ->orderBy('total', 'DESC');
        
        $sql = $query->toSql();
        expect($sql)->toContain('GROUP BY');
        expect($sql)->toContain('HAVING');
        expect($sql)->toContain('ORDER BY');
    });

    test('multiple group by with multiple having', function () {
        $query = $this->builder
            ->table('orders')
            ->groupBy(['user_id', 'status'])
            ->having('COUNT(*)', '>', 5)
            ->having('SUM(total)', '>', 1000);
        
        $sql = $query->toSql();
        expect($sql)->toContain('GROUP BY user_id, status');
        expect(substr_count($sql, 'HAVING'))->toBe(1);
    });
});

describe('Pagination Edge Cases', function () {
    test('paginate with large page numbers', function () {
        $query = $this->builder
            ->table('users')
            ->paginate(100, 50);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 50');
        expect($sql)->toContain('OFFSET 4950');
    });

    test('paginate with zero per page throws or handles gracefully', function () {
        $query = $this->builder
            ->table('users')
            ->paginate(1, 0);
        
        expect($query->toSql())->toContain('LIMIT 0');
    });

    test('limit without offset', function () {
        $query = $this->builder
            ->table('users')
            ->limit(25);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 25');
        expect($sql)->not->toContain('OFFSET');
    });
});

describe('Like Clause Variations', function () {
    test('multiple like clauses', function () {
        $query = $this->builder
            ->table('users')
            ->like('name', 'John')
            ->like('email', 'gmail', 'after');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIKE');
        expect($query->getBindings())->toBe(['%John%', 'gmail%']);
    });

    test('like with empty string', function () {
        $query = $this->builder
            ->table('users')
            ->like('name', '');
        
        expect($query->getBindings())->toBe(['%%']);
    });
});

describe('WhereIn Edge Cases', function () {
    test('whereIn with single value', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('id', [1]);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id IN (?)');
        expect($query->getBindings())->toBe([1]);
    });

    test('whereIn with large array', function () {
        $values = range(1, 1000);
        $query = $this->builder
            ->table('users')
            ->whereIn('id', $values);
        
        expect($query->getBindings())->toHaveCount(1000);
        expect(substr_count($query->toSql(), '?'))->toBe(1000);
    });

    test('multiple whereIn clauses', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereIn('role', ['admin', 'moderator']);
        
        expect($query->getBindings())->toBe(['active', 'pending', 'admin', 'moderator']);
    });

    test('whereIn and whereNotIn together', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereNotIn('role', ['banned', 'suspended']);
        
        $sql = $query->toSql();
        expect($sql)->toContain('IN (?, ?)');
        expect($sql)->toContain('NOT IN (?, ?)');
    });
});

describe('WhereBetween Edge Cases', function () {
    test('whereBetween with same values', function () {
        $query = $this->builder
            ->table('users')
            ->whereBetween('age', [18, 18]);
        
        expect($query->toSql())->toContain('BETWEEN ? AND ?');
        expect($query->getBindings())->toBe([18, 18]);
    });

    test('whereBetween with dates', function () {
        $query = $this->builder
            ->table('orders')
            ->whereBetween('created_at', ['2024-01-01', '2024-12-31']);
        
        expect($query->getBindings())->toBe(['2024-01-01', '2024-12-31']);
    });

    test('multiple whereBetween clauses', function () {
        $query = $this->builder
            ->table('products')
            ->whereBetween('price', [10, 100])
            ->whereBetween('stock', [1, 1000]);
        
        expect($query->getBindings())->toBe([10, 100, 1, 1000]);
    });
});

describe('WhereNull Edge Cases', function () {
    test('multiple whereNull clauses', function () {
        $query = $this->builder
            ->table('users')
            ->whereNull('deleted_at')
            ->whereNull('banned_at');
        
        $sql = $query->toSql();
        expect($sql)->toContain('deleted_at IS NULL');
        expect($sql)->toContain('banned_at IS NULL');
    });

    test('whereNull and whereNotNull together', function () {
        $query = $this->builder
            ->table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at');
        
        $sql = $query->toSql();
        expect($sql)->toContain('IS NULL');
        expect($sql)->toContain('IS NOT NULL');
    });
});

describe('Raw SQL Edge Cases', function () {
    test('whereRaw with complex conditions', function () {
        $query = $this->builder
            ->table('users')
            ->whereRaw('DATE(created_at) = CURDATE()')
            ->whereRaw('YEAR(created_at) = ?', [2024]);
        
        $sql = $query->toSql();
        expect($sql)->toContain('DATE(created_at) = CURDATE()');
        expect($query->getBindings())->toBe([2024]);
    });

    test('mix whereRaw with regular where', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->whereRaw('age > ?', [18])
            ->where('verified', true);
        
        expect($query->getBindings())->toBe(['active', 18, true]);
    });

    test('orWhereRaw after where clauses', function () {
        $query = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->orWhereRaw('role = ? AND verified = ?', ['admin', true]);
        
        $sql = $query->toSql();
        expect($sql)->toContain('OR');
        expect($query->getBindings())->toBe(['active', 'admin', true]);
    });
});

describe('Select Variations', function () {
    test('select with table prefix', function () {
        $query = $this->builder
            ->table('users')
            ->select(['users.id', 'users.name', 'profiles.bio'])
            ->join('profiles', 'users.id = profiles.user_id');
        
        expect($query->toSql())->toContain('users.id, users.name, profiles.bio');
    });

    test('select with aliases', function () {
        $query = $this->builder
            ->table('users')
            ->select(['id', 'name as full_name', 'email as contact_email']);
        
        expect($query->toSql())->toContain('name as full_name');
    });

    test('select with functions', function () {
        $query = $this->builder
            ->table('orders')
            ->select(['user_id', 'COUNT(*) as order_count', 'SUM(total) as total_amount']);
        
        expect($query->toSql())->toContain('COUNT(*)');
        expect($query->toSql())->toContain('SUM(total)');
    });

    test('addSelect preserves existing selections', function () {
        $query = $this->builder
            ->table('users')
            ->select(['id', 'name'])
            ->addSelect('email')
            ->addSelect(['phone', 'address']);
        
        expect($query->toSql())->toBe('SELECT id, name, email, phone, address FROM users');
    });
});

describe('Immutability Advanced', function () {
    test('cloning preserves all state', function () {
        $query1 = $this->builder
            ->table('users')
            ->select('id, name')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10);
        
        $query2 = $query1->where('age', '>', 18);
        
        expect($query1->getBindings())->toBe(['active']);
        expect($query2->getBindings())->toBe(['active', 18]);
        expect($query1->toSql())->not->toContain('age');
        expect($query2->toSql())->toContain('age');
    });

    test('resetWhere creates new instance', function () {
        $query1 = $this->builder
            ->table('users')
            ->where('status', 'active')
            ->where('age', '>', 18);
        
        $query2 = $query1->resetWhere();
        
        expect($query1->getBindings())->toBe(['active', 18]);
        expect($query2->getBindings())->toBe([]);
        expect($query1)->not->toBe($query2);
    });
});

describe('toRawSql Edge Cases', function () {
    test('toRawSql with null values', function () {
        $query = $this->builder
            ->table('users')
            ->where('deleted_at', null)
            ->where('name', 'John');
        
        $rawSql = $query->toRawSql();
        expect($rawSql)->toContain('NULL');
        expect($rawSql)->toContain("'John'");
    });

    test('toRawSql with boolean values', function () {
        $query = $this->builder
            ->table('users')
            ->where('is_active', true)
            ->where('is_deleted', false);
        
        $rawSql = $query->toRawSql();
        // Check that booleans are properly represented
        expect($rawSql)->toContain('1'); // or 'true' depending on your implementation
        expect($rawSql)->toContain('0'); // or 'false'
    });

    test('toRawSql with array values in whereIn', function () {
        $query = $this->builder
            ->table('users')
            ->whereIn('id', [1, 2, 3, 4, 5]);
        
        $rawSql = $query->toRawSql();
        expect($rawSql)->not->toContain('?');
        expect($rawSql)->toContain('1');
        expect($rawSql)->toContain('5');
    });
});