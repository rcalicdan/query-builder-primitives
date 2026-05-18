<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('Select Variations', function () {
    test('select with table prefix', function () {
        $query = MockQueryBuilder::table('users')
            ->select('users.id', 'users.name', 'profiles.bio')
            ->join('profiles', 'users.id = profiles.user_id')
        ;

        expect($query->toSql())->toContain('users.id, users.name, profiles.bio');
    });

    test('select with aliases', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id', 'name as full_name', 'email as contact_email')
        ;

        expect($query->toSql())->toContain('name as full_name');
    });

    test('select with functions', function () {
        $query = MockQueryBuilder::table('orders')
            ->select('user_id', 'COUNT(*) as order_count', 'SUM(total) as total_amount')
        ;

        expect($query->toSql())->toContain('COUNT(*)');
        expect($query->toSql())->toContain('SUM(total)');
    });

    test('addSelect preserves existing selections', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id', 'name')
            ->addSelect('email')
            ->addSelect('phone', 'address')
        ;

        expect($query->toSql())->toBe('SELECT id, name, email, phone, address FROM users');
    });

    test('selectRaw replaces wildcard and adds bindings in correct order', function () {
        $query = MockQueryBuilder::table('users')
            ->selectRaw('SUM(price * ?) as total', [1.2])
            ->where('status', 'active')
        ;

        expect($query->toSql())->toBe('SELECT SUM(price * ?) as total FROM users WHERE status = ?');
        expect($query->getBindings())->toBe([1.2, 'active']);
    });

    test('selectRaw appends to existing select', function () {
        $query = MockQueryBuilder::table('users')
            ->select('id', 'name')
            ->selectRaw('COUNT(orders.id) > ? as has_orders', [5])
        ;

        expect($query->toSql())->toBe('SELECT id, name, COUNT(orders.id) > ? as has_orders FROM users');
        expect($query->getBindings())->toBe([5]);
    });

    test('select clears existing select bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->selectRaw('SUM(price * ?) as total', [1.2])
            ->select('id', 'name')
        ;

        expect($query->toSql())->toBe('SELECT id, name FROM users');
        expect($query->getBindings())->toBe([]);
    });
});
