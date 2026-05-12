<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('DML Query Tests', function () {

    describe('Insert', function () {
        test('builds single insert query', function () {
            $builder = MockQueryBuilder::table('users');
            $sql = $builder->buildInsertQuery(['name' => 'John', 'email' => 'john@example.com']);

            expect($sql)->toBe('INSERT INTO users (name, email) VALUES (?, ?)');
        });

        test('builds batch insert query', function () {
            $builder = MockQueryBuilder::table('users');
            $sql = $builder->buildInsertBatchQuery([
                ['name' => 'John', 'email' => 'john@example.com'],
                ['name' => 'Jane', 'email' => 'jane@example.com'],
            ]);

            expect($sql)->toBe('INSERT INTO users (name, email) VALUES (?, ?), (?, ?)');
        });

        test('throws exception on invalid batch data format', function () {
            $builder = MockQueryBuilder::table('users');

            expect(fn () => $builder->buildInsertBatchQuery(['name' => 'John']))
                ->toThrow(InvalidArgumentException::class, 'Invalid data format for batch insert')
            ;
        });
    });

    describe('Update', function () {
        test('builds update query without where clause (affects all rows)', function () {
            $builder = MockQueryBuilder::table('users');
            $sql = $builder->buildUpdateQuery(['status' => 'inactive']);

            expect($sql)->toBe('UPDATE users SET status = ?');
        });

        test('builds update query with where clauses', function () {
            $builder = MockQueryBuilder::table('users')
                ->where('id', 1)
                ->where('role', 'admin')
            ;

            $sql = $builder->buildUpdateQuery(['status' => 'active', 'updated_at' => '2024-01-01']);

            expect($sql)->toBe('UPDATE users SET status = ?, updated_at = ? WHERE id = ? AND role = ?');
        });
    });

    describe('Delete', function () {
        test('builds delete query without where clause (truncates/deletes all)', function () {
            $builder = MockQueryBuilder::table('users');
            $sql = $builder->buildDeleteQuery();

            expect($sql)->toBe('DELETE FROM users');
        });

        test('builds delete query with where clauses', function () {
            $builder = MockQueryBuilder::table('users')
                ->where('status', 'banned')
                ->where('last_login', '<', '2023-01-01')
            ;

            $sql = $builder->buildDeleteQuery();

            expect($sql)->toBe('DELETE FROM users WHERE status = ? AND last_login < ?');
        });
    });

    describe('Upsert (MySQL)', function () {
        test('builds mysql upsert with default update columns', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('mysql');
            $data = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];

            $sql = $builder->buildUpsertQuery($data, 'id');

            expect($sql)->toBe('INSERT INTO users (id, email, name) VALUES (?, ?, ?) AS new ON DUPLICATE KEY UPDATE email = new.email, name = new.name');
        });

        test('builds mysql upsert with explicit update columns', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('mysql');
            $data = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];

            $sql = $builder->buildUpsertQuery($data, 'id', ['name']);

            expect($sql)->toBe('INSERT INTO users (id, email, name) VALUES (?, ?, ?) AS new ON DUPLICATE KEY UPDATE name = new.name');
        });

        test('builds mysql batch upsert', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('mysql');
            $data = [
                ['id' => 1, 'name' => 'Test 1'],
                ['id' => 2, 'name' => 'Test 2'],
            ];

            $sql = $builder->buildUpsertQuery($data, 'id');

            expect($sql)->toBe('INSERT INTO users (id, name) VALUES (?, ?), (?, ?) AS new ON DUPLICATE KEY UPDATE name = new.name');
        });
    });

    describe('Upsert (PostgreSQL)', function () {
        test('builds pgsql upsert with default update columns', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('pgsql');
            $data = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];

            $sql = $builder->buildUpsertQuery($data, 'id');

            expect($sql)->toBe('INSERT INTO users (id, email, name) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET email = EXCLUDED.email, name = EXCLUDED.name');
        });

        test('builds pgsql DO NOTHING when no columns to update', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('pgsql');
            $data = ['id' => 1, 'email' => 'test@test.com'];

            $sql = $builder->buildUpsertQuery($data, 'id', []);

            expect($sql)->toBe('INSERT INTO users (id, email) VALUES (?, ?) ON CONFLICT (id) DO NOTHING');
        });
    });

    describe('Upsert (SQLite)', function () {
        test('builds sqlite upsert with default update columns', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('sqlite');
            $data = ['id' => 1, 'email' => 'test@test.com', 'name' => 'Test'];

            $sql = $builder->buildUpsertQuery($data, 'id');

            expect($sql)->toBe('INSERT INTO users (id, email, name) VALUES (?, ?, ?) ON CONFLICT (id) DO UPDATE SET email = excluded.email, name = excluded.name');
        });

        test('builds sqlite DO NOTHING when no columns to update', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('sqlite');
            $data = ['id' => 1, 'email' => 'test@test.com'];

            $sql = $builder->buildUpsertQuery($data, ['id', 'email'], []);

            expect($sql)->toBe('INSERT INTO users (id, email) VALUES (?, ?) ON CONFLICT (id, email) DO NOTHING');
        });
    });

    describe('Upsert Edge Cases', function () {
        test('throws exception if data is empty', function () {
            $builder = MockQueryBuilder::table('users');

            expect(fn () => $builder->buildUpsertQuery([], 'id'))
                ->toThrow(InvalidArgumentException::class, 'Data cannot be empty for upsert')
            ;
        });

        test('throws exception if unique columns is empty', function () {
            $builder = MockQueryBuilder::table('users');

            expect(fn () => $builder->buildUpsertQuery(['id' => 1], []))
                ->toThrow(InvalidArgumentException::class, 'Unique columns must be specified for upsert')
            ;
        });

        test('throws exception for unsupported drivers', function () {
            $builder = MockQueryBuilder::table('users')->setDriver('oracle');

            expect(fn () => $builder->buildUpsertQuery(['id' => 1], 'id'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported driver: oracle.')
            ;
        });
    });
});
