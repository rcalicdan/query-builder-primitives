<?php

use Tests\MockQueryBuilder;

describe('Raw SQL Edge Cases', function () {
    test('whereRaw with complex conditions', function () {
        $query = MockQueryBuilder::table('users')
            ->whereRaw('DATE(created_at) = CURDATE()')
            ->whereRaw('YEAR(created_at) = ?', [2024]);
        
        $sql = $query->toSql();
        expect($sql)->toContain('DATE(created_at) = CURDATE()');
        expect($query->getBindings())->toBe([2024]);
    });

    test('mix whereRaw with regular where', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereRaw('age > ?', [18])
            ->where('verified', true);
        
        expect($query->getBindings())->toBe(['active', 18, true]);
    });

    test('orWhereRaw after where clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereRaw('role = ? AND verified = ?', ['admin', true]);
        
        $sql = $query->toSql();
        expect($sql)->toContain('OR');
        expect($query->getBindings())->toBe(['active', 'admin', true]);
    });
});