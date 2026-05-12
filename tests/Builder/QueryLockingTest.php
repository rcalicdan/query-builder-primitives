<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryLocking', function () {
    describe('MySQL lock modes', function () {
        test('lockForUpdate appends FOR UPDATE', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->where('id', 1)
                ->lockForUpdate()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        });

        test('lockForShare uses LOCK IN SHARE MODE on MySQL', function () {
            $sql = MockQueryBuilder::table('inventory')
                ->setDriver('mysql')
                ->where('product_id', 42)
                ->lockForShare()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM inventory WHERE product_id = ? LOCK IN SHARE MODE');
            expect($sql)->not->toContain('FOR SHARE');
        });

        test('lockForUpdate with noWait appends NOWAIT on MySQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->noWait()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE status = ? FOR UPDATE NOWAIT');
        });

        test('lockForShare with noWait throws on MySQL', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('mysql')
                    ->lockForShare()
                    ->noWait()
                    ->toSql()
            )->toThrow(LogicException::class, 'LOCK IN SHARE MODE');
        });

        test('lockForShare with skipLocked throws on MySQL', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('mysql')
                    ->lockForShare()
                    ->skipLocked()
                    ->toSql()
            )->toThrow(LogicException::class, 'LOCK IN SHARE MODE');
        });
    });

    describe('PostgreSQL lock modes', function () {
        test('lockForUpdate appends FOR UPDATE on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->where('id', 1)
                ->lockForUpdate()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE id = ? FOR UPDATE');
        });

        test('lockForShare appends FOR SHARE on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('inventory')
                ->setDriver('pgsql')
                ->where('product_id', 42)
                ->lockForShare()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM inventory WHERE product_id = ? FOR SHARE');
            expect($sql)->not->toContain('LOCK IN SHARE MODE');
        });

        test('lockForUpdate with noWait on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->where('id', 1)
                ->lockForUpdate()
                ->noWait()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE id = ? FOR UPDATE NOWAIT');
        });

        test('lockForShare with noWait on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->lockForShare()
                ->noWait()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders FOR SHARE NOWAIT');
        });

        test('lockForUpdate with skipLocked on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('jobs')
                ->setDriver('pgsql')
                ->where('status', 'pending')
                ->limit(1)
                ->lockForUpdate()
                ->skipLocked()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM jobs WHERE status = ? LIMIT 1 FOR UPDATE SKIP LOCKED');
        });

        test('lockForShare with skipLocked on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('jobs')
                ->setDriver('pgsql')
                ->lockForShare()
                ->skipLocked()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM jobs FOR SHARE SKIP LOCKED');
        });
    });

    describe('PostgreSQL OF clause', function () {
        test('lockOf with a single table', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->join('users', 'orders.user_id = users.id')
                ->lockForUpdate()
                ->lockOf('orders')
                ->toSql()
            ;

            expect($sql)->toContain('FOR UPDATE OF orders');
            expect($sql)->not->toContain('FOR UPDATE OF users');
        });

        test('lockOf with multiple tables', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->join('items', 'orders.id = items.order_id')
                ->lockForUpdate()
                ->lockOf(['orders', 'items'])
                ->toSql()
            ;

            expect($sql)->toContain('FOR UPDATE OF orders, items');
        });

        test('lockOf combined with noWait on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->join('users', 'orders.user_id = users.id')
                ->lockForUpdate()
                ->lockOf('orders')
                ->noWait()
                ->toSql()
            ;

            expect($sql)->toEndWith('FOR UPDATE OF orders NOWAIT');
        });

        test('lockOf combined with skipLocked on PostgreSQL', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->lockForUpdate()
                ->lockOf('orders')
                ->skipLocked()
                ->toSql()
            ;

            expect($sql)->toEndWith('FOR UPDATE OF orders SKIP LOCKED');
        });
    });

    describe('lock clause position in SQL', function () {
        test('lock clause appears after LIMIT and OFFSET', function () {
            $sql = MockQueryBuilder::table('jobs')
                ->setDriver('mysql')
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->limit(10, 20)
                ->lockForUpdate()
                ->toSql()
            ;

            expect($sql)->toBe(
                'SELECT * FROM jobs WHERE status = ? ORDER BY created_at ASC LIMIT 10 OFFSET 20 FOR UPDATE'
            );
        });

        test('lock clause appears after ORDER BY', function () {
            $sql = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->orderBy('name')
                ->lockForShare()
                ->toSql()
            ;

            $orderByPos = strpos($sql, 'ORDER BY');
            $lockPos = strpos($sql, 'FOR SHARE');

            expect($lockPos)->toBeGreaterThan($orderByPos);
        });

        test('lock clause appears after HAVING', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->groupBy('user_id')
                ->having('COUNT(*)', '>', 5)
                ->lockForUpdate()
                ->toSql()
            ;

            $havingPos = strpos($sql, 'HAVING');
            $lockPos = strpos($sql, 'FOR UPDATE');

            expect($lockPos)->toBeGreaterThan($havingPos);
        });
    });

    describe('withoutLock', function () {
        test('withoutLock removes the lock clause', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->where('id', 1)
                ->lockForUpdate()
                ->withoutLock()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE id = ?');
            expect($sql)->not->toContain('FOR UPDATE');
        });

        test('withoutLock also clears modifier and OF', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->lockForUpdate()
                ->lockOf('orders')
                ->noWait()
                ->withoutLock()
                ->toSql()
            ;

            expect($sql)->not->toContain('FOR UPDATE');
            expect($sql)->not->toContain('OF');
            expect($sql)->not->toContain('NOWAIT');
        });

        test('withoutLock on a builder with no lock is a no-op', function () {
            $sql = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->where('id', 1)
                ->withoutLock()
                ->toSql()
            ;

            expect($sql)->toBe('SELECT * FROM orders WHERE id = ?');
        });
    });

    describe('immutability', function () {
        test('lockForUpdate does not mutate the original instance', function () {
            $base = MockQueryBuilder::table('orders')->setDriver('mysql');
            $locked = $base->lockForUpdate();

            expect($base->toSql())->not->toContain('FOR UPDATE');
            expect($locked->toSql())->toContain('FOR UPDATE');
        });

        test('noWait does not mutate the previous instance', function () {
            $base = MockQueryBuilder::table('orders')->setDriver('pgsql')->lockForUpdate();
            $nowait = $base->noWait();

            expect($base->toSql())->not->toContain('NOWAIT');
            expect($nowait->toSql())->toContain('NOWAIT');
        });
    });

    describe('SQLite throws on any lock', function () {
        test('lockForUpdate throws LogicException on SQLite', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('sqlite')
                    ->lockForUpdate()
                    ->toSql()
            )->toThrow(LogicException::class, 'SQLite does not support row-level locking');
        });

        test('lockForShare throws LogicException on SQLite', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('sqlite')
                    ->lockForShare()
                    ->toSql()
            )->toThrow(LogicException::class, 'SQLite does not support row-level locking');
        });
    });

    describe('MySQL rejects OF clause', function () {
        test('lockOf throws LogicException on MySQL', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('mysql')
                    ->lockForUpdate()
                    ->lockOf('orders')
            )->toThrow(LogicException::class, 'MySQL does not support the OF clause');
        });
    });

    describe('modifier guards without a lock mode', function () {
        test('noWait without lock mode throws LogicException', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('mysql')
                    ->noWait()
            )->toThrow(LogicException::class, 'NOWAIT');
        });

        test('skipLocked without lock mode throws LogicException', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('pgsql')
                    ->skipLocked()
            )->toThrow(LogicException::class, 'SKIP LOCKED');
        });

        test('lockOf without lock mode throws LogicException', function () {
            expect(
                fn () => MockQueryBuilder::table('orders')
                    ->setDriver('pgsql')
                    ->lockOf('orders')
            )->toThrow(LogicException::class, 'OF clause');
        });
    });
});
