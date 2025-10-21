<?php

use Tests\MockQueryBuilder;

describe('Edge Cases', function () {
    test('handles empty table name gracefully', function () {
        $query = (new MockQueryBuilder())->select('id');

        expect($query->toSql())->toBe('SELECT id FROM ');
    });

    test('handles very long column lists', function () {
        $columns = array_map(fn ($i) => "column{$i}", range(1, 50));
        $query = MockQueryBuilder::table('users')
            ->select($columns)
        ;

        expect($query->toSql())->toContain('column1');
        expect($query->toSql())->toContain('column50');
    });

    test('handles special characters in values', function () {
        $query = MockQueryBuilder::table('users')
            ->where('name', "O'Brien")
            ->where('description', 'Quote: "test"')
        ;

        expect($query->getBindings())->toBe(["O'Brien", 'Quote: "test"']);
    });

    test('handles null values in where clause', function () {
        $query = MockQueryBuilder::table('users')
            ->where('deleted_at', null)
        ;

        expect($query->getBindings())->toBe([null]);
    });

    test('handles boolean values', function () {
        $query = MockQueryBuilder::table('users')
            ->where('is_active', true)
            ->where('is_deleted', false)
        ;

        expect($query->getBindings())->toBe([true, false]);
    });

    test('handles zero and empty string', function () {
        $query = MockQueryBuilder::table('users')
            ->where('count', 0)
            ->where('name', '')
        ;

        expect($query->getBindings())->toBe([0, '']);
    });
});
