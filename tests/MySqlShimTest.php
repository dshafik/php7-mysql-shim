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

class MySqlShimTest extends \PHPUnit_Framework_TestCase
{
    static $host;
    static $container;
    static $bin = [];

    public function test_mysql_connect()
    {
        $mysql = \mysql_connect(static::$host, 'root');
        $this->assertConnection($mysql);
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessageRegExp /^mysql(i?)_connect\(\): (\(HY000\/1045\): )?Access denied for user ''@'(.*?)' \(using password: NO\)$/
     */
    public function test_mysql_connect_fail()
    {
        $mysql = \mysql_connect(static::$host);
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessage Argument $new is no longer supported in PHP > 7
     * @requires PHP 7.0.0
     */
    public function test_mysql_connect_new()
    {
        $mysql = \mysql_connect(static::$host, 'root', null, true);
    }

    public function test_mysql_connect_options()
    {
        $mysql = \mysql_connect(static::$host, 'root', null, false, MYSQL_CLIENT_COMPRESS);
        $this->assertConnection($mysql);
    }

    /**
     * @expectedException \PHPUnit_Framework_Error_Warning
     * @expectedExceptionMessageRegExp /^mysql((i_real)?)_connect\(\): (\(HY000\/1045\): )?Access denied for user ''@'(.*?)' \(using password: NO\)$/
     */
    public function test_mysql_connect_options_fail()
    {
        \mysql_connect(static::$host, null, null, false, MYSQL_CLIENT_COMPRESS);
    }

    public function test_mysql_connect_multi()
    {
        $conn = \mysql_connect(static::$host, 'root');
        $conn2 = \mysql_connect(static::$host, 'root');

        $this->assertEquals($conn, $conn2);

        $result = \mysql_query("SELECT CONNECTION_ID()", $conn);
        $row = \mysql_fetch_row($result);
        $id = $row[0];

        $result = \mysql_query("SELECT CONNECTION_ID()", $conn2);
        $row = \mysql_fetch_row($result);
        $id2 = $row[0];

        $this->assertEquals($id, $id2);
    }

    public function test_mysql_pconnect()
    {
        $conn = \mysql_pconnect(static::$host, 'root');

        $result = \mysql_query("SELECT 'persistent'", $conn);
        $row = \mysql_fetch_row($result);
        $this->assertEquals('persistent', $row[0]);
    }

    public function test_mysql_query_ddl()
    {
        $conn = \mysql_connect(static::$host, 'root');
        $result = \mysql_query("CREATE DATABASE shim_test;");
        $this->assertTrue($result);
        $result = \mysql_select_db('shim_test');
        $this->assertTrue($result);
        $result = \mysql_query(
            "CREATE TABLE testing (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                PRIMARY KEY (id)
            );"
        );
        $this->assertTrue($result, \mysql_error());
    }

    public function test_mysql_query_insert()
    {
        $this->getConnection("mysql_shim");
        $result = \mysql_query("INSERT INTO testing (one, two) VALUES ('1', '1'), ('2', '2'), ('3', '3'), ('4', '4')");

        $this->assertTrue($result, \mysql_error());
    }

    public function test_mysql_query()
    {
        $this->getConnection("mysql_shim");
        $result = \mysql_query("SELECT VERSION()");

        $this->assertResult($result);
    }

    public function test_mysql_query_nodata()
    {
        $this->getConnection("mysql_shim");
        $result = \mysql_query("SET @test = 'foo'");

        $this->assertTrue($result);
    }

    public function test_mysql_query_fail()
    {
        $this->getConnection("mysql_shim");
        $result = \mysql_query("SELECT VERSION(");

        $this->assertFalse($result);
    }

    public function test_mysql_unbuffered_query()
    {
        $this->getConnection("mysql_shim");

        $result = \mysql_unbuffered_query("SELECT one, two FROM testing LIMIT 4");
        $this->assertResult($result);
        $i = 0;
        while ($row = \mysql_fetch_assoc($result)) {
            $i++;
        }
        $this->assertEquals(4, $i);

        $result = \mysql_query("SELECT one, two FROM testing LIMIT 4");
        $this->assertResult($result);
    }

    public function test_mysql_unbuffered_query_fail()
    {
        $this->getConnection();

        $result = \mysql_unbuffered_query("SELECT VERSION(");
        $this->assertFalse($result);
    }

    public function test_mysql_unbuffered_query_num_rows()
    {
        $this->getConnection("mysql_shim");

        \mysql_query(
            "CREATE TABLE largetest (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                PRIMARY KEY (id)
          );"
        );

        $result = \mysql_unbuffered_query("SELECT one, two FROM testing");
        $this->assertResult($result);
        $this->assertEquals(0, \mysql_num_rows($result));
        \mysql_free_result($result);
    }

    public function test_mysql_unbuffered_query_close_legacy()
    {
        if (!version_compare(PHP_VERSION, '7.0.0', '<')) {
            $this->markTestSkipped("PHP < 7.0.0 is required");
        }

        $conn = $this->getConnection("mysql_shim");

        \mysql_query(
            "CREATE TABLE largetest (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                PRIMARY KEY (id)
          );"
        );

        $result = \mysql_unbuffered_query("SELECT one, two FROM testing");
        $this->assertResult($result);
        try {
            \mysql_close($conn);
        } catch (\PHPUnit_Framework_Error_Notice $e) {
            $this->assertEquals(
                "mysql_close(): Function called without first fetching all rows from a previous unbuffered query",
                $e->getMessage()
            );
        }
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_unbuffered_query_close()
    {
        $conn = $this->getConnection("mysql_shim");

        \mysql_query(
            "CREATE TABLE largetest (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                PRIMARY KEY (id)
          );"
        );

        $result = \mysql_unbuffered_query("SELECT one, two FROM testing");
        $this->assertResult($result);
        \mysql_close($conn);
    }

    /**
     * @dataProvider mysql_fetch_DataProvider
     */
    public function test_mysql_fetch($function, $results)
    {
        $this->getConnection("mysql_shim");

        $result = \mysql_query("SELECT one, two FROM testing");
        $this->assertResult($result);

        $this->assertEquals(sizeof($results), \mysql_num_rows($result));

        $i = 0;
        while ($row = $function($result)) {
            $this->assertEquals($results[$i], $row);
            $i++;
        }

        $this->assertEquals(sizeof($results), $i);
    }

    public function test_mysql_num_rows()
    {
        $this->getConnection("mysql_shim");

        $result = \mysql_query("SELECT * FROM testing");
        $this->assertResult($result);
        $this->assertEquals(4, \mysql_num_rows($result));
    }

    public function test_mysql_affected_rows()
    {
        $this->getConnection("mysql_shim");

        $result = \mysql_query("UPDATE testing SET one = one + 1, two = two + 1 ORDER BY one DESC LIMIT 4");
        $this->assertTrue($result);
        $this->assertEquals(4, \mysql_affected_rows());
        $result = \mysql_query("UPDATE testing SET one = one - 1, two = two - 1 ORDER BY one DESC LIMIT 4");
        $this->assertTrue($result);
        $this->assertEquals(4, \mysql_affected_rows());
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
        \mysql_close();
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

    public function mysql_fetch_DataProvider()
    {
        $numeric = [
            ['1', '1'],
            ['2', '2'],
            ['3', '3'],
            ['4', '4'],
        ];

        $assoc = [
            ['one' => '1', 'two' => '1'],
            ['one' => '2', 'two' => '2'],
            ['one' => '3', 'two' => '3'],
            ['one' => '4', 'two' => '4'],
        ];

        $array = [
            ['1', '1', 'one' => '1', 'two' => '1'],
            ['2', '2', 'one' => '2', 'two' => '2'],
            ['3', '3', 'one' => '3', 'two' => '3'],
            ['4', '4', 'one' => '4', 'two' => '4'],
        ];

        $object = [
            (object) ['one' => '1', 'two' => '1'],
            (object) ['one' => '2', 'two' => '2'],
            (object) ['one' => '3', 'two' => '3'],
            (object) ['one' => '4', 'two' => '4'],
        ];

        return [
            [
                'function' => 'mysql_fetch_array',
                'results' => $array
            ],
            [
                'function' => 'mysql_fetch_assoc',
                'results' => $assoc
            ],
            [
                'function' => 'mysql_fetch_row',
                'results' => $numeric
            ],
            [
                'function' => 'mysql_fetch_object',
                'results' => $object,
            ]
        ];
    }

    /**
     * @param $result
     */
    protected function assertResult($result)
    {
        $this->assertTrue(
            is_resource($result) && get_resource_type($result) == 'mysql result'
            || $result instanceof \mysqli_result,
            \mysql_error()
        );
    }

    protected function getConnection($db = null)
    {
        $mysql = \mysql_connect(static::$host, 'root');
        $this->assertConnection($mysql);

        if ($db !== null) {
            $this->assertTrue(\mysql_select_db('shim_test'));
        }

        return $mysql;
    }

    /**
     * @param $mysql
     */
    protected function assertConnection($mysql)
    {
        $this->assertTrue(
            is_resource($mysql) && get_resource_type($mysql) == 'mysql link'
            ||
            $mysql instanceof \mysqli,
            "Not a valid MySQL connection"
        );
    }
}
