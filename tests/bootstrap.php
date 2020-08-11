<?php
/**
 * php7-mysql-shim
 *
 * @author Davey Shafik <me@daveyshafik.com>
 * @copyright Copyright (c) 2017 Davey Shafik
 * @license MIT License
 * @link https://github.com/dshafik/php7-mysql-shim
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (getenv('MYSQL_HOST') !== '') {
    Dshafik\MySQL\Tests\MySqlShimTest::$host = getenv('MYSQL_HOST');   
}

if (getenv('MYSQL_USERNAME') !== '') {
    Dshafik\MySQL\Tests\MySqlShimTest::$username = getenv('MYSQL_USERNAME');   
}

if (getenv('MYSQL_PASSWORD') !== '') {
    Dshafik\MySQL\Tests\MySqlShimTest::$password = getenv('MYSQL_PASSWORD');
}