<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryConditions Tests', function () {
    test('where with 3 arguments generates correct operator', function () {
        $query = MockQueryBuilder::table('users')
            ->where('age', '>=', 18)
            ->where('name', '!=', 'admin')
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE age >= ? AND name != ?');
        expect($query->getBindings())->toBe([18, 'admin']);
    });

    test('where with 2 arguments defaults to equals', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('orWhere adds OR condition with proper bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin')
            ->orWhere('age', '>', 65)
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE status = ? OR role = ? OR age > ?');
        expect($query->getBindings())->toBe(['active', 'admin', 65]);
    });

    test('having and havingRaw clauses generate correctly', function () {
        $query = MockQueryBuilder::table('orders')
            ->select('user_id, SUM(total) as grand_total')
            ->groupBy('user_id')
            ->having('grand_total', '>', 1000)
            ->havingRaw('COUNT(id) = ?', [5])
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('HAVING grand_total > ? AND COUNT(id) = ?');

        expect($query->getBindings())->toBe([1000, 5]);
    });

    test('resetWhere clears all condition state and bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereIn('id', [1, 2, 3])
            ->whereNull('deleted_at')
            ->having('score', '>', 10)
        ;

        expect($query->getBindings())->not->toBeEmpty();
        expect($query->toSql())->toContain('WHERE');

        $resetQuery = $query->resetWhere();

        expect($query)->not->toBe($resetQuery);
        expect($resetQuery->getBindings())->toBeEmpty();
        expect($resetQuery->toSql())->toBe('SELECT * FROM users');
    });

    test('whereColumn compares two columns without bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereColumn('created_at', 'updated_at')
            ->whereColumn('first_name', '!=', 'last_name')
        ;

        $sql = $query->toSql();

        expect($sql)->toContain('WHERE status = ? AND created_at = updated_at AND first_name != last_name');

        expect($query->getBindings())->toBe(['active']);
    });

    test('orWhereColumn wrapper generates correct OR clause without bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereColumn('is_admin', 'is_super_admin')
            ->orWhereColumn('login_count', '>', 'view_count')
        ;

        $sql = $query->toSql();

        expect($sql)->toContain('WHERE status = ? OR is_admin = is_super_admin OR login_count > view_count');
        expect($query->getBindings())->toBe(['active']);
    });

    test('whereColumn accepts boolean parameter dynamically', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereColumn('published_at', '<', 'expires_at', 'OR')
        ;

        $sql = $query->toSql();

        expect($sql)->toContain('WHERE status = ? OR published_at < expires_at');
        expect($query->getBindings())->toBe(['active']);
    });
});
