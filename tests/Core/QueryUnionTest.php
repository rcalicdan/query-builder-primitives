<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryUnion', function () {
    test('adds a basic UNION query', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id', 'name')
            ->where('status', 'active')
            ->union(function ($q) {
                return $q->from('archived_users')
                         ->select('id', 'name')
                         ->where('status', 'banned')
                ;
            })
        ;

        $sql = $query->toSql();

        expect($sql)->toBe('SELECT id, name FROM users WHERE status = ? UNION SELECT id, name FROM archived_users WHERE status = ?');
        expect($query->getBindings())->toBe(['active', 'banned']);
    });

    test('adds a UNION ALL query', function () {
        $query = MockQueryBuilder::table('users')
            ->where('role', 'admin')
            ->unionAll(function ($q) {
                return $q->from('users')->where('role', 'moderator');
            })
        ;

        expect($query->toSql())->toContain('UNION ALL');
        expect($query->getBindings())->toBe(['admin', 'moderator']);
    });

    test('handles multiple unions', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->union(fn ($q) => $q->from('admins')->where('level', 1))
            ->unionAll(fn ($q) => $q->from('guests')->where('active', 1))
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('UNION SELECT');
        expect($sql)->toContain('UNION ALL SELECT');
        expect($query->getBindings())->toBe(['active', 1, 1]);
    });

    test('applies ORDER BY and LIMIT to the final unioned result', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id', 'created_at')
            ->where('status', 'active')
            ->union(function ($q) {
                return $q->from('archived_users')
                         ->select('id', 'created_at')
                         ->where('status', 'archived')
                ;
            })
            ->orderBy('created_at', 'DESC')
            ->limit(10)
        ;

        $sql = $query->toSql();

        // Ensure UNION happens BEFORE ORDER BY and LIMIT
        $unionPos = strpos($sql, 'UNION');
        $orderPos = strpos($sql, 'ORDER BY');
        $limitPos = strpos($sql, 'LIMIT');

        expect($unionPos)->toBeLessThan($orderPos);
        expect($orderPos)->toBeLessThan($limitPos);
        expect($query->getBindings())->toBe(['active', 'archived']);
    });

    test('throws exception if union query does not specify a table', function () {
        expect(fn () => MockQueryBuilder::table('users')->union(function ($q) {
            return $q->where('status', 'active');
        }))->toThrow(InvalidArgumentException::class, 'Union query must specify a table');
    });
});
