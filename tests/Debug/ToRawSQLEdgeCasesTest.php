<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('toRawSql Edge Cases', function () {
    test('toRawSql with null values', function () {
        $query = MockQueryBuilder::table('users')
            ->where('deleted_at', null)
            ->where('name', 'John')
        ;

        $rawSql = $query->toRawSql();
        expect($rawSql)->toContain('NULL');
        expect($rawSql)->toContain("'John'");
    });

    test('toRawSql with boolean values', function () {
        $query = MockQueryBuilder::table('users')
            ->where('is_active', true)
            ->where('is_deleted', false)
        ;

        $rawSql = $query->toRawSql();
        expect($rawSql)->toContain('1');
        expect($rawSql)->toContain('0');
    });

    test('toRawSql with array values in whereIn', function () {
        $query = MockQueryBuilder::table('users')
            ->whereIn('id', [1, 2, 3, 4, 5])
        ;

        $rawSql = $query->toRawSql();
        expect($rawSql)->not->toContain('?');
        expect($rawSql)->toContain('1');
        expect($rawSql)->toContain('5');
    });

    test('toRawSql replaces postgres $1 without affecting $10', function () {
        $builder = MockQueryBuilder::table('users');

        $query = $builder->whereRaw('col1 = $1', [1])
            ->whereRaw('col2 = $2', [2])
            ->whereRaw('col3 = $3', [3])
            ->whereRaw('col4 = $4', [4])
            ->whereRaw('col5 = $5', [5])
            ->whereRaw('col6 = $6', [6])
            ->whereRaw('col7 = $7', [7])
            ->whereRaw('col8 = $8', [8])
            ->whereRaw('col9 = $9', [9])
            ->whereRaw('col10 = $10', [100])
        ;

        $rawSql = $query->toRawSql();

        expect($rawSql)->toContain('col1 = 1');
        expect($rawSql)->toContain('col10 = 100');
        expect($rawSql)->not->toContain('$1');
    });

    test('toRawSql handles values containing dollar signs safely', function () {
        $query = MockQueryBuilder::table('products')
            ->where('price_label', '$100.00')
            ->where('discount_code', '$2-OFF')
        ;

        $rawSql = $query->toRawSql();

        expect($rawSql)->toContain("price_label = '$100.00'");
        expect($rawSql)->toContain("discount_code = '$2-OFF'");
    });

    test('toRawSql replaces all occurrences of the same postgres binding', function () {
        $query = MockQueryBuilder::table('events')
            ->whereRaw('start_date > $1 OR end_date > $1', ['2024-01-01'])
        ;

        $rawSql = $query->toRawSql();

        expect($rawSql)->toBe("SELECT * FROM events WHERE start_date > '2024-01-01' OR end_date > '2024-01-01'");
    });

    test('toRawSql interpolates selectRaw bindings correctly', function () {
        $query = MockQueryBuilder::table('orders')
            ->selectRaw('SUM(price * ?) as total', [1.2])
            ->where('status', 'completed')
        ;

        $rawSql = $query->toRawSql();

        expect($rawSql)->toBe("SELECT SUM(price * 1.2) as total FROM orders WHERE status = 'completed'");
    });

    test('toRawSql truncates very long strings in display', function () {
        $longString = str_repeat('a', 150);
        $query = MockQueryBuilder::table('posts')
            ->where('content', $longString)
        ;

        $rawSql = $query->toRawSql();

        expect(strlen($rawSql))->toBeLessThan(150);
        expect($rawSql)->toContain('...');
    });

    test('toRawSql handles complex mixed bindings across all clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->selectRaw('COUNT(*) > ?', [5])
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'editor'])
            ->havingRaw('MAX(age) > ?', [21])
        ;

        $rawSql = $query->toRawSql();

        expect($rawSql)->toContain('SELECT COUNT(*) > 5');
        expect($rawSql)->toContain("WHERE status = 'active'");
        expect($rawSql)->toContain("role IN ('admin', 'editor')");
        expect($rawSql)->toContain('HAVING MAX(age) > 21');
    });
});
