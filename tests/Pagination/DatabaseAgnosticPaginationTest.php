<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('Database Agnostic Pagination', function () {
    describe('MySQL Syntax', function () {

        it('generates correct LIMIT syntax for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('OFFSET')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct LIMIT with OFFSET syntax for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH')
                ->not->toContain('ROWS')
            ;
        });

        it('generates correct pagination syntax for MySQL', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('mysql')
                ->where('category', 'electronics')
                ->orderBy('price', 'ASC')
                ->forPage(3, 25)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LIMIT 25')
                ->toContain('OFFSET 50') // (3 - 1) * 25 = 50
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct OFFSET without LIMIT for MySQL', function () {
            $query = MockQueryBuilder::table('logs')
                ->setDriver('mysql')
                ->offset(100)
            ;

            expect($query->toSql())
                ->toContain('OFFSET 100')
                ->not->toContain('LIMIT')
                ->not->toContain('FETCH')
            ;
        });
    });

    describe('PostgreSQL Syntax', function () {

        it('generates correct LIMIT syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->where('status', 'active')
                ->limit(10)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('OFFSET')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct LIMIT with OFFSET syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct pagination syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('pgsql')
                ->where('price', '>', 50)
                ->orderBy('created_at')
                ->forPage(2, 15)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LIMIT 15')
                ->toContain('OFFSET 15') // (2 - 1) * 15 = 15
                ->not->toContain('FETCH')
            ;
        });
    });

    describe('SQLite Syntax', function () {

        it('generates correct LIMIT syntax for SQLite', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->where('status', 'active')
                ->limit(10)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct LIMIT with OFFSET syntax for SQLite', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20)
            ;

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct pagination syntax for SQLite', function () {
            $query = MockQueryBuilder::table('logs')
                ->setDriver('sqlite')
                ->orderBy('created_at', 'DESC')
                ->forPage(5, 10)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 40') // (5 - 1) * 10 = 40
                ->not->toContain('FETCH')
            ;
        });
    });

    describe('Default Driver Behavior', function () {

        it('defaults to MySQL syntax when no driver is set', function () {
            $query = MockQueryBuilder::table('users')
                ->where('status', 'active')
                ->limit(10)
                ->offset(5)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 5')
                ->not->toContain('FETCH')
            ;
        });
    });

    describe('Complex Queries with Joins', function () {

        it('generates correct MySQL syntax with joins', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->select('orders.*, customers.name')
                ->leftJoin('customers', 'orders.customer_id = customers.id')
                ->where('orders.status', 'completed')
                ->whereNotNull('orders.shipped_at')
                ->orderBy('orders.created_at', 'DESC')
                ->limit(50)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('LIMIT 50')
                ->not->toContain('FETCH')
            ;
        });

        it('generates correct PostgreSQL syntax with joins', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->select('orders.*, customers.name')
                ->leftJoin('customers', 'orders.customer_id = customers.id')
                ->where('orders.status', 'completed')
                ->whereNotNull('orders.shipped_at')
                ->orderBy('orders.created_at', 'DESC')
                ->limit(50)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('LIMIT 50')
                ->not->toContain('FETCH')
            ;
        });
    });

    describe('Edge Cases', function () {

        it('handles no pagination for any driver', function () {
            $drivers = ['mysql', 'pgsql', 'sqlite'];

            foreach ($drivers as $driver) {
                $query = MockQueryBuilder::table('users')
                    ->setDriver($driver)
                    ->where('status', 'active')
                ;

                $sql = $query->toSql();

                expect($sql)
                    ->not->toContain('LIMIT')
                    ->not->toContain('OFFSET')
                    ->not->toContain('FETCH')
                ;
            }
        });

        it('handles LIMIT 0', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->limit(0)
            ;

            expect($query->toSql())->toContain('LIMIT 0');
        });
    });

    describe('Driver Case Insensitivity', function () {

        it('handles uppercase driver names', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('MYSQL')
                ->limit(5)
            ;

            expect($query->toSql())->toContain('LIMIT 5');
        });

        it('handles mixed case driver names', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('MySql')
                ->limit(10)
            ;

            expect($query->toSql())->toContain('LIMIT 10');
        });
    });

    describe('Nested Conditions with Pagination', function () {

        it('generates correct MySQL syntax with nested conditions', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('mysql')
                ->whereNested(function ($q) {
                    return $q->where('category', 'electronics')
                        ->orWhere('category', 'computers')
                    ;
                })
                ->where('stock', '>', 0)
                ->orderBy('price', 'ASC')
                ->limit(20)
                ->offset(40)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('WHERE')
                ->toContain('stock > ?')
                ->toContain('category = ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('LIMIT 20')
                ->toContain('OFFSET 40')
            ;
        });

        it('generates correct PostgreSQL syntax with nested conditions', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('pgsql')
                ->whereNested(function ($q) {
                    return $q->where('category', 'electronics')
                        ->orWhere('category', 'computers')
                    ;
                })
                ->where('stock', '>', 0)
                ->orderBy('price', 'ASC')
                ->limit(20)
                ->offset(40)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('WHERE')
                ->toContain('stock > ?')
                ->toContain('category = ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('LIMIT 20')
                ->toContain('OFFSET 40')
            ;
        });
    });

    describe('Multiple ORDER BY Clauses', function () {

        it('preserves multiple ORDER BY clauses', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->orderBy('status', 'ASC')
                ->orderBy('created_at', 'DESC')
                ->limit(10)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('ORDER BY status ASC, created_at DESC')
                ->toContain('LIMIT 10')
            ;
        });
    });

    describe('Bindings Consistency', function () {

        it('maintains correct binding order with pagination', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->where('age', '>', 18)
                ->limit(10)
                ->offset(20)
            ;

            expect($query->getBindings())->toBe(['active', 18]);
        });
    });

    describe('Debug Output', function () {

        it('shows correct driver in dump output', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10)
            ;

            ob_start();
            $query->dump();
            $output = ob_get_clean();

            $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);

            expect($cleanOutput)
                ->toContain('mysql')
                ->toContain('LIMIT 10')
            ;
        });

        it('shows correct raw SQL in debug output', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('email', 'test@example.com')
                ->limit(5)
            ;

            $rawSql = $query->toRawSql();

            expect($rawSql)
                ->toContain("'test@example.com'")
                ->toContain('LIMIT 5')
            ;
        });
    });

    describe('Real World Scenarios', function () {

        it('handles user listing with pagination', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->select('id, username, email, created_at')
                ->where('active', 1)
                ->where('verified', 1)
                ->orderBy('created_at', 'DESC')
                ->forPage(1, 50)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('SELECT id, username, email, created_at')
                ->toContain('WHERE active = ? AND verified = ?')
                ->toContain('ORDER BY created_at DESC')
                ->toContain('LIMIT 50')
                ->not->toContain('FETCH')
            ;

            expect($query->getBindings())->toBe([1, 1]);
        });

        it('handles product search with MySQL', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('mysql')
                ->select('*')
                ->like('name', 'laptop', 'both')
                ->where('price', '>', 500)
                ->where('stock', '>', 0)
                ->orderBy('price', 'ASC')
                ->limit(20)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('name LIKE ?')
                ->toContain('price > ?')
                ->toContain('stock > ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('LIMIT 20')
            ;

            $bindings = $query->getBindings();
            expect($bindings[0])->toBe('%laptop%');
            expect($bindings[1])->toBe(500);
            expect($bindings[2])->toBe(0);
        });

        it('handles order history with PostgreSQL', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('pgsql')
                ->select('orders.*, customers.name')
                ->leftJoin('customers', 'orders.customer_id = customers.id')
                ->whereIn('orders.status', ['completed', 'shipped', 'delivered'])
                ->orderBy('orders.created_at', 'DESC')
                ->forPage(2, 25)
            ;

            $sql = $query->toSql();

            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('orders.status IN (?, ?, ?)')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('LIMIT 25')
                ->toContain('OFFSET 25')
            ;

            expect($query->getBindings())->toBe(['completed', 'shipped', 'delivered']);
        });
    });
});
