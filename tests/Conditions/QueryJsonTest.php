<?php

declare(strict_types=1);

use Tests\MockQueryBuilder;

describe('QueryJson Primitives - Dialect Compilation', function () {

    describe('MySQL JSON Compilation', function () {
        test('whereJson compares key', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->whereJson('options->preferences->theme', 'dark')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.preferences.theme')) = ?");
            expect($query->getBindings())->toBe(['dark']);
        });

        test('whereJsonContains parses value as JSON string', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->whereJsonContains('options->languages', 'en')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_CONTAINS(options, ?, '$.languages')");
            expect($query->getBindings())->toBe(['"en"']);
        });

        test('whereJsonLength compiles', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('mysql')
                ->whereJsonLength('options->tags', '>', 5)
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_LENGTH(JSON_EXTRACT(options, '$.tags')) > ?");
            expect($query->getBindings())->toBe([5]);
        });
    });

    describe('PostgreSQL JSON Compilation', function () {
        test('whereJson compiles to text extraction operator', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->whereJson('options->preferences->theme', '=', 'dark')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE options#>>'{preferences,theme}' = ?");
            expect($query->getBindings())->toBe(['dark']);
        });

        test('whereJsonContains compiles to jsonb operator', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->whereJsonContains('options->languages', 'en')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE (options->'languages')::jsonb @> ?::jsonb");
            expect($query->getBindings())->toBe(['"en"']);
        });

        test('whereJsonLength compiles to jsonb_array_length', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('pgsql')
                ->whereJsonLength('options->tags', '=', 3)
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE jsonb_array_length((options->'tags')::jsonb) = ?");
            expect($query->getBindings())->toBe([3]);
        });
    });

    describe('SQLite JSON Compilation', function () {
        test('whereJson compiles to json_extract', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->whereJson('options->preferences->theme', 'dark')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE json_extract(options, '$.preferences.theme') = ?");
            expect($query->getBindings())->toBe(['dark']);
        });

        test('whereJsonContains compiles to subquery EXISTS on json_each', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->whereJsonContains('options->languages', 'en')
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE EXISTS (SELECT 1 FROM json_each(json_extract(options, '$.languages')) WHERE value = ?)");
            expect($query->getBindings())->toBe(['en']);
        });

        test('whereJsonLength compiles to json_array_length', function () {
            $query = MockQueryBuilder::table('users')
                ->setDriver('sqlite')
                ->whereJsonLength('options->tags', '>=', 1)
            ;

            expect($query->toSql())->toBe("SELECT * FROM users WHERE json_array_length(json_extract(options, '$.tags')) >= ?");
            expect($query->getBindings())->toBe([1]);
        });
    });
});

describe('QueryJson Primitives - Advanced & Edge Cases', function () {
    test('normalizes both dot and arrow notations interchangeably', function () {
        $queryArrow = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJson('options->preferences->theme', 'dark')
        ;

        $queryDot = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJson('options->preferences.theme', 'dark')
        ;

        expect($queryArrow->toSql())->toBe($queryDot->toSql());
        expect($queryArrow->getBindings())->toBe($queryDot->getBindings());
    });

    test('compiles root-level JSON queries securely with no path specified', function () {
        $mysql = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJsonContains('options', 'en')
        ;

        expect($mysql->toSql())->toBe("SELECT * FROM users WHERE JSON_CONTAINS(options, ?, '$')");
        expect($mysql->getBindings())->toBe(['"en"']);

        $pgsql = MockQueryBuilder::table('users')
            ->setDriver('pgsql')
            ->whereJsonContains('options', 'en')
        ;

        expect($pgsql->toSql())->toBe('SELECT * FROM users WHERE options::jsonb @> ?::jsonb');
        expect($pgsql->getBindings())->toBe(['"en"']);

        $sqlite = MockQueryBuilder::table('users')
            ->setDriver('sqlite')
            ->whereJsonContains('options', 'en')
        ;

        expect($sqlite->toSql())->toBe("SELECT * FROM users WHERE EXISTS (SELECT 1 FROM json_each(json_extract(options, '$')) WHERE value = ?)");
        expect($sqlite->getBindings())->toBe(['en']);
    });

    test('compiles complex structured values inside whereJsonContains', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJsonContains('options->tags', ['active' => true, 'score' => 10])
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_CONTAINS(options, ?, '$.tags')");
        expect($query->getBindings())->toBe(['{"active":true,"score":10}']);
    });

    test('compiles multiple consecutive JSON conditions with correct sequential parameter bindings', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJson('options->theme', 'dark')
            ->orWhereJsonContains('options->roles', 'admin')
            ->where('status', 'active')
        ;

        expect($query->toSql())->toBe(
            "SELECT * FROM users WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.theme')) = ? OR JSON_CONTAINS(options, ?, '$.roles') AND status = ?"
        );
        expect($query->getBindings())->toBe(['dark', '"admin"', 'active']);
    });
});

describe('QueryJson Primitives - Deep Logical & Operator Edge Cases', function () {
    test('compiles JSON conditions nested inside a whereGroup logical block', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('pgsql')
            ->where('active', 1)
            ->whereGroup(function ($q) {
                return $q->whereJson('meta->theme', 'dark')
                         ->orWhereJson('meta->theme', 'blue')
                ;
            })
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE active = ? AND (meta#>>'{theme}' = ? OR meta#>>'{theme}' = ?)");
        expect($query->getBindings())->toBe([1, 'dark', 'blue']);
    });

    test('compiles JSON extraction using mathematical comparison operators', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJson('options->age', '>=', 21)
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.age')) >= ?");
        expect($query->getBindings())->toBe([21]);
    });

    test('serializes boolean values as raw JSON literals instead of standard SQL 1 or 0', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJsonContains('options->active', true)
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_CONTAINS(options, ?, '$.active')");
        expect($query->getBindings())->toBe(['true']);
    });

    test('compiles JSON extraction using text partial-matching operators like LIKE', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('sqlite')
            ->whereJson('options->username', 'LIKE', '%admin%')
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE json_extract(options, '$.username') LIKE ?");
        expect($query->getBindings())->toBe(['%admin%']);
    });

    test('compiles explicit NULL comparisons safely', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJson('options->deleted_at', null)
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE JSON_UNQUOTE(JSON_EXTRACT(options, '$.deleted_at')) = ?");
        expect($query->getBindings())->toBe([null]);
    });

    test('compiles inside nested subqueries executing JSON conditions on different tables', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('pgsql')
            ->whereSub('id', 'IN', function ($sub) {
                return $sub->from('orders')
                           ->select('user_id')
                           ->whereJson('meta->status', 'completed')
                ;
            })
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE id IN (SELECT user_id FROM orders WHERE meta#>>'{status}' = ?)");
        expect($query->getBindings())->toBe(['completed']);
    });

    test('compiles deep array index-based extraction paths', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('sqlite')
            ->whereJson('options->languages->0', 'en')
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE json_extract(options, '$.languages.0') = ?");
        expect($query->getBindings())->toBe(['en']);
    });
});

describe('QueryJson Primitives - Negative Containment (DoesntContain)', function () {
    test('compiles MySQL NOT JSON_CONTAINS', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('mysql')
            ->whereJsonDoesntContain('options->languages', 'en')
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE NOT JSON_CONTAINS(options, ?, '$.languages')");
        expect($query->getBindings())->toBe(['"en"']);
    });

    test('compiles PostgreSQL NOT jsonb containment operator', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('pgsql')
            ->whereJsonDoesntContain('options->languages', 'en')
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE NOT (options->'languages')::jsonb @> ?::jsonb");
        expect($query->getBindings())->toBe(['"en"']);
    });

    test('compiles SQLite NOT EXISTS on json_each', function () {
        $query = MockQueryBuilder::table('users')
            ->setDriver('sqlite')
            ->whereJsonDoesntContain('options->languages', 'en')
        ;

        expect($query->toSql())->toBe("SELECT * FROM users WHERE NOT EXISTS (SELECT 1 FROM json_each(json_extract(options, '$.languages')) WHERE value = ?)");
        expect($query->getBindings())->toBe(['en']);
    });
});
