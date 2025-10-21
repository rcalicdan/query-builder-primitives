<?php

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
});
