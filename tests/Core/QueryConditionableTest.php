<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryConditionable (when / unless)', function () {
    test('when executes callback when condition is true', function () {
        $query = MockQueryBuilder::table('users')
            ->when(true, fn ($q) => $q->where('status', 'active'))
        ;

        expect($query->toSql())->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('when ignores callback when condition is false', function () {
        $query = MockQueryBuilder::table('users')
            ->when(false, fn ($q) => $q->where('status', 'active'))
        ;

        expect($query->toSql())->not->toContain('WHERE');
        expect($query->getBindings())->toBeEmpty();
    });

    test('when executes default callback when condition is false', function () {
        $query = MockQueryBuilder::table('users')
            ->when(
                false,
                fn ($q) => $q->where('status', 'active'),
                fn ($q) => $q->where('status', 'inactive')
            )
        ;

        expect($query->toSql())->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['inactive']);
    });

    test('unless executes callback when condition is false', function () {
        $query = MockQueryBuilder::table('users')
            ->unless(false, fn ($q) => $q->where('status', 'active'))
        ;

        expect($query->toSql())->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('unless ignores callback when condition is true', function () {
        $query = MockQueryBuilder::table('users')
            ->unless(true, fn ($q) => $q->where('status', 'active'))
        ;

        expect($query->toSql())->not->toContain('WHERE');
        expect($query->getBindings())->toBeEmpty();
    });

    test('unless executes default callback when condition is true', function () {
        $query = MockQueryBuilder::table('users')
            ->unless(
                true,
                fn ($q) => $q->where('status', 'active'),
                fn ($q) => $q->where('status', 'inactive')
            )
        ;

        expect($query->toSql())->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['inactive']);
    });

    test('passes condition value as second argument to callback', function () {
        $searchQuery = 'John Doe';

        $query = MockQueryBuilder::table('users')
            ->when($searchQuery, function ($q, $search) {
                return $q->where('name', $search);
            })
        ;

        expect($query->toSql())->toContain('WHERE name = ?');
        expect($query->getBindings())->toBe(['John Doe']);
    });

    test('evaluates closure as condition', function () {
        $query = MockQueryBuilder::table('users')
            ->when(
                fn ($q) => str_contains($q->toSql(), 'users'),
                fn ($q) => $q->limit(10)
            )
        ;

        expect($query->toSql())->toContain('LIMIT 10');
    });

    test('evaluates arrow functions correctly', function () {
        $query = MockQueryBuilder::table('users')
            ->when(
                fn () => true,
                fn ($q) => $q->where('status', 'active')
            )
        ;

        expect($query->toSql())->toContain('WHERE status = ?');
        expect($query->getBindings())->toBe(['active']);
    });

    test('evaluates invokable class as condition', function () {
        $invokable = new class () {
            public function __invoke($q): bool
            {
                return str_contains($q->toSql(), 'users');
            }
        };

        $query = MockQueryBuilder::table('users')
            ->when(
                $invokable,
                fn ($q) => $q->limit(5)
            )
        ;

        expect($query->toSql())->toContain('LIMIT 5');
    });

    test('safely treats string callables as data values', function () {
        $search = 'count';

        $query = MockQueryBuilder::table('users')
            ->when(
                $search,
                fn ($q, $val) => $q->where('type', $val)
            )
        ;

        expect($query->toSql())->toContain('WHERE type = ?');
        expect($query->getBindings())->toBe(['count']);
    });

    test('safely treats array callables as raw data values instead of executing them', function () {
        $arrayCallable = [MockQueryBuilder::class, 'table'];

        $query = MockQueryBuilder::table('users')
            ->when($arrayCallable, function ($q, $val) {
                return $q->whereIn('tags', $val);
            })
        ;

        expect($query->toSql())->toContain('WHERE tags IN (?, ?)');
        expect($query->getBindings())->toBe([MockQueryBuilder::class, 'table']);
    });

    test('treats non-invokable objects as truthy raw values without executing them', function () {
        $nonInvokable = new stdClass();
        $nonInvokable->foo = 'bar';

        $query = MockQueryBuilder::table('users')
            ->when($nonInvokable, function ($q, $val) {
                return $q->where('meta', $val->foo);
            })
        ;

        expect($query->toSql())->toContain('WHERE meta = ?');
        expect($query->getBindings())->toBe(['bar']);
    });

    test('correctly evaluates standard PHP falsy values without executing them', function () {
        $falsyValues = [
            false,
            0,
            0.0,
            '',
            '0',
            [],
            null,
        ];

        foreach ($falsyValues as $falsy) {
            $query = MockQueryBuilder::table('users')
                ->when($falsy, fn ($q) => $q->where('id', 1))
            ;

            expect($query->toSql())->not->toContain('WHERE');
            expect($query->getBindings())->toBeEmpty();
        }
    });

    test('correctly evaluates standard PHP truthy values without executing them', function () {
        $truthyValues = [
            true,
            1,
            -1,
            1.5,
            '1',
            'false',
            [0],
            ' ',
        ];

        foreach ($truthyValues as $truthy) {
            $query = MockQueryBuilder::table('users')
                ->when($truthy, fn ($q, $val) => $q->where('val', $val))
            ;

            expect($query->toSql())->toContain('WHERE val = ?');
            expect($query->getBindings())->toBe([$truthy]);
        }
    });

    test('preserves immutability correctly', function () {
        $base = MockQueryBuilder::table('users');
        $filtered = $base->when(true, fn ($q) => $q->where('id', 1));

        expect($base->toSql())->not->toContain('WHERE');
        expect($filtered->toSql())->toContain('WHERE');
    });
});
