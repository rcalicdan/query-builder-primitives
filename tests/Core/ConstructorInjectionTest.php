<?php 

declare(strict_types=1);

use Tests\Fixtures\CustomConstructorBuilder;
use Tests\Fixtures\DummyConnection;

describe('Constructor Injection via protected newQuery', function () {
    
    test('whereGroup safely passes constructor dependencies to sub-queries', function () {
        $connection = new DummyConnection();
        $qb = new CustomConstructorBuilder($connection);

        $query = $qb->from('users')->whereGroup(function ($q) {
            expect($q)->toBeInstanceOf(CustomConstructorBuilder::class);
            expect($q->getConnection())->toBeInstanceOf(DummyConnection::class);
            
            return $q->where('status', 'active');
        });

        expect($query->toSql())->toContain('WHERE (status = ?)');
    });

    test('whereExists safely passes constructor dependencies to sub-queries', function () {
        $connection = new DummyConnection();
        $qb = new CustomConstructorBuilder($connection);

        $query = $qb->from('users')->whereExists(function ($q) {
            expect($q)->toBeInstanceOf(CustomConstructorBuilder::class);
            expect($q->getConnection())->toBeInstanceOf(DummyConnection::class);
            
            return $q->from('orders')->whereColumn('orders.user_id', 'users.id');
        });

        expect($query->toSql())->toContain('WHERE EXISTS (SELECT * FROM orders WHERE orders.user_id = users.id)');
    });

    test('union safely passes constructor dependencies to sub-queries', function () {
        $connection = new DummyConnection();
        $qb = new CustomConstructorBuilder($connection);

        $query = $qb->from('users')
            ->select('id')
            ->union(function ($q) {
                expect($q)->toBeInstanceOf(CustomConstructorBuilder::class);
                expect($q->getConnection())->toBeInstanceOf(DummyConnection::class);
                
                return $q->from('archived_users')->select('id');
            });

        expect($query->toSql())->toBe('SELECT id FROM users UNION SELECT id FROM archived_users');
    });

    test('whereSub safely passes constructor dependencies to sub-queries', function () {
        $connection = new DummyConnection();
        $qb = new CustomConstructorBuilder($connection);

        $query = $qb->from('users')
            ->whereSub('total_orders', '>', function ($q) {
                expect($q)->toBeInstanceOf(CustomConstructorBuilder::class);
                expect($q->getConnection())->toBeInstanceOf(DummyConnection::class);
                
                return $q->from('orders')->selectRaw('COUNT(*)');
            });

        expect($query->toSql())->toBe('SELECT * FROM users WHERE total_orders > (SELECT COUNT(*) FROM orders)');
    });
});