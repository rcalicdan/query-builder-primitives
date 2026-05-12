<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('WhereBetween Edge Cases', function () {
    test('throws exception if values array has fewer than 2 elements', function () {
        expect(fn () => MockQueryBuilder::table('events')->whereBetween('date', ['2024-01-01']))
            ->toThrow(InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
    });

    test('throws exception if values array has more than 2 elements', function () {
        expect(fn () => MockQueryBuilder::table('events')->whereBetween('date', ['2024-01-01', '2024-12-31', '2025-01-01']))
            ->toThrow(InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
    });

    test('throws exception if values array is empty', function () {
        expect(fn () => MockQueryBuilder::table('events')->whereBetween('date', []))
            ->toThrow(InvalidArgumentException::class, 'whereBetween requires exactly 2 values');
    });

    test('works properly with different data types (numbers and strings)', function () {
        $query = MockQueryBuilder::table('products')
            ->whereBetween('price', [10.50, 99.99])
            ->whereBetween('created_at', ['2024-01-01', '2024-12-31']);

        $sql = $query->toSql();
        expect($sql)->toContain('price BETWEEN ? AND ?');
        expect($sql)->toContain('created_at BETWEEN ? AND ?');
        expect($query->getBindings())->toBe([10.50, 99.99, '2024-01-01', '2024-12-31']);
    });

    test('mixed with other conditions maintains correct strict binding order', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereBetween('age', [18, 30])
            ->where('role', 'user');

        expect($query->getBindings())->toBe(['active', 18, 30, 'user']);
    });
});