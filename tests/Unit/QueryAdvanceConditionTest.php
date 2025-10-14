<?php

use Tests\MockQueryBuilder;

describe('QueryAdvancedConditions', function () {
    test('adds where group', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereGroup(function ($query) {
                return $query
                    ->where('age', '>', 18)
                    ->where('country', 'US');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? AND (age > ? AND country = ?)');
        expect($query->getBindings())->toBe(['active', 18, 'US']);
    });

    test('adds where nested', function () {
        $query = MockQueryBuilder::table('users')
            ->whereNested(function ($query) {
                return $query
                    ->where('name', 'John')
                    ->orWhere('name', 'Jane');
            });
        
        expect($query->toSql())->toContain('WHERE (name = ? OR name = ?)');
        expect($query->getBindings())->toBe(['John', 'Jane']);
    });

    test('adds or where nested', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereNested(function ($query) {
                return $query
                    ->where('role', 'admin')
                    ->where('verified', true);
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR (role = ? AND verified = ?)');
        expect($query->getBindings())->toBe(['active', 'admin', true]);
    });

    test('adds where exists', function () {
        $query = MockQueryBuilder::table('users')
            ->whereExists(function ($query) {
                return $query
                    ->table('orders')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE EXISTS (SELECT * FROM orders');
    });

    test('throws exception for where exists without table', function () {
        expect(fn() => MockQueryBuilder::table('users')
            ->whereExists(function ($query) {
                return $query->where('id', 1);
            })
        )->toThrow(\InvalidArgumentException::class, 'Subquery must specify a table');
    });

    test('adds where not exists', function () {
        $query = MockQueryBuilder::table('users')
            ->whereNotExists(function ($query) {
                return $query
                    ->table('bans')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE NOT EXISTS (SELECT * FROM bans');
    });

    test('adds or where exists', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereExists(function ($query) {
                return $query
                    ->table('premium_memberships')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR EXISTS');
    });

    test('adds or where not exists', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->orWhereNotExists(function ($query) {
                return $query
                    ->table('suspensions')
                    ->where('user_id', 'users.id');
            });
        
        expect($query->toSql())->toContain('WHERE status = ? OR NOT EXISTS');
    });

    test('adds where sub', function () {
        $query = MockQueryBuilder::table('users')
            ->whereSub('user_count', '>', function ($query) {
                return $query
                    ->table('orders')
                    ->select('COUNT(*)');
            });
        
        expect($query->toSql())->toContain('WHERE user_count > (SELECT COUNT(*) FROM orders)');
    });
});