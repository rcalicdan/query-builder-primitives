<?php

declare(strict_types=1);

use Tests\Fixtures\BadConstructorBuilder;
use Tests\Fixtures\CustomConstructorBuilder;
use Tests\Fixtures\DummyConnection;

describe('Constructor Injection via protected newQuery', function () {

    describe('Happy Paths (Direct Subqueries)', function () {
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
                })
            ;

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
                })
            ;

            expect($query->toSql())->toBe('SELECT * FROM users WHERE total_orders > (SELECT COUNT(*) FROM orders)');
        });
    });

    describe('Edge Cases and Behaviors', function () {

        test('throws descriptive LogicException if developer forgets to override newQuery', function () {
            $connection = new DummyConnection();
            $qb = new BadConstructorBuilder($connection);

            expect(fn () => $qb->from('users')->whereGroup(fn ($q) => $q->where('id', 1)))
                ->toThrow(
                    LogicException::class,
                    'Cannot instantiate subquery builder for class "' . BadConstructorBuilder::class . '". Because your constructor requires arguments, you must override'
                )
            ;
        });

        test('dependencies survive deeply nested subqueries', function () {
            $connection = new DummyConnection();
            $qb = new CustomConstructorBuilder($connection);

            $query = $qb->from('users')->whereExists(function ($q1) {
                expect($q1->getConnection())->toBeInstanceOf(DummyConnection::class);

                return $q1->from('orders')
                    ->whereColumn('orders.user_id', 'users.id')
                    ->whereGroup(function ($q2) {
                        expect($q2->getConnection())->toBeInstanceOf(DummyConnection::class);

                        return $q2->where('orders.total', '>', 100)
                            ->orWhere('orders.status', 'vip')
                        ;
                    })
                ;
            });

            expect($query->toSql())->toContain('WHERE EXISTS');
            expect($query->toSql())->toContain('WHERE orders.user_id = users.id AND (orders.total > ? OR orders.status = ?)');
            expect($query->getBindings())->toBe([100, 'vip']);
        });

        test('subqueries automatically inherit the parent database driver', function () {
            $connection = new DummyConnection();

            $qb = (new CustomConstructorBuilder($connection))->setDriver('pgsql');

            $query = $qb->from('users')
                ->whereExists(function ($q) {
                    return $q->from('orders')
                        ->whereColumn('orders.user_id', 'users.id')
                        ->lockForShare()
                    ;
                })
            ;

            $sql = $query->toSql();

            expect($sql)->toContain('FOR SHARE');
            expect($sql)->not->toContain('LOCK IN SHARE MODE');
        });

        test('whereGroup subqueries safely inherit the parent table name', function () {
            $connection = new DummyConnection();
            $qb = new CustomConstructorBuilder($connection);

            $query = $qb->from('products')->whereGroup(function ($q) {
                return $q->where('price', '>', 50);
            });

            expect($query->toSql())->toBe('SELECT * FROM products WHERE (price > ?)');
            expect($query->getBindings())->toBe([50]);
        });
    });
});
