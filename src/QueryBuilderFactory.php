<?php

namespace Rcalicdan\QueryBuilderPrimitives;

use PDO;

class QueryBuilderFactory
{
    /**
     * Create a query builder instance with driver auto-detection from PDO.
     *
     * @param  PDO  $pdo  The PDO instance.
     * @param  string|null  $table  Optional table name.
     */
    public static function create(PDO $pdo, ?string $table = null): QueryBuilderBase
    {
        /** @var string $driver */
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        $builder = new QueryBuilderBase();
        $builder = $builder->setDriver($driver);

        if ($table !== null) {
            $builder = $builder->table($table);
        }

        return $builder;
    }

    /**
     * Create a query builder instance with manual driver specification.
     *
     * @param  string  $driver  The database driver name (mysql, pgsql, sqlsrv, mssql, sqlite).
     * @param  string|null  $table  Optional table name.
     */
    public static function createWithDriver(string $driver, ?string $table = null): QueryBuilderBase
    {
        $builder = new QueryBuilderBase();
        $builder = $builder->setDriver($driver);

        if ($table !== null) {
            $builder = $builder->table($table);
        }

        return $builder;
    }
}
