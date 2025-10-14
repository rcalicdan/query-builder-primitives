<?php

use Tests\MockQueryBuilder;

describe('WhereNull Edge Cases', function () {
    test('multiple whereNull clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->whereNull('deleted_at')
            ->whereNull('banned_at');
        
        $sql = $query->toSql();
        expect($sql)->toContain('deleted_at IS NULL');
        expect($sql)->toContain('banned_at IS NULL');
    });

    test('whereNull and whereNotNull together', function () {
        $query = MockQueryBuilder::table('users')
            ->whereNull('deleted_at')
            ->whereNotNull('email_verified_at');
        
        $sql = $query->toSql();
        expect($sql)->toContain('IS NULL');
        expect($sql)->toContain('IS NOT NULL');
    });
});