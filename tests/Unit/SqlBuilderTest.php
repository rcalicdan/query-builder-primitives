<?php

use Tests\MockQueryBuilder;

describe('SqlBuilder', function () {
    test('builds select query', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id, name')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10)
        ;

        expect($query->toSql())->toBe('SELECT id, name FROM users WHERE status = ? ORDER BY name ASC LIMIT 10');
    });

    test('builds complex query with joins and groups', function () {
        $query = MockQueryBuilder::table('orders')
            ->select('user_id, COUNT(*) as order_count')
            ->leftJoin('users', 'orders.user_id = users.id')
            ->where('orders.status', 'completed')
            ->groupBy('user_id')
            ->having('COUNT(*)', '>', 5)
            ->orderBy('order_count', 'DESC')
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('SELECT user_id, COUNT(*) as order_count');
        expect($sql)->toContain('LEFT JOIN users');
        expect($sql)->toContain('WHERE orders.status = ?');
        expect($sql)->toContain('GROUP BY user_id');
        expect($sql)->toContain('HAVING COUNT(*) > ?');
    });

    test('combines and and or conditions correctly', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->where('verified', true)
            ->orWhere('role', 'admin')
        ;

        expect($query->toSql())->toContain('WHERE (status = ? AND verified = ?) OR role = ?');
    });
});
