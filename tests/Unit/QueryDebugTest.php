<?php

use Tests\MockQueryBuilder;

describe('QueryDebug', function () {
    test('converts to sql', function () {
        $query = MockQueryBuilder::table('users')
            ->where('id', 1);
        
        expect($query->toSql())->toBe('SELECT * FROM users WHERE id = ?');
    });

    test('gets bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('status', 'active')
            ->whereIn('role', ['admin', 'user']);
        
        expect($query->getBindings())->toBe(['active', 'admin', 'user']);
    });

    test('converts to raw sql', function () {
        $query = MockQueryBuilder::table('users')
            ->where('name', 'John')
            ->where('age', 25);
        
        $rawSql = $query->toRawSql();
        expect($rawSql)->toContain("'John'");
        expect($rawSql)->toContain('25');
        expect($rawSql)->not->toContain('?');
    });

    test('dump returns same instance', function () {
        $query = MockQueryBuilder::table('users');
        
        ob_start();
        $result = $query->dump();
        ob_end_clean();
        
        expect($result)->toBe($query);
    });
});