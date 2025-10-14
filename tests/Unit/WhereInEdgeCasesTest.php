<?php

use Tests\MockQueryBuilder;

describe('WhereIn Edge Cases', function () {
    test('whereIn with single value', function () {
        $query = MockQueryBuilder::table('users')
            ->whereIn('id', [1]);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id IN (?)');
        expect($query->getBindings())->toBe([1]);
    });

    test('whereIn with large array', function () {
        $values = range(1, 1000);
        $query = MockQueryBuilder::table('users')
            ->whereIn('id', $values);
        
        expect($query->getBindings())->toHaveCount(1000);
        expect(substr_count($query->toSql(), '?'))->toBe(1000);
    });

    test('multiple whereIn clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereIn('role', ['admin', 'moderator']);
        
        expect($query->getBindings())->toBe(['active', 'pending', 'admin', 'moderator']);
    });

    test('whereIn and whereNotIn together', function () {
        $query = MockQueryBuilder::table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereNotIn('role', ['banned', 'suspended']);
        
        $sql = $query->toSql();
        expect($sql)->toContain('IN (?, ?)');
        expect($sql)->toContain('NOT IN (?, ?)');
    });
});