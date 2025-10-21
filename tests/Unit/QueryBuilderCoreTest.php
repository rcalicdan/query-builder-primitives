<?php

use Tests\MockQueryBuilder;

describe('QueryBuilderCore', function () {
    test('sets table name', function () {
        $query = MockQueryBuilder::table('users');

        expect($query->toSql())->toContain('FROM users');
    });

    test('sets select columns', function () {
        $query = MockQueryBuilder::table('users')
            ->select(['id', 'name', 'email'])
        ;

        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('sets select columns from string', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id, name, email')
        ;

        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('adds select columns', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id')
            ->addSelect(['name', 'email'])
        ;

        expect($query->toSql())->toBe('SELECT id, name, email FROM users');
    });

    test('selects distinct', function () {
        $query = MockQueryBuilder::table('users')
            ->selectDistinct('email')
        ;

        expect($query->toSql())->toBe('SELECT DISTINCT email FROM users');
    });

    test('selects distinct with multiple columns', function () {
        $query = MockQueryBuilder::table('users')
            ->selectDistinct(['name', 'email'])
        ;

        expect($query->toSql())->toContain('DISTINCT name');
    });

    test('returns compiled bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->where('id', 1)
            ->where('name', 'John')
        ;

        expect($query->getBindings())->toBe([1, 'John']);
    });
});
