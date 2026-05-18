<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('Raw SQL Edge Cases', function () {
    test('whereRaw with complex conditions', function () {
        $query = MockQueryBuilder::table('users')
            ->whereRaw('DATE(created_at) = CURDATE()')
            ->whereRaw('YEAR(created_at) = ?', [2024])
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('DATE(created_at) = CURDATE()');
        expect($query->getBindings())->toBe([2024]);
    });

    test('mix whereRaw with regular where', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereRaw('age > ?', [18])
            ->where('verified', true)
        ;

        expect($query->getBindings())->toBe(['active', 18, true]);
    });

    test('orWhereRaw after where clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereRaw('role = ? AND verified = ?', ['admin', true])
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('OR');
        expect($query->getBindings())->toBe(['active', 'admin', true]);
    });

    test('whereRaw rejects named bindings', function () {
        expect(fn () => MockQueryBuilder::table('users')->whereRaw('status = :status', ['status' => 'active']))
            ->toThrow(InvalidArgumentException::class, 'Query builder primitives only support positional bindings')
        ;
    });

    test('selectRaw rejects named bindings', function () {
        expect(fn () => MockQueryBuilder::table('users')->selectRaw('SUM(price * :tax)', ['tax' => 1.2]))
            ->toThrow(InvalidArgumentException::class, 'Query builder primitives only support positional bindings')
        ;
    });

    test('havingRaw rejects named bindings', function () {
        expect(fn () => MockQueryBuilder::table('users')->havingRaw('SUM(total) > :min', ['min' => 100]))
            ->toThrow(InvalidArgumentException::class, 'Query builder primitives only support positional bindings')
        ;
    });

    test('allows postgres positional bindings ($n) across all raw methods', function () {
        $query = MockQueryBuilder::table('users')
            ->selectRaw('SUM(price * $1) as total_with_tax', [1.2])
            ->whereRaw('status = $2', ['active'])
            ->havingRaw('COUNT(id) > $3', [5])
        ;

        $sql = $query->toSql();

        expect($sql)->toBe('SELECT SUM(price * $1) as total_with_tax FROM users WHERE status = $2 HAVING COUNT(id) > $3');

        expect($query->getBindings())->toBe([1.2, 'active', 5]);
    });

    test('mixes standard bindings (?) and postgres bindings ($n) safely', function () {
        $query = MockQueryBuilder::table('users')
            ->where('role', 'admin')
            ->whereRaw('age > $2', [18])
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('role = ?');
        expect($sql)->toContain('age > $2');
        expect($query->getBindings())->toBe(['admin', 18]);
    });
});
