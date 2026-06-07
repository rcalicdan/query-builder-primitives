<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryCte Primitives - Core Features', function () {
    test('compiles a basic single CTE SELECT query', function () {
        $query = MockQueryBuilder::table('active_users')
            ->select('id', 'name')
            ->with('active_users', function ($q) {
                return $q->from('users')
                    ->select('id', 'name')
                    ->where('status', 'active')
                ;
            })
        ;

        expect($query->toSql())->toBe(
            'WITH active_users AS (SELECT id, name FROM users WHERE status = ?) SELECT id, name FROM active_users'
        );
        expect($query->getBindings())->toBe(['active']);
    });

    test('compiles multiple consecutive CTE definitions with sequential binding preservation', function () {
        $query = MockQueryBuilder::table('matched_records')
            ->with('recent_orders', function ($q) {
                return $q->from('orders')
                    ->where('created_at', '>', '2024-01-01')
                ;
            })
            ->with('vip_users', function ($q) {
                return $q->from('users')
                    ->where('score', '>', 500)
                ;
            })
            ->where('amount', '>', 100)
        ;

        $sql = $query->toSql();

        expect($sql)->toBe(
            'WITH recent_orders AS (SELECT * FROM orders WHERE created_at > ?), vip_users AS (SELECT * FROM users WHERE score > ?) SELECT * FROM matched_records WHERE amount > ?'
        );

        expect($query->getBindings())->toBe(['2024-01-01', 500, 100]);
    });

    test('compiles recursive CTE using recursive modifier', function () {
        $query = MockQueryBuilder::table('org_chart')
            ->with('org_chart', function ($q) {
                return $q->from('employees')
                    ->select('id', 'name', 'manager_id')
                    ->where('id', 1)
                    ->unionAll(function ($union) {
                        return $union->from('employees as e')
                            ->select('e.id', 'e.name', 'e.manager_id')
                            ->join('org_chart as o', 'e.manager_id = o.id')
                        ;
                    })
                ;
            }, true)
        ;

        expect($query->toSql())->toContain('WITH RECURSIVE org_chart AS');
    });

    test('compiles count queries executing on top of CTE structures', function () {
        $query = MockQueryBuilder::table('filtered_users')
            ->with('filtered_users', function ($q) {
                return $q->from('users')->where('age', '>=', 18);
            })
        ;

        $sql = $query->buildCountQuery();

        expect($sql)->toBe(
            'WITH filtered_users AS (SELECT * FROM users WHERE age >= ?) SELECT COUNT(*) FROM filtered_users'
        );
        expect($query->getBindings())->toBe([18]);
    });

    test('compiles aggregate queries executing on top of CTE structures', function () {
        $query = MockQueryBuilder::table('financial_data')
            ->with('financial_data', function ($q) {
                return $q->from('transactions')->where('status', 'cleared');
            })
        ;

        $sql = $query->buildAggregateQuery('SUM', 'amount');

        expect($sql)->toBe(
            'WITH financial_data AS (SELECT * FROM transactions WHERE status = ?) SELECT SUM(amount) FROM financial_data'
        );
        expect($query->getBindings())->toBe(['cleared']);
    });

    test('compiles existence check subqueries wrapping CTE definitions', function () {
        $query = MockQueryBuilder::table('users')
            ->with('users', function ($q) {
                return $q->from('customer_records')->where('active', 1);
            })
        ;

        $sql = $query->buildExistsQuery();

        expect($sql)->toBe(
            'SELECT EXISTS(WITH users AS (SELECT * FROM customer_records WHERE active = ?) SELECT 1 FROM users)'
        );
        expect($query->getBindings())->toBe([1]);
    });

    test('guarantees complete immutability of the query builder state during CTE attachment', function () {
        $baseQuery = MockQueryBuilder::table('users')->where('status', 'active');

        $cteQuery = $baseQuery->with('high_spenders', function ($q) {
            return $q->from('orders')->where('total', '>', 1000);
        });

        expect($baseQuery->toSql())->toBe('SELECT * FROM users WHERE status = ?');
        expect($baseQuery->getBindings())->toBe(['active']);

        expect($cteQuery->toSql())->toBe(
            'WITH high_spenders AS (SELECT * FROM orders WHERE total > ?) SELECT * FROM users WHERE status = ?'
        );

        expect($cteQuery->getBindings())->toBe([1000, 'active']);
    });
});

describe('QueryCte Primitives - Advanced & Edge Cases', function () {
    test('compiles CTE with explicit multi-column aliases in declaration', function () {
        $query = MockQueryBuilder::table('user_summaries')
            ->with('user_summaries(uid, display_name)', function ($q) {
                return $q->from('users')->select('id', 'name');
            })
        ;

        expect($query->toSql())->toBe(
            'WITH user_summaries(uid, display_name) AS (SELECT id, name FROM users) SELECT * FROM user_summaries'
        );
    });

    test('compiles nested CTEs where a subsequent CTE references a previous one', function () {
        $query = MockQueryBuilder::table('final_selection')
            ->with('raw_users', function ($q) {
                return $q->from('users')->where('status', 'active');
            })
            ->with('filtered_users', function ($q) {
                return $q->from('raw_users')->where('age', '>=', 18);
            })
        ;

        expect($query->toSql())->toBe(
            'WITH raw_users AS (SELECT * FROM users WHERE status = ?), filtered_users AS (SELECT * FROM raw_users WHERE age >= ?) SELECT * FROM final_selection'
        );
        expect($query->getBindings())->toBe(['active', 18]);
    });

    test('compiles query joining a CTE to a standard table with parameter alignment', function () {
        $query = MockQueryBuilder::table('users')
            ->select('users.name', 'top_orders.total')
            ->with('top_orders', function ($q) {
                return $q->from('orders')->where('total', '>', 500);
            })
            ->join('top_orders', 'users.id = top_orders.user_id')
            ->where('users.status', 'active')
        ;

        expect($query->toSql())->toBe(
            'WITH top_orders AS (SELECT * FROM orders WHERE total > ?) SELECT users.name, top_orders.total FROM users INNER JOIN top_orders ON users.id = top_orders.user_id WHERE users.status = ?'
        );

        expect($query->getBindings())->toBe([500, 'active']);
    });

    test('compiles CTE containing complex sub-selects, joins, and where bindings', function () {
        $query = MockQueryBuilder::table('cte_table')
            ->select('col1')
            ->with('cte_table', function ($q) {
                return $q->from('orders')
                    ->selectRaw('SUM(amount * ?)', [1.1])
                    ->join('users', 'orders.user_id = users.id')
                    ->where('orders.status', 'completed')
                    ->whereIn('users.role', ['admin', 'manager'])
                ;
            })
            ->where('outer_col', 'some_val')
        ;

        expect($query->getBindings())->toBe([1.1, 'completed', 'admin', 'manager', 'some_val']);
    });

    test('applies pagination, limit, and offset correctly on CTE-based query', function () {
        $query = MockQueryBuilder::table('large_cte')
            ->with('large_cte', function ($q) {
                return $q->from('logs')->where('severity', 'error');
            })
            ->limit(50, 100);

        expect($query->toSql())->toBe(
            'WITH large_cte AS (SELECT * FROM logs WHERE severity = ?) SELECT * FROM large_cte LIMIT 50 OFFSET 100'
        );
    });
});
