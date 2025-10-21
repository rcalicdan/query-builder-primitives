<?php

use Tests\MockQueryBuilder;

describe('Select Variations', function () {
    test('select with table prefix', function () {
        $query = MockQueryBuilder::table('users')
            ->select(['users.id', 'users.name', 'profiles.bio'])
            ->join('profiles', 'users.id = profiles.user_id')
        ;

        expect($query->toSql())->toContain('users.id, users.name, profiles.bio');
    });

    test('select with aliases', function () {
        $query = MockQueryBuilder::table('users')
            ->select(['id', 'name as full_name', 'email as contact_email'])
        ;

        expect($query->toSql())->toContain('name as full_name');
    });

    test('select with functions', function () {
        $query = MockQueryBuilder::table('orders')
            ->select(['user_id', 'COUNT(*) as order_count', 'SUM(total) as total_amount'])
        ;

        expect($query->toSql())->toContain('COUNT(*)');
        expect($query->toSql())->toContain('SUM(total)');
    });

    test('addSelect preserves existing selections', function () {
        $query = MockQueryBuilder::table('users')
            ->select(['id', 'name'])
            ->addSelect('email')
            ->addSelect(['phone', 'address'])
        ;

        expect($query->toSql())->toBe('SELECT id, name, email, phone, address FROM users');
    });
});
