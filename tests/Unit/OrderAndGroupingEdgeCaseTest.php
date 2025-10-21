<?php

use Tests\MockQueryBuilder;

describe('Order and Grouping Edge Cases', function () {
    test('multiple order by clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->orderBy('status', 'DESC')
            ->orderBy('name', 'ASC')
            ->orderBy('created_at', 'DESC')
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('ORDER BY status DESC, name ASC, created_at DESC');
    });

    test('group by with having and order by', function () {
        $query = MockQueryBuilder::table('orders')
            ->select('user_id, COUNT(*) as total')
            ->groupBy('user_id')
            ->having('COUNT(*)', '>', 5)
            ->orderBy('total', 'DESC')
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('GROUP BY');
        expect($sql)->toContain('HAVING');
        expect($sql)->toContain('ORDER BY');
    });

    test('multiple group by with multiple having', function () {
        $query = MockQueryBuilder::table('orders')
            ->groupBy(['user_id', 'status'])
            ->having('COUNT(*)', '>', 5)
            ->having('SUM(total)', '>', 1000)
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('GROUP BY user_id, status');
        expect(substr_count($sql, 'HAVING'))->toBe(1);
    });
});
