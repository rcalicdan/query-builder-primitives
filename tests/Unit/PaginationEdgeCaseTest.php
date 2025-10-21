<?php

use Tests\MockQueryBuilder;

describe('Pagination Edge Cases', function () {
    test('paginate with large page numbers', function () {
        $query = MockQueryBuilder::table('users')
            ->forPage(100, 50)
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 50');
        expect($sql)->toContain('OFFSET 4950');
    });

    test('paginate with zero per page throws or handles gracefully', function () {
        $query = MockQueryBuilder::table('users')
            ->forPage(1, 0)
        ;

        expect($query->toSql())->toContain('LIMIT 0');
    });

    test('limit without offset', function () {
        $query = MockQueryBuilder::table('users')
            ->limit(25)
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('LIMIT 25');
        expect($sql)->not->toContain('OFFSET');
    });
});
