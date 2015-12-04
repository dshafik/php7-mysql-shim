<?php
/**
 * php7-mysql-shim
 *
 * @author Davey Shafik <dshafik@akamai.com>
 * @copyright Copyright 2015 Akamai Technologies, Inc. All rights reserved.
 * @license Apache 2.0
 * @link https://github.com/akamai-open/php7-mysql-shim
 * @link https://developer.akamai.com
 */

namespace Dshafik\MySQL\Tests;

use function \mysql_connect;

class MySqlShimTest extends \PHPUnit_Framework_TestCase
{
    static $host;
    static $container;
    static $bin = [];

    public function test_mysql_connect()
    {
        $mysql = mysql_connect(static::$host, 'root');

        $this->assertTrue(
            is_resource($mysql) && get_resource_type($mysql) == 'mysql link'
            ||
            $mysql instanceof \mysqli
        );
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessageRegExp /^mysql(i?)_connect\(\): (\(HY000\/1045\): )?Access denied for user ''@'(.*?)' \(using password: NO\)$/
     */
    public function test_mysql_connect_fail()
    {
        $mysql = mysql_connect(static::$host);

        $this->assertFalse($mysql);
    }

    public function test_mysql_query()
    {
        \mysql_connect(static::$host, 'root');
        $result = \mysql_query("SELECT VERSION()");

        $this->assertTrue(
            is_resource($result) && get_resource_type($result) == 'mysql result'
            || $result instanceof \mysqli_result
        );
    }

    public function test_mysql_query_nodata()
    {
        \mysql_connect(static::$host, 'root');
        $result = \mysql_query("SET @test = 'foo'");

        $this->assertTrue($result);
    }

    public function test_mysql_query_fail()
    {
        \mysql_connect(static::$host, 'root');
        $result = \mysql_query("SELECT VERSION(");

        $this->assertFalse($result);
    }

    public function test_mysql_fetch_array()
    {
        \mysql_connect(static::$host, 'root');

        $result = \mysql_query("SELECT 'test' AS col");

        $row = \mysql_fetch_array($result);
        $this->assertTrue(is_array($row));
        $this->assertArrayHasKey(0, $row);
        $this->assertEquals($row[0], 'test');
        $this->assertArrayHasKey('col', $row);
        $this->assertEquals($row['col'], 'test');
    }

    public function test_mysql_fetch_array_multirow()
    {
        \mysql_connect(static::$host, 'root');

        $result = \mysql_query("SHOW DATABASES");

        while ($row = \mysql_fetch_array($result)) {
            $this->assertTrue(is_array($row));
            $this->assertArrayHasKey(0, $row);
            $this->assertTrue(in_array($row[0], ["information_schema", "mysql", "performance_schema", "sys"]));
            $this->assertArrayHasKey('Database', $row);
            $this->assertTrue(in_array($row['Database'], ["information_schema", "mysql", "performance_schema", "sys"]));
        }
    }

    public function test_mysql_num_rows()
    {
        \mysql_connect(static::$host, 'root');

        $result = \mysql_query("SHOW DATABASES");

        $this->assertEquals(4, \mysql_num_rows($result));
    }

    public function test_mysql_close()
    {
        \mysql_connect(static::$host, 'root');
        $this->assertTrue(\mysql_close());
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage mysql_close(): no MySQL-Link resource supplied
     */
    public function test_mysql_close_fail()
    {
        $this->assertFalse(\mysql_close());
    }

    public function tearDown()
    {
        @\mysql_close();
    }

    public static function setUpBeforeClass()
    {
        fwrite(STDERR, "=> Finding binaries\n");
        static::$bin['dm'] = $dm = exec('/usr/bin/env which docker-machine');
        static::$bin['docker'] = $docker = exec('/usr/bin/env which docker');
        if (empty($dm) && empty($docker)) {
            static::markTestSkipped('Docker is required to run these tests');
        }

        if (!empty($dm)) {

            fwrite(STDERR, "=> Starting Docker Machine\n");
            exec($dm . ' create -d virtualbox mysql-shim');
            exec($dm . ' start mysql-shim');

            $env = '';
            exec($dm . ' env mysql-shim', $env);
            foreach ($env as $line) {
                if ($line{0} !== '#') {
                    putenv(str_replace(["export ", '"'], "", $line));
                }
            }
        }

        fwrite(STDERR, "=> Running Docker Container: ");
        static::$container = exec($docker . ' run -e MYSQL_ALLOW_EMPTY_PASSWORD=1 -P -d  mysql/mysql-server');

        if (empty(static::$container)) {
            static::markTestSkipped("Unable to start docker container");
        }

        fwrite(STDERR, static::$container . "\n");

        fwrite(STDERR, "=> Finding MySQL Host: ");
        static::$host = exec($docker . ' port ' . self::$container . ' 3306');
        fwrite(STDERR, static::$host . "\n");

        if (!empty($dm)) {
            fwrite(STDERR, "=> Using Docker Machine IP: ");
            $info = explode(':', static::$host);
            $port = array_pop($info);
            static::$host = exec($dm . ' ip mysql-shim') . ':' . $port;
            fwrite(STDERR, static::$host . "\n");
        }

        fwrite(STDERR, "=> Waiting on mysqld to start:");
        $out = '';
        while (trim($out) != 'mysqld') {
            $out = exec(static::$bin['docker'] . ' exec ' . static::$container . ' ps ax | awk \'/mysqld/ {print $NF}\'');
        }
        fwrite(STDERR, " started\n");

        fwrite(STDERR, "=> Docker Container Running\n\n");

        error_reporting(E_ALL & ~E_DEPRECATED);

        sleep(3);
    }

    public static function tearDownAfterClass()
    {
        fwrite(STDERR, "\n\nStopping Docker Container: ");
        $output = exec(static::$bin['docker'] . ' stop ' .static::$container);
        if (trim($output) !== static::$container) {
            fwrite(STDERR, " Failed to stop container!\n");
            return;
        }

        $output = exec(static::$bin['docker'] . ' rm ' .static::$container);
        if (trim($output) !== static::$container) {
            fwrite(STDERR, " Failed to remove container!\n");
            return;
        }
        fwrite(STDERR, "Done\n");
    }
}
