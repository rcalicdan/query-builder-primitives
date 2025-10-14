<?php

use Tests\MockQueryBuilder;

describe('QueryGrouping', function () {
    test('adds group by', function () {
        $query = MockQueryBuilder::table('orders')
            ->groupBy('status');
        
        expect($query->toSql())->toContain('GROUP BY status');
    });

    test('adds multiple group by columns', function () {
        $query = MockQueryBuilder::table('orders')
            ->groupBy(['status', 'user_id']);
        
        expect($query->toSql())->toContain('GROUP BY status, user_id');
    });

    test('adds group by from string', function () {
        $query = MockQueryBuilder::table('orders')
            ->groupBy('status, user_id');
        
        expect($query->toSql())->toContain('GROUP BY status, user_id');
    });

    test('adds order by', function () {
        $query = MockQueryBuilder::table('users')
            ->orderBy('created_at', 'DESC');
        
        expect($query->toSql())->toContain('ORDER BY created_at DESC');
    });

    test('adds order by asc', function () {
        $query = MockQueryBuilder::table('users')
            ->orderByAsc('name');
        
        expect($query->toSql())->toContain('ORDER BY name ASC');
    });

    test('adds order by desc', function () {
        $query = MockQueryBuilder::table('users')
            ->orderByDesc('created_at');
        
        expect($query->toSql())->toContain('ORDER BY created_at DESC');
    });

    test('adds limit', function () {
        $query = MockQueryBuilder::table('users')
            ->limit(10);
        
        expect($query->toSql())->toContain('LIMIT 10');
    });

    test('adds limit with offset', function () {
        $query = MockQueryBuilder::table('users')
            ->limit(10, 20);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 20');
    });

    test('adds offset', function () {
        $query = MockQueryBuilder::table('users')
            ->limit(10)
            ->offset(5);
        
        expect($query->toSql())->toContain('OFFSET 5');
    });

    test('paginates results', function () {
        $query = MockQueryBuilder::table('users')
            ->paginate(2, 15);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 15');
        expect($sql)->toContain('OFFSET 15');
    });

    test('paginates first page', function () {
        $query = MockQueryBuilder::table('users')
            ->paginate(1, 10);
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 10');
        expect($sql)->toContain('OFFSET 0');
    });
});