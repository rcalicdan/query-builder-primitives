<?php

use Rcalicdan\QueryBuilderPrimitives\QueryBuilderBase;

require 'vendor/autoload.php';

$queryBuilder = new QueryBuilderBase();

$sql = $queryBuilder->select('id', 'name')->from('users')->where('status', 'active')->toRawSql();
echo $sql;
