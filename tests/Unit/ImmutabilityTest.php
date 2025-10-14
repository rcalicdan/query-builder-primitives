<?php

use Tests\MockQueryBuilder;

describe('Immutability', function () {
    test('query builder returns new instances', function () {
        $query1 = MockQueryBuilder::table('users');
        $query2 = $query1->where('id', 1);
        
        expect($query1)->not->toBe($query2);
        expect($query1->toSql())->toBe('SELECT * FROM users');
        expect($query2->toSql())->toBe('SELECT * FROM users WHERE id = ?');
    });

    test('multiple branches from same query', function () {
        $base = MockQueryBuilder::table('users')->where('status', 'active');
        
        $admins = $base->where('role', 'admin');
        $users = $base->where('role', 'user');
        
        expect($admins->toSql())->toContain("role = ?");
        expect($users->toSql())->toContain("role = ?");
        expect($admins->getBindings())->toBe(['active', 'admin']);
        expect($users->getBindings())->toBe(['active', 'user']);
    });

    test('cloning preserves all state', function () {
        $query1 = MockQueryBuilder::table('users')
            ->select('id, name')
            ->where('status', 'active')
            ->orderBy('name')
            ->limit(10);
        
        $query2 = $query1->where('age', '>', 18);
        
        expect($query1->getBindings())->toBe(['active']);
        expect($query2->getBindings())->toBe(['active', 18]);
        expect($query1->toSql())->not->toContain('age');
        expect($query2->toSql())->toContain('age');
    });

    test('resetWhere creates new instance', function () {
        $query1 = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->where('age', '>', 18);
        
        $query2 = $query1->resetWhere();
        
        expect($query1->getBindings())->toBe(['active', 18]);
        expect($query2->getBindings())->toBe([]);
        expect($query1)->not->toBe($query2);
    });
});