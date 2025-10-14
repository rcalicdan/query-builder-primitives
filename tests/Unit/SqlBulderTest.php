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