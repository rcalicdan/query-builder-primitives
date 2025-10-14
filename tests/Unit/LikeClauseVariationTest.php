<?php

use Tests\MockQueryBuilder;

describe('Like Clause Variations', function () {
    test('multiple like clauses', function () {
        $query = MockQueryBuilder::table('users')
            ->like('name', 'John')
            ->like('email', 'gmail', 'after');
        
        $sql = $query->toSql();
        expect($sql)->toContain('LIKE');
        expect($query->getBindings())->toBe(['%John%', 'gmail%']);
    });

    test('like with empty string', function () {
        $query = MockQueryBuilder::table('users')
            ->like('name', '');
        
        expect($query->getBindings())->toBe(['%%']);
    });
});