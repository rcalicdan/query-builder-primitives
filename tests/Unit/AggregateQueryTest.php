<?php

use Tests\MockQueryBuilder;

describe('Aggregate Query Tests', function () {

    test('simple MAX query', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder->buildAggregateQuery('MAX', 'price');

        expect($sql)->toBe('SELECT MAX(price) FROM products');
    });

    test('MIN with WHERE clause', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder->where('status', '=', 'active')
            ->buildAggregateQuery('MIN', 'price');

        expect($sql)->toBe('SELECT MIN(price) FROM products WHERE status = ?');
    });

    test('AVG with multiple WHERE conditions', function () {
        $builder = MockQueryBuilder::table('orders');
        $sql = $builder
            ->where('status', '=', 'completed')
            ->where('created_at', '>', '2024-01-01')
            ->buildAggregateQuery('AVG', 'amount');

        expect($sql)->toBe('SELECT AVG(amount) FROM orders WHERE status = ? AND created_at > ?');
    });

    test('SUM with GROUP BY', function () {
        $builder = MockQueryBuilder::table('orders');
        $sql = $builder
            ->groupBy('customer_id')
            ->buildAggregateQuery('SUM', 'total_amount');

        expect($sql)->toBe('SELECT SUM(total_amount) FROM orders GROUP BY customer_id');
    });

    test('AVG with JOIN, WHERE, and GROUP BY', function () {
        $builder = MockQueryBuilder::table('orders');
        $sql = $builder
            ->join('customers', 'orders.customer_id = customers.id')
            ->where('orders.status', '=', 'completed')
            ->groupBy('customers.country')
            ->buildAggregateQuery('AVG', 'orders.amount');

        expect($sql)->toBe('SELECT AVG(orders.amount) FROM orders INNER JOIN customers ON orders.customer_id = customers.id WHERE orders.status = ? GROUP BY customers.country');
    });

    test('SUM with multiple JOINs and WHERE', function () {
        $builder = MockQueryBuilder::table('order_items');
        $sql = $builder
            ->join('orders', 'order_items.order_id = orders.id')
            ->join('products', 'order_items.product_id = products.id')
            ->where('orders.status', '=', 'completed')
            ->where('products.category', '=', 'electronics')
            ->buildAggregateQuery('SUM', 'order_items.quantity');

        expect($sql)->toBe('SELECT SUM(order_items.quantity) FROM order_items INNER JOIN orders ON order_items.order_id = orders.id INNER JOIN products ON order_items.product_id = products.id WHERE orders.status = ? AND products.category = ?');
    });

    test('MAX with GROUP BY and HAVING', function () {
        $builder = MockQueryBuilder::table('sales');
        $sql = $builder
            ->groupBy('product_id')
            ->havingRaw('SUM(quantity) > 100')
            ->buildAggregateQuery('MAX', 'price');

        expect($sql)->toBe('SELECT MAX(price) FROM sales GROUP BY product_id HAVING SUM(quantity) > 100');
    });

    test('AVG with WHERE IN', function () {
        $builder = MockQueryBuilder::table('reviews');
        $sql = $builder
            ->whereIn('product_id', [1, 2, 3, 4, 5])
            ->buildAggregateQuery('AVG', 'rating');

        expect($sql)->toBe('SELECT AVG(rating) FROM reviews WHERE product_id IN (?, ?, ?, ?, ?)');
    });

    test('SUM with WHERE BETWEEN', function () {
        $builder = MockQueryBuilder::table('transactions');
        $sql = $builder
            ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
            ->buildAggregateQuery('SUM', 'amount');

        expect($sql)->toBe('SELECT SUM(amount) FROM transactions WHERE created_at BETWEEN ? AND ?');
    });

    test('MIN with WHERE NOT NULL', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->whereNotNull('discount_price')
            ->buildAggregateQuery('MIN', 'discount_price');

        expect($sql)->toBe('SELECT MIN(discount_price) FROM products WHERE discount_price IS NOT NULL');
    });

    test('complex SUM with JOIN, WHERE, GROUP BY, and HAVING', function () {
        $builder = MockQueryBuilder::table('order_items');
        $sql = $builder
            ->join('orders', 'order_items.order_id = orders.id')
            ->join('products', 'order_items.product_id = products.id')
            ->join('customers', 'orders.customer_id = customers.id')
            ->where('orders.status', '=', 'completed')
            ->where('customers.country', '=', 'USA')
            ->whereIn('products.category', ['electronics', 'computers'])
            ->whereBetween('orders.created_at', ['2024-01-01', '2024-12-31'])
            ->groupBy('products.category')
            ->having('COUNT(*) > 10')
            ->buildAggregateQuery('SUM', 'order_items.total_price');

        expect($sql)->toContain('SELECT SUM(order_items.total_price)')
            ->toContain('FROM order_items')
            ->toContain('INNER JOIN orders')
            ->toContain('INNER JOIN products')
            ->toContain('INNER JOIN customers')
            ->toContain('WHERE orders.status = ?')
            ->toContain('GROUP BY products.category')
            ->toContain('HAVING COUNT(*) > 10');
    });

    test('AVG with OR conditions', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->where('category', '=', 'electronics')
            ->orWhere('category', '=', 'computers')
            ->buildAggregateQuery('AVG', 'price');

        expect($sql)->toContain('SELECT AVG(price)')
            ->toContain('FROM products')
            ->toContain('WHERE')
            ->toContain('OR');
    });

    test('MAX with LEFT JOIN', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->leftJoin('reviews', 'products.id = reviews.product_id')
            ->where('products.status', '=', 'active')
            ->buildAggregateQuery('MAX', 'reviews.rating');

        expect($sql)->toBe('SELECT MAX(reviews.rating) FROM products LEFT JOIN reviews ON products.id = reviews.product_id WHERE products.status = ?');
    });

    test('COUNT existing method for comparison', function () {
        $builder = MockQueryBuilder::table('users');
        $sql = $builder
            ->where('status', '=', 'active')
            ->groupBy('country')
            ->buildCountQuery();

        expect($sql)->toBe('SELECT COUNT(*) FROM users WHERE status = ? GROUP BY country');
    });

    test('SUM with CROSS JOIN', function () {
        $builder = MockQueryBuilder::table('prices');
        $sql = $builder
            ->crossJoin('multipliers')
            ->buildAggregateQuery('SUM', 'prices.amount');

        expect($sql)->toBe('SELECT SUM(prices.amount) FROM prices CROSS JOIN multipliers');
    });

    test('AVG with PostgreSQL driver', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->setDriver('pgsql')
            ->where('status', '=', 'active')
            ->buildAggregateQuery('AVG', 'price');

        expect($sql)->toBe('SELECT AVG(price) FROM products WHERE status = ?');
    });

    test('MAX with SQL Server driver', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->setDriver('sqlsrv')
            ->where('status', '=', 'active')
            ->buildAggregateQuery('MAX', 'price');

        expect($sql)->toBe('SELECT MAX(price) FROM products WHERE status = ?');
    });

    test('all aggregate functions on same table', function () {
        $baseBuilder = MockQueryBuilder::table('products')->where('status', '=', 'active');

        $max = (clone $baseBuilder)->buildAggregateQuery('MAX', 'price');
        $min = (clone $baseBuilder)->buildAggregateQuery('MIN', 'price');
        $avg = (clone $baseBuilder)->buildAggregateQuery('AVG', 'price');
        $sum = (clone $baseBuilder)->buildAggregateQuery('SUM', 'price');

        expect($max)->toContain('SELECT MAX(price)')
            ->and($min)->toContain('SELECT MIN(price)')
            ->and($avg)->toContain('SELECT AVG(price)')
            ->and($sum)->toContain('SELECT SUM(price)');
    });

    test('MIN with RIGHT JOIN', function () {
        $builder = MockQueryBuilder::table('products');
        $sql = $builder
            ->rightJoin('categories', 'products.category_id = categories.id')
            ->where('categories.active', '=', true)
            ->buildAggregateQuery('MIN', 'products.price');

        expect($sql)->toBe('SELECT MIN(products.price) FROM products RIGHT JOIN categories ON products.category_id = categories.id WHERE categories.active = ?');
    });

    test('SUM with INNER JOIN', function () {
        $builder = MockQueryBuilder::table('order_items');
        $sql = $builder
            ->innerJoin('orders', 'order_items.order_id = orders.id')
            ->where('orders.status', '=', 'paid')
            ->buildAggregateQuery('SUM', 'order_items.price');

        expect($sql)->toBe('SELECT SUM(order_items.price) FROM order_items INNER JOIN orders ON order_items.order_id = orders.id WHERE orders.status = ?');
    });

    test('real-world scenario - sales analytics', function () {
        $builder = MockQueryBuilder::table('sales');
        $sql = $builder
            ->join('products', 'sales.product_id = products.id')
            ->join('categories', 'products.category_id = categories.id')
            ->where('sales.sale_date', '>=', '2024-01-01')
            ->where('sales.sale_date', '<=', '2024-12-31')
            ->where('categories.name', '=', 'Electronics')
            ->groupBy('products.id')
            ->groupBy('products.name')
            ->having('SUM(sales.quantity) > 50')
            ->buildAggregateQuery('SUM', 'sales.total_amount');

        expect($sql)->toContain('SELECT SUM(sales.total_amount)')
            ->toContain('FROM sales')
            ->toContain('GROUP BY products.id, products.name')
            ->toContain('HAVING SUM(sales.quantity) > 50');
    });

    test('aggregate on table without conditions', function () {
        $builder = MockQueryBuilder::table('inventory');
        $sql = $builder->buildAggregateQuery('COUNT', '*');

        expect($sql)->toBe('SELECT COUNT(*) FROM inventory');
    });

    test('using toSql() for debugging SELECT vs Aggregate', function () {
        $builder = MockQueryBuilder::table('products');
        $builder = $builder->where('status', '=', 'active')
            ->select(['id', 'name', 'price']);

        $selectQuery = $builder->toSql();
        $aggregateQuery = $builder->buildAggregateQuery('MAX', 'price');

        expect($selectQuery)->toContain('SELECT')
            ->toContain('id')
            ->toContain('name')
            ->toContain('price')
            ->and($aggregateQuery)->toContain('SELECT MAX(price)')
            ->and($aggregateQuery)->not->toContain('id, name');
    });
});
