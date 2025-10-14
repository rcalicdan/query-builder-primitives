<?php

use Tests\MockQueryBuilder;

describe('Complex Nested Conditions', function () {
    test('deeply nested where groups', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereNested(function ($q) {
                return $q
                    ->where('role', 'admin')
                    ->orWhereNested(function ($q2) {
                        return $q2
                            ->where('role', 'moderator')
                            ->where('verified', true);
                    });
            });
        
        $sql = $query->toSql();
        expect($sql)->toContain('status = ?');
        expect($query->getBindings())->toHaveCount(4);
    });

    test('multiple nested groups with AND', function () {
        $query = MockQueryBuilder::table('users')
            ->whereNested(function ($q) {
                return $q->where('age', '>', 18)->where('age', '<', 65);
            })
            ->whereNested(function ($q) {
                return $q->where('country', 'US')->orWhere('country', 'CA');
            });
        
        expect($query->getBindings())->toBe([18, 65, 'US', 'CA']);
    });

    test('combines whereIn with nested conditions', function () {
        $query = MockQueryBuilder::table('users')
            ->whereIn('status', ['active', 'pending'])
            ->whereNested(function ($q) {
                return $q->where('age', '>', 18)->orWhere('verified', true);
            });
        
        $sql = $query->toSql();
        expect($sql)->toContain('IN (?, ?)');
        expect($query->getBindings())->toBe(['active', 'pending', 18, true]);
    });
});