<?php

use Tests\MockQueryBuilder;

describe('QueryJoin', function () {
    test('adds inner join', function () {
        $query = MockQueryBuilder::table('users')
            ->join('orders', 'users.id = orders.user_id');
        
        expect($query->toSql())->toContain('FROM users INNER JOIN orders ON users.id = orders.user_id');
    });

    test('adds left join', function () {
        $query = MockQueryBuilder::table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id');
        
        expect($query->toSql())->toContain('LEFT JOIN profiles ON users.id = profiles.user_id');
    });

    test('adds right join', function () {
        $query = MockQueryBuilder::table('users')
            ->rightJoin('teams', 'users.team_id = teams.id');
        
        expect($query->toSql())->toContain('RIGHT JOIN teams ON users.team_id = teams.id');
    });

    test('adds cross join', function () {
        $query = MockQueryBuilder::table('users')
            ->crossJoin('settings');
        
        expect($query->toSql())->toContain('CROSS JOIN settings');
    });

    test('adds multiple joins', function () {
        $query = MockQueryBuilder::table('users')
            ->leftJoin('profiles', 'users.id = profiles.user_id')
            ->innerJoin('orders', 'users.id = orders.user_id');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LEFT JOIN profiles');
        expect($sql)->toContain('INNER JOIN orders');
    });
});