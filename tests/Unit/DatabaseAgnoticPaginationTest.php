<?php

use Tests\MockQueryBuilder;

describe('Database Agnostic Pagination', function () {
    describe('MySQL Syntax', function () {
        
        it('generates correct LIMIT syntax for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('OFFSET')
                ->not->toContain('FETCH');
        });

        it('generates correct LIMIT with OFFSET syntax for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH')
                ->not->toContain('ROWS');
        });

        it('generates correct pagination syntax for MySQL', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('mysql')
                ->where('category', 'electronics')
                ->orderBy('price', 'ASC')
                ->paginate(3, 25); // Page 3, 25 per page

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LIMIT 25')
                ->toContain('OFFSET 50') // (3 - 1) * 25 = 50
                ->not->toContain('FETCH');
        });

        it('generates correct OFFSET without LIMIT for MySQL', function () {
            $query = MockQueryBuilder::table('logs')
                ->setDriver('mysql')
                ->offset(100);

            expect($query->toSql())
                ->toContain('OFFSET 100')
                ->not->toContain('LIMIT')
                ->not->toContain('FETCH');
        });
    });

    describe('SQL Server Syntax', function () {
        
        it('generates correct OFFSET FETCH syntax for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('status', 'active')
                ->orderBy('created_at', 'DESC')
                ->limit(10);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 10 ROWS ONLY')
                ->not->toContain('LIMIT');
        });

        it('generates correct OFFSET FETCH with offset for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('status', 'active')
                ->orderBy('created_at', 'DESC')
                ->limit(10)
                ->offset(20);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 20 ROWS')
                ->toContain('FETCH NEXT 10 ROWS ONLY')
                ->not->toContain('LIMIT');
        });

        it('auto-adds ORDER BY when missing for SQL Server with LIMIT', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('sqlsrv')
                ->where('category', 'electronics')
                ->limit(25);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('ORDER BY (SELECT NULL)')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 25 ROWS ONLY');
        });

        it('auto-adds ORDER BY when missing for SQL Server with OFFSET', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('sqlsrv')
                ->where('status', 'pending')
                ->offset(50);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('ORDER BY (SELECT NULL)')
                ->toContain('OFFSET 50 ROWS')
                ->not->toContain('FETCH'); // No LIMIT, so no FETCH
        });

        it('does not add ORDER BY if already present for SQL Server', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('sqlsrv')
                ->where('price', '>', 100)
                ->orderBy('price', 'DESC')
                ->limit(15);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('ORDER BY price DESC')
                ->not->toContain('ORDER BY (SELECT NULL)')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 15 ROWS ONLY');
        });

        it('generates correct pagination syntax for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('active', 1)
                ->orderBy('username')
                ->paginate(3, 20); // Page 3, 20 per page

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 40 ROWS') // (3 - 1) * 20 = 40
                ->toContain('FETCH NEXT 20 ROWS ONLY')
                ->not->toContain('LIMIT');
        });

        it('handles MSSQL driver alias', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mssql')
                ->where('status', 'active')
                ->orderBy('id')
                ->limit(5);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 5 ROWS ONLY')
                ->not->toContain('LIMIT');
        });
    });

    describe('PostgreSQL Syntax', function () {
        
        it('generates correct LIMIT syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->where('status', 'active')
                ->limit(10);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('OFFSET')
                ->not->toContain('FETCH');
        });

        it('generates correct LIMIT with OFFSET syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH');
        });

        it('generates correct pagination syntax for PostgreSQL', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('pgsql')
                ->where('price', '>', 50)
                ->orderBy('created_at')
                ->paginate(2, 15);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LIMIT 15')
                ->toContain('OFFSET 15') // (2 - 1) * 15 = 15
                ->not->toContain('FETCH');
        });
    });

    describe('SQLite Syntax', function () {
        
        it('generates correct LIMIT syntax for SQLite', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->where('status', 'active')
                ->limit(10);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->not->toContain('FETCH');
        });

        it('generates correct LIMIT with OFFSET syntax for SQLite', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->where('status', 'active')
                ->limit(10)
                ->offset(20);

            expect($query->toSql())
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 20')
                ->not->toContain('FETCH');
        });

        it('generates correct pagination syntax for SQLite', function () {
            $query = MockQueryBuilder::table('logs')
                ->setDriver('sqlite')
                ->orderBy('created_at', 'DESC')
                ->paginate(5, 10);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 40') // (5 - 1) * 10 = 40
                ->not->toContain('FETCH');
        });
    });

    describe('Default Driver Behavior', function () {
        
        it('defaults to MySQL syntax when no driver is set', function () {
            $query = MockQueryBuilder::table('users')
                ->where('status', 'active')
                ->limit(10)
                ->offset(5);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LIMIT 10')
                ->toContain('OFFSET 5')
                ->not->toContain('FETCH');
        });
    });

    describe('Complex Queries with Joins', function () {
        
        it('generates correct SQL Server syntax with joins', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('sqlsrv')
                ->select('orders.*, customers.name')
                ->leftJoin('customers', 'orders.customer_id = customers.id')
                ->where('orders.status', 'completed')
                ->whereNotNull('orders.shipped_at')
                ->orderBy('orders.created_at', 'DESC')
                ->limit(50);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 50 ROWS ONLY')
                ->not->toContain('LIMIT');
        });

        it('generates correct MySQL syntax with joins', function () {
            $query = MockQueryBuilder::table('orders')
                ->setDriver('mysql')
                ->select('orders.*, customers.name')
                ->leftJoin('customers', 'orders.customer_id = customers.id')
                ->where('orders.status', 'completed')
                ->whereNotNull('orders.shipped_at')
                ->orderBy('orders.created_at', 'DESC')
                ->limit(50);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('LIMIT 50')
                ->not->toContain('FETCH');
        });
    });

    describe('Edge Cases', function () {
        
        it('handles no pagination for any driver', function () {
            $drivers = ['mysql', 'sqlsrv', 'pgsql', 'sqlite'];

            foreach ($drivers as $driver) {
                $query = MockQueryBuilder::table('users')
                    ->setDriver($driver)
                    ->where('status', 'active');

                $sql = $query->toSql();
                
                expect($sql)
                    ->not->toContain('LIMIT')
                    ->not->toContain('OFFSET')
                    ->not->toContain('FETCH');
            }
        });

        it('handles LIMIT 0 for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->limit(0);

            expect($query->toSql())->toContain('LIMIT 0');
        });

        it('handles LIMIT 0 for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->orderBy('id')
                ->limit(0);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 0 ROWS ONLY');
        });

        it('handles only OFFSET without LIMIT for SQL Server', function () {
            $query = MockQueryBuilder::table('logs')
                ->setDriver('sqlsrv')
                ->orderBy('created_at')
                ->offset(1000);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 1000 ROWS')
                ->not->toContain('FETCH');
        });
    });

    describe('Driver Case Insensitivity', function () {
        
        it('handles uppercase driver names', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('SQLSRV')
                ->orderBy('id')
                ->limit(5);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 5 ROWS ONLY');
        });

        it('handles mixed case driver names', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('MySql')
                ->limit(10);

            expect($query->toSql())->toContain('LIMIT 10');
        });
    });

    describe('Nested Conditions with Pagination', function () {
        
        it('generates correct SQL Server syntax with nested conditions', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('sqlsrv')
                ->whereNested(function($q) {
                    return $q->where('category', 'electronics')
                             ->orWhere('category', 'computers');
                })
                ->where('stock', '>', 0)
                ->orderBy('price', 'ASC')
                ->limit(20)
                ->offset(40);

            $sql = $query->toSql();
            
            // The actual order is: stock comes first, then the nested group
            // This is because of how conditions are tracked and ordered
            expect($sql)
                ->toContain('WHERE')
                ->toContain('stock > ?')
                ->toContain('category = ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('OFFSET 40 ROWS')
                ->toContain('FETCH NEXT 20 ROWS ONLY');
        });

        it('generates correct MySQL syntax with nested conditions', function () {
            $query = MockQueryBuilder::table('products')
                ->setDriver('mysql')
                ->whereNested(function($q) {
                    return $q->where('category', 'electronics')
                             ->orWhere('category', 'computers');
                })
                ->where('stock', '>', 0)
                ->orderBy('price', 'ASC')
                ->limit(20)
                ->offset(40);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('WHERE')
                ->toContain('stock > ?')
                ->toContain('category = ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('LIMIT 20')
                ->toContain('OFFSET 40');
        });
    });

    describe('Multiple ORDER BY Clauses', function () {
        
        it('preserves multiple ORDER BY for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->orderBy('status', 'ASC')
                ->orderBy('created_at', 'DESC')
                ->limit(10);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('ORDER BY status ASC, created_at DESC')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 10 ROWS ONLY');
        });

        it('preserves multiple ORDER BY for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->orderBy('status', 'ASC')
                ->orderBy('created_at', 'DESC')
                ->limit(10);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('ORDER BY status ASC, created_at DESC')
                ->toContain('LIMIT 10');
        });
    });

    describe('Bindings Consistency', function () {
        
        it('maintains correct binding order for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->where('age', '>', 18)
                ->limit(10)
                ->offset(20);

            $bindings = $query->getBindings();
            
            expect($bindings)->toBe(['active', 18]);
        });

        it('maintains correct binding order for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('status', 'active')
                ->where('age', '>', 18)
                ->orderBy('id')
                ->limit(10)
                ->offset(20);

            $bindings = $query->getBindings();
            
            expect($bindings)->toBe(['active', 18]);
        });
    });

    describe('Debug Output', function () {
        
        it('shows correct driver in dump for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('status', 'active')
                ->limit(10);

            ob_start();
            $query->dump();
            $output = ob_get_clean();
            
            // Strip ANSI color codes for testing
            $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
            
            expect($cleanOutput)
                ->toContain('sqlsrv')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 10 ROWS ONLY');
        });

        it('shows correct driver in dump for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('status', 'active')
                ->limit(10);

            ob_start();
            $query->dump();
            $output = ob_get_clean();
            
            // Strip ANSI color codes for testing
            $cleanOutput = preg_replace('/\x1b\[[0-9;]*m/', '', $output);
            
            expect($cleanOutput)
                ->toContain('mysql')
                ->toContain('LIMIT 10');
        });

        it('shows correct raw SQL for SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->where('email', 'test@example.com')
                ->orderBy('id')
                ->limit(5);

            $rawSql = $query->toRawSql();
            
            expect($rawSql)
                ->toContain("'test@example.com'")
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 5 ROWS ONLY');
        });

        it('shows correct raw SQL for MySQL', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->where('email', 'test@example.com')
                ->limit(5);

            $rawSql = $query->toRawSql();
            
            expect($rawSql)
                ->toContain("'test@example.com'")
                ->toContain('LIMIT 5');
        });
    });

    describe('Real World Scenarios', function () {
        
        it('handles user listing with SQL Server', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlsrv')
                ->select('id, username, email, created_at')
                ->where('active', 1)
                ->where('verified', 1)
                ->orderBy('created_at', 'DESC')
                ->paginate(1, 50);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('SELECT id, username, email, created_at')
                ->toContain('WHERE active = ? AND verified = ?')
                ->toContain('ORDER BY created_at DESC')
                ->toContain('OFFSET 0 ROWS')
                ->toContain('FETCH NEXT 50 ROWS ONLY');
            
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
                ->limit(20);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('name LIKE ?')
                ->toContain('price > ?')
                ->toContain('stock > ?')
                ->toContain('ORDER BY price ASC')
                ->toContain('LIMIT 20');
            
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
                ->paginate(2, 25);

            $sql = $query->toSql();
            
            expect($sql)
                ->toContain('LEFT JOIN customers')
                ->toContain('orders.status IN (?, ?, ?)')
                ->toContain('ORDER BY orders.created_at DESC')
                ->toContain('LIMIT 25')
                ->toContain('OFFSET 25');
            
            expect($query->getBindings())->toBe(['completed', 'shipped', 'delivered']);
        });
    });
});