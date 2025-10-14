<?php

use Tests\MockQueryBuilder;

describe('Multiple Join Scenarios', function () {
    test('joins with where conditions', function () {
        $query = MockQueryBuilder::table('users')
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
        $query = MockQueryBuilder::table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id')
            ->innerJoin('orders', 'users.id = orders.user_id')
            ->rightJoin('payments', 'orders.id = payments.order_id');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LEFT JOIN');
        expect($sql)->toContain('INNER JOIN');
        expect($sql)->toContain('RIGHT JOIN');
    });
});