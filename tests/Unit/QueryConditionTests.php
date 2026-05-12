<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryConditions Tests', function () {
    test('where with 3 arguments generates correct operator', function () {
        $query = MockQueryBuilder::table('users')
            ->where('age', '>=', 18)
            ->where('name', '!=', 'admin');

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE age >= ? AND name != ?');
        expect($query->getBindings())->toBe([18, 'admin']);
    });

    test('where with 2 arguments defaults to equals', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active');

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('orWhere adds OR condition with proper bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhere('role', 'admin')
            ->orWhere('age', '>', 65);

        $sql = $query->toSql();
        expect($sql)->toContain('WHERE status = ? OR role = ? OR age > ?');
        expect($query->getBindings())->toBe(['active', 'admin', 65]);
    });

    test('having and havingRaw clauses generate correctly', function () {
        $query = MockQueryBuilder::table('orders')
            ->select('user_id, SUM(total) as grand_total')
            ->groupBy('user_id')
            ->having('grand_total', '>', 1000)
            ->havingRaw('COUNT(id) = ?', [5]);

        $sql = $query->toSql();
        expect($sql)->toContain('HAVING grand_total > ? AND COUNT(id) = ?');
        
        expect($query->getBindings())->toBe([1000, 5]);
    });

    test('resetWhere clears all condition state and bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereIn('id', [1, 2, 3])
            ->whereNull('deleted_at')
            ->having('score', '>', 10);

        expect($query->getBindings())->not->toBeEmpty();
        expect($query->toSql())->toContain('WHERE');

        $resetQuery = $query->resetWhere();

        expect($query)->not->toBe($resetQuery); 
        expect($resetQuery->getBindings())->toBeEmpty();
        expect($resetQuery->toSql())->toBe('SELECT * FROM users');
    });
});