<?php

declare(strict_types=1);

use Rcalicdan\QueryBuilderPrimitives\Interfaces\JoinClauseInterface;
use Tests\MockQueryBuilder;

describe('Advanced Joins - Core Features', function () {
    test('supports simple closure joins using on()', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id');
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id');
    });

    test('supports advanced closure joins with parameter bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', '=', 'contacts.user_id')
                            ->where('contacts.status', 'active')
                ;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id AND contacts.status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('supports nested logical grouping inside joins', function () {
        $query = MockQueryBuilder::table('users')
            ->leftJoin('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->whereGroup(function ($q) {
                                return $q->where('contacts.type', 'primary')
                                         ->orWhere('contacts.type', 'emergency')
                                ;
                            })
                ;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users LEFT JOIN contacts ON users.id = contacts.user_id AND (contacts.type = ? OR contacts.type = ?)');
        expect($query->getBindings())->toBe(['primary', 'emergency']);
    });

    test('combines join bindings and where bindings in the strictly correct SQL execution order', function () {
        $query = MockQueryBuilder::table('users')
            ->selectRaw('COUNT(*) as total', [1])
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->where('contacts.status', 'active')
                ;
            })
            ->where('users.age', '>', 18)
        ;

        expect($query->toSql())->toContain('INNER JOIN contacts ON users.id = contacts.user_id AND contacts.status = ? WHERE users.age > ?');
        expect($query->getBindings())->toBe([1, 'active', 18]);
    });

    test('preserves exact backward compatibility with raw string joins', function () {
        $query = MockQueryBuilder::table('users')
            ->join('orders', 'users.id = orders.user_id AND orders.status = "paid"')
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN orders ON users.id = orders.user_id AND orders.status = "paid"');
        expect($query->getBindings())->toBeEmpty();
    });

    test('orOn applies correct OR logical operator', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->orOn('users.alt_id', 'contacts.user_id')
                ;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id OR users.alt_id = contacts.user_id');
    });
});

describe('Advanced Joins - Immutability & Isolation', function () {
    test('preserves immutability of the main query builder', function () {
        $base = MockQueryBuilder::table('users')->where('status', 'active');

        $joined = $base->join('contacts', function (JoinClauseInterface $join) {
            return $join->on('users.id', 'contacts.user_id')
                        ->where('contacts.priority', 1)
            ;
        });

        expect($base->toSql())->toBe('SELECT * FROM users WHERE status = ?');
        expect($base->getBindings())->toBe(['active']);

        expect($joined->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id AND contacts.priority = ? WHERE status = ?');
        expect($joined->getBindings())->toBe([1, 'active']);
    });

    test('preserves immutability inside the join closure (isolated chains)', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                $step1 = $join->on('users.id', 'contacts.user_id');
                $step2 = $step1->where('contacts.type', 'primary');

                // This branch is unused and must NOT affect $step2
                $unusedBranch = $step1->where('contacts.type', 'secondary');

                return $step2;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id AND contacts.type = ?');
        expect($query->getBindings())->toBe(['primary']);
    });
});

describe('Advanced Joins - Edge Cases & Diverse Operators', function () {
    test('handles empty closure gracefully', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON ');
        expect($query->getBindings())->toBeEmpty();
    });

    test('supports complex conditions (whereIn, whereNull, whereBetween) inside the join clause', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->whereIn('contacts.role', ['admin', 'staff'])
                            ->whereNull('contacts.deleted_at')
                            ->whereBetween('contacts.rank', [1, 5])
                ;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id AND contacts.role IN (?, ?) AND contacts.deleted_at IS NULL AND contacts.rank BETWEEN ? AND ?');
        expect($query->getBindings())->toBe(['admin', 'staff', 1, 5]);
    });

    test('supports complex subqueries (whereSub) inside advanced joins', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->whereSub('contacts.score', '>', function ($sub) {
                                return $sub->from('ratings')
                                           ->selectRaw('AVG(value)')
                                           ->where('active', 1)
                                ;
                            })
                ;
            })
        ;

        expect($query->toSql())->toBe('SELECT * FROM users INNER JOIN contacts ON users.id = contacts.user_id AND contacts.score > (SELECT AVG(value) FROM ratings WHERE active = ?)');
        expect($query->getBindings())->toBe([1]);
    });

    test('handles multiple advanced joins consecutively', function () {
        $query = MockQueryBuilder::table('users')
            ->join('contacts', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'contacts.user_id')
                            ->where('contacts.status', 'active')
                ;
            })
            ->leftJoin('orders', function (JoinClauseInterface $join) {
                return $join->on('users.id', 'orders.user_id')
                            ->where('orders.amount', '>', 100)
                ;
            })
        ;

        $sql = $query->toSql();
        expect($sql)->toContain('INNER JOIN contacts ON users.id = contacts.user_id AND contacts.status = ?');
        expect($sql)->toContain('LEFT JOIN orders ON users.id = orders.user_id AND orders.amount > ?');
        expect($query->getBindings())->toBe(['active', 100]);
    });
});
