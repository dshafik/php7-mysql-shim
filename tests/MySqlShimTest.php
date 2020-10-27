<?php
/**
 * php7-mysql-shim
 *
 * @author Davey Shafik <me@daveyshafik.com>
 * @copyright Copyright (c) 2017 Davey Shafik
 * @license MIT License
 * @link https://github.com/dshafik/php7-mysql-shim
 */
namespace Dshafik\MySQL\Tests;

class MySqlShimTest extends \Yoast\PHPUnitPolyfills\TestCases\TestCase
{
    /**
     * @var string MySQL Host
     */
    public static $host = 'localhost';
    public static $username = 'root';
    public static $password = null;

    /**
     * @var array Location of binaries
     */
    protected static $bin = array();

    /**
     * @var array List of databases created
     */
    protected static $dbs = array();

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        if (getenv('MYSQL_HOST') !== false) {
            static::$host = getenv('MYSQL_HOST');
        }

        if (getenv('MYSQL_USERNAME') !== false) {
            static::$username = getenv('MYSQL_USERNAME');
        }

        if (getenv('MYSQL_PASSWORD') !== false) {
            static::$password = getenv('MYSQL_PASSWORD');
        }

        parent::__construct($name, $data, $dataName);
    }

    public function test_mysql_connect()
    {
        $mysql = mysql_connect(static::$host, static::$username, static::$password);
        $this->assertConnection($mysql);
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_connect_defaults()
    {
        ini_set('mysqli.default_host', static::$host);
        ini_set('mysqli.default_user', static::$username);
        ini_set('mysqli.default_pw', (static::$password === null) ? '' : static::$password);

        $mysql = mysql_connect();
        $this->assertConnection($mysql);
    }

    public function test_mysql_connect_port()
    {
        $mysql = mysql_connect(static::$host . ':3306', static::$username, static::$password);
        $this->assertConnection($mysql); 
    }

    public function test_mysql_connect_fail_warning()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^mysql(i?)_connect\(\): (\(HY000\/1045\): )?Access denied for user 'baduser'@'(.*?)' \(using password: YES\)$/");
        mysql_connect(static::$host, 'baduser', 'badpass');
    }

    public function test_mysql_connect_fail_false()
    {
        $mysql = @mysql_connect(static::$host, 'baduser', 'badpass');
        $this->assertFalse($mysql);
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_connect_new()
    {
        $this->expectWarning();
        $this->expectWarningMessage('Argument $new is no longer supported in PHP > 7');
        $conn1 = mysql_connect(static::$host, static::$username, static::$password, true);
        $conn2 = mysql_connect(static::$host, static::$username, static::$password, true);

        $this->assertEquals($conn1, $conn2);
    }

    public function test_mysql_connect_options()
    {
        $mysql = mysql_connect(static::$host, static::$username, static::$password, false, MYSQL_CLIENT_COMPRESS);
        $this->assertConnection($mysql);
    }

    public function test_mysql_connect_options_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^mysql((i_real)?)_connect\(\): (\(HY000\/1045\): )?Access denied for user 'baduser'@'(.*?)' \(using password: YES\)$/");
        mysql_connect(static::$host, 'baduser', 'badpass', false, MYSQL_CLIENT_COMPRESS);
    }

    public function test_mysql_connect_multi()
    {
        $conn = mysql_connect(static::$host, static::$username, static::$password);
        $conn2 = mysql_connect(static::$host, static::$username, static::$password);

        $this->assertEquals($conn, $conn2);

        $result = mysql_query('SELECT CONNECTION_ID()', $conn);
        $row = mysql_fetch_row($result);
        $id = $row[0];

        $result = mysql_query('SELECT CONNECTION_ID()', $conn2);
        $row = mysql_fetch_row($result);
        $id2 = $row[0];

        $this->assertEquals($id, $id2);
    }

    public function test_mysql_pconnect()
    {
        $conn = mysql_pconnect(static::$host, static::$username, static::$password);

        $result = mysql_query("SELECT 'persistent'", $conn);
        $row = mysql_fetch_row($result);
        $this->assertEquals('persistent', $row[0]);
    }

    public function test_mysql_constants()
    {
        $this->assertTrue(defined('MYSQL_ASSOC'));
        $this->assertEquals(constant('MYSQL_ASSOC'), 1);
        $this->assertTrue(defined('MYSQL_NUM'));
        $this->assertEquals(constant('MYSQL_NUM'), 2);
        $this->assertTrue(defined('MYSQL_BOTH'));
        $this->assertEquals(constant('MYSQL_BOTH'), 3);
        $this->assertTrue(defined('MYSQL_CLIENT_COMPRESS'));
        $this->assertEquals(constant('MYSQL_CLIENT_COMPRESS'), 32);
        $this->assertTrue(defined('MYSQL_CLIENT_SSL'));
        $this->assertEquals(constant('MYSQL_CLIENT_SSL'), 2048);
        $this->assertTrue(defined('MYSQL_CLIENT_INTERACTIVE'));
        $this->assertEquals(constant('MYSQL_CLIENT_INTERACTIVE'), 1024);
        $this->assertTrue(defined('MYSQL_CLIENT_IGNORE_SPACE'));
        $this->assertEquals(constant('MYSQL_CLIENT_IGNORE_SPACE'), 256);
    }

    public function test_mysql_query_ddl()
    {
        mysql_connect(static::$host, static::$username, static::$password);
        $result = mysql_query('CREATE DATABASE IF NOT EXISTS shim_test');
        $this->assertTrue($result, mysql_error());
    }

    public function test_mysql_query_insert()
    {
        $this->getConnection('shim_test');
        $result = mysql_query(
            "INSERT INTO
                testing (one, two, three, four, five, six, seven, eight, nine, ten, eleven)
             VALUES
                ('1', '1', '1', '1', '1', '1', '1', '1', 'one', 'one', '1'),
                ('2', '2', '2', '2', '2', '2', '2', '2', 'two', 'two', '2'),
                ('3', '3', '3', '3', '3', '3', '3', '3', 'three', 'three', '3'),
                ('4', '4', '4', '4', '4', '4', '4', '4', 'four', 'four', '4')"
        );

        $this->assertTrue($result, mysql_error());

        $result = mysql_query(
            "INSERT INTO
                testing2 (one, two, three, four, five, six, seven, eight, nine, ten, eleven)
             VALUES
                ('1', '1', '1', '1', '1', '1', '1', '1', 'one', 'one', '1'),
                ('2', '2', '2', '2', '2', '2', '2', '2', 'two', 'two', '2'),
                ('3', '3', '3', '3', '3', '3', '3', '3', 'three', 'three', '3'),
                ('4', '4', '4', '4', '4', '4', '4', '4', 'four', 'four', '4')"
        );

        $this->assertTrue($result, mysql_error());
    }

    public function test_mysql_query()
    {
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT VERSION()');

        $this->assertResult($result);
    }

    public function test_mysql_query_nodata()
    {
        $this->getConnection('shim_test');
        $result = mysql_query("SET @test = 'foo'");

        $this->assertTrue($result);
    }

    public function test_mysql_query_fail()
    {
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT VERSION(');

        $this->assertFalse($result);
    }

    public function test_mysql_unbuffered_query()
    {
        $this->getConnection('shim_test');

        $result = mysql_unbuffered_query('SELECT one, two FROM testing LIMIT 4');
        $this->assertResult($result);
        $i = 0;
        while ($row = mysql_fetch_assoc($result)) {
            $i++;
        }
        $this->assertEquals(4, $i);

        $result = mysql_query('SELECT one, two FROM testing LIMIT 4');
        $this->assertResult($result);
    }

    public function test_mysql_unbuffered_query_fail()
    {
        $this->getConnection();

        $result = mysql_unbuffered_query('SELECT VERSION(');
        $this->assertFalse($result);
    }

    public function test_mysql_unbuffered_query_num_rows()
    {
        $this->getConnection('shim_test');

        $result = mysql_unbuffered_query('SELECT one, two FROM testing');
        $this->assertResult($result);
        $this->assertEquals(0, mysql_num_rows($result));
        mysql_free_result($result);
    }

    /**
     * @requires PHP < 7.0.0
     */
    public function test_mysql_unbuffered_query_close_legacy()
    {
        if (!version_compare(PHP_VERSION, '7.0.0', '<')) {
            $this->markTestIncomplete('PHP < 7.0.0 is required');
        }

        $conn = $this->getConnection('shim_test');

        $result = mysql_unbuffered_query('SELECT one, two FROM testing');
        $this->assertResult($result);
        try {
            mysql_close($conn);
        } catch (\PHPUnit_Framework_Error_Notice $e) {
            $this->assertEquals(
                'mysql_close(): Function called without first fetching all rows from a previous unbuffered query',
                $e->getMessage()
            );
        }
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_unbuffered_query_close()
    {
        $conn = $this->getConnection('shim_test');
        $result = mysql_unbuffered_query('SELECT one, two FROM testing');
        $this->assertResult($result);
        mysql_close($conn);
    }

    public function test_mysql_db_query()
    {
        $this->getConnection();
        $result = mysql_db_query('shim_test', 'SELECT DATABASE()');
        $this->assertResult($result);
        $rows =  mysql_fetch_row($result);
        $this->assertEquals('shim_test', $rows[0]);
        $result = mysql_db_query('mysql', 'SELECT DATABASE()');
        $this->assertResult($result);
        $rows = mysql_fetch_row($result);
        $this->assertEquals('mysql', $rows[0]);
    }

    public function test_mysql_db_query_fail()
    {
        $this->getConnection();
        $result = mysql_db_query('nonexistent', 'SELECT DATABASE()');
        $this->assertFalse($result);
    }

    public function test_mysql_insert_id()
    {
        $this->getConnection('shim_test');
        $result = mysql_query(
            "INSERT INTO
                testing (id, one, two, three, four, five, six, seven, eight, nine, ten, eleven)
             VALUES
                (5, '5', '5', '5', '5', '5', '5', '5', 'five', '5', '5', '5')"
        );
        $this->assertTrue($result);
        $this->assertEquals(5, mysql_insert_id());
    }

    public function test_mysql_list_dbs()
    {
        $this->getConnection();
        $result = mysql_list_dbs();
        $this->assertResult($result);
        while ($row = mysql_fetch_assoc($result)) {
            $this->assertArrayHasKey('Database', $row);
        }
    }

    public function test_mysql_list_tables()
    {
        $this->getConnection();
        $result = mysql_list_tables('mysql');
        $this->assertResult($result);

        while ($row = mysql_fetch_assoc($result)) {
            $this->assertArrayHasKey('Tables_in_mysql', $row);
        }
    }

    /**
     * @requires PHP
     */
    public function test_mysql_list_tables_fail()
    {
        $this->getConnection();
        $result = mysql_list_tables('nonexistent');
        $this->assertFalse($result);
    }

    public function test_mysql_list_tables_specialchars()
    {
        $this->getConnection('shim-test');

        $result = mysql_list_tables('shim-test');
        $this->assertResult($result);
        $i = 0;
        while ($row = mysql_fetch_assoc($result)) {
            $i++;
            $this->assertArrayHasKey('Tables_in_shim-test', $row);
        }
        $this->assertEquals(2, $i);
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_list_fields()
    {
        $mysql = $this->getConnection();

        $result = mysql_list_fields('shim_test', 'testing', $mysql);
        $this->assertResult($result);

        $i = 0;
        while ($row = mysql_fetch_assoc($result)) {
            $i++;

            $this->assertEquals(
                array(
                    'Field',
                    'Type',
                    'Null',
                    'Key',
                    'Default',
                    'Extra'
                ),
                array_keys($row)
            );
        }

        $this->assertEquals(12, $i);
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_list_fields_specialchars()
    {
        $this->getConnection('shim-test');

        mysql_query(
            "CREATE TABLE IF NOT EXISTS `testing-3` (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                three varchar(255),
                four varchar(255),
                five varchar(255),
                six varchar(255),
                seven varchar(255),
                eight varchar(255),
                nine ENUM('one', 'two', '\'three'),
                ten SET('one', 'two', '\'\'three'),
                eleven MEDIUMTEXT,
                INDEX one_idx (one),
                UNIQUE INDEX two_unq (two),
                INDEX three_four_idx (three, four),
                UNIQUE INDEX four_five_unq (four, five),
                INDEX seven_eight_idx (seven, eight),
                UNIQUE INDEX seven_eight_unq (seven, eight),
                PRIMARY KEY (id)
            ) CHARACTER SET latin1;"
        );

        $result = mysql_list_fields('shim-test', 'testing-3');
        $this->assertResult($result);

        $i = 0;
        while ($row = mysql_fetch_assoc($result)) {
            $i++;
            $this->assertEquals(
                array(
                    'Field',
                    'Type',
                    'Null',
                    'Key',
                    'Default',
                    'Extra'
                ),
                array_keys($row)
            );
        }

        $this->assertEquals(12, $i);
    }

    public function test_mysql_list_fields_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessage("mysql_list_fields(): Unable to save MySQL query result");

        $this->getConnection();
        mysql_list_fields('shim_test', 'nonexistent');
    }

    public function test_mysql_field()
    {
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_table($result, 0));
        $this->assertEquals('id', mysql_field_name($result, 0));
        $this->assertEquals('int', mysql_field_type($result, 0));
        $this->assertEquals(11, mysql_field_len($result, 0));
        $this->assertEquals('not_null primary_key auto_increment', mysql_field_flags($result, 0));

        $this->assertEquals('testing', mysql_field_table($result, 1));
        $this->assertEquals('one', mysql_field_name($result, 1));
        $this->assertEquals('string', mysql_field_type($result, 1));
        $this->assertEquals(255, mysql_field_len($result, 1));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 1));

        $this->assertEquals('testing', mysql_field_table($result, 2));
        $this->assertEquals('two', mysql_field_name($result, 2));
        $this->assertEquals('string', mysql_field_type($result, 2));
        $this->assertEquals(255, mysql_field_len($result, 2));
        $this->assertEquals('unique_key', mysql_field_flags($result, 2));

        $this->assertEquals('testing', mysql_field_table($result, 3));
        $this->assertEquals('three', mysql_field_name($result, 3));
        $this->assertEquals('string', mysql_field_type($result, 3));
        $this->assertEquals(255, mysql_field_len($result, 3));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 3));

        $this->assertEquals('testing', mysql_field_table($result, 4));
        $this->assertEquals('four', mysql_field_name($result, 4));
        $this->assertEquals('string', mysql_field_type($result, 4));
        $this->assertEquals(255, mysql_field_len($result, 4));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 4));

        $this->assertEquals('testing', mysql_field_table($result, 5));
        $this->assertEquals('five', mysql_field_name($result, 5));
        $this->assertEquals('string', mysql_field_type($result, 5));
        $this->assertEquals(255, mysql_field_len($result, 5));
        $this->assertEmpty(mysql_field_flags($result, 5));

        $this->assertEquals('testing', mysql_field_table($result, 6));
        $this->assertEquals('six', mysql_field_name($result, 6));
        $this->assertEquals('string', mysql_field_type($result, 6));
        $this->assertEquals(255, mysql_field_len($result, 6));
        $this->assertEmpty(mysql_field_flags($result, 6));

        $this->assertEquals('testing', mysql_field_table($result, 7));
        $this->assertEquals('seven', mysql_field_name($result, 7));
        $this->assertEquals('string', mysql_field_type($result, 7));
        $this->assertEquals(255, mysql_field_len($result, 7));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 7));

        $this->assertEquals('testing', mysql_field_table($result, 8));
        $this->assertEquals('eight', mysql_field_name($result, 8));
        $this->assertEquals('string', mysql_field_type($result, 8));
        $this->assertEquals(255, mysql_field_len($result, 8));
        $this->assertEmpty(mysql_field_flags($result, 8));

        $this->assertEquals('testing', mysql_field_table($result, 9));
        $this->assertEquals('nine', mysql_field_name($result, 9));
        $this->assertEquals('string', mysql_field_type($result, 9));
        $this->assertEquals(6, mysql_field_len($result, 9));
        $this->assertEquals('enum', mysql_field_flags($result, 9));

        $this->assertEquals('testing', mysql_field_table($result, 10));
        $this->assertEquals('ten', mysql_field_name($result, 10));
        $this->assertEquals('string', mysql_field_type($result, 10));
        $this->assertEquals(35, mysql_field_len($result, 10));
        $this->assertEquals('set', mysql_field_flags($result, 10));

        $this->assertEquals('testing', mysql_field_table($result, 11));
        $this->assertEquals('eleven', mysql_field_name($result, 11));
        $this->assertEquals('blob', mysql_field_type($result, 11));
        $this->assertEquals(16777215, mysql_field_len($result, 11));
        $this->assertEquals('blob', mysql_field_flags($result, 11));
    }

    public function test_mysql_field_utf8()
    {
        $this->getConnection('shim_test_utf8', 'utf8');
        $result = mysql_query("SELECT * FROM testing WHERE one = 'one' LIMIT 1");

        $this->assertEquals('testing', mysql_field_table($result, 0));
        $this->assertEquals('id', mysql_field_name($result, 0));
        $this->assertEquals('int', mysql_field_type($result, 0));
        $this->assertEquals(11, mysql_field_len($result, 0));
        $this->assertEquals('not_null primary_key auto_increment', mysql_field_flags($result, 0));

        $this->assertEquals('testing', mysql_field_table($result, 1));
        $this->assertEquals('one', mysql_field_name($result, 1));
        $this->assertEquals('string', mysql_field_type($result, 1));
        $this->assertEquals(255*3, mysql_field_len($result, 1));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 1));

        $this->assertEquals('testing', mysql_field_table($result, 2));
        $this->assertEquals('two', mysql_field_name($result, 2));
        $this->assertEquals('string', mysql_field_type($result, 2));
        $this->assertEquals(255*3, mysql_field_len($result, 2));
        $this->assertEquals('unique_key', mysql_field_flags($result, 2));

        $this->assertEquals('testing', mysql_field_table($result, 3));
        $this->assertEquals('three', mysql_field_name($result, 3));
        $this->assertEquals('string', mysql_field_type($result, 3));
        $this->assertEquals(255*3, mysql_field_len($result, 3));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 3));

        $this->assertEquals('testing', mysql_field_table($result, 4));
        $this->assertEquals('four', mysql_field_name($result, 4));
        $this->assertEquals('string', mysql_field_type($result, 4));
        $this->assertEquals(255*3, mysql_field_len($result, 4));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 4));

        $this->assertEquals('testing', mysql_field_table($result, 5));
        $this->assertEquals('five', mysql_field_name($result, 5));
        $this->assertEquals('string', mysql_field_type($result, 5));
        $this->assertEquals(255*3, mysql_field_len($result, 5));
        $this->assertEmpty(mysql_field_flags($result, 5));

        $this->assertEquals('testing', mysql_field_table($result, 6));
        $this->assertEquals('six', mysql_field_name($result, 6));
        $this->assertEquals('string', mysql_field_type($result, 6));
        $this->assertEquals(255*3, mysql_field_len($result, 6));
        $this->assertEmpty(mysql_field_flags($result, 6));

        $this->assertEquals('testing', mysql_field_table($result, 7));
        $this->assertEquals('seven', mysql_field_name($result, 7));
        $this->assertEquals('string', mysql_field_type($result, 7));
        $this->assertEquals(255*3, mysql_field_len($result, 7));
        $this->assertEquals('multiple_key', mysql_field_flags($result, 7));

        $this->assertEquals('testing', mysql_field_table($result, 8));
        $this->assertEquals('eight', mysql_field_name($result, 8));
        $this->assertEquals('string', mysql_field_type($result, 8));
        $this->assertEquals(255*3, mysql_field_len($result, 8));
        $this->assertEmpty(mysql_field_flags($result, 8));

        $this->assertEquals('testing', mysql_field_table($result, 9));
        $this->assertEquals('nine', mysql_field_name($result, 9));
        $this->assertEquals('string', mysql_field_type($result, 9));
        $this->assertEquals(6*3, mysql_field_len($result, 9));
        $this->assertEquals('enum', mysql_field_flags($result, 9));

        $this->assertEquals('testing', mysql_field_table($result, 10));
        $this->assertEquals('ten', mysql_field_name($result, 10));
        $this->assertEquals('string', mysql_field_type($result, 10));
        $this->assertEquals(35*3, mysql_field_len($result, 10));
        $this->assertEquals('set', mysql_field_flags($result, 10));

        $this->assertEquals('testing', mysql_field_table($result, 11));
        $this->assertEquals('eleven', mysql_field_name($result, 11));
        $this->assertEquals('blob', mysql_field_type($result, 11));
        $this->assertEquals(16777215*3, mysql_field_len($result, 11));
        $this->assertEquals('blob', mysql_field_flags($result, 11));
    }

    public function test_mysql_field_name_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_field_name\(\): )?Field 999 is invalid for MySQL result index .*$/");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_name($result, 999));
    }

    public function test_mysql_field_table_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_field_table\(\): )?Field 999 is invalid for MySQL result index .*$/");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_table($result, 999));
    }

    public function test_mysql_field_type_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_field_type\(\): )?Field 999 is invalid for MySQL result index .*$/");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_type($result, 999));
    }

    public function test_mysql_field_len_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_field_len\(\): )?Field 999 is invalid for MySQL result index .*$/");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_len($result, 999));
    }

    public function test_mysql_field_flags_fail()
    {

        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_field_flags\(\): )?Field 999 is invalid for MySQL result index .*$/");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT * FROM testing LIMIT 1');

        $this->assertEquals('testing', mysql_field_flags($result, 999));
    }

    public function test_mysql_num_fields()
    {
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT one, two FROM testing LIMIT 1');
        $this->assertResult($result);

        $this->assertEquals(2, mysql_num_fields($result));
    }

    public function test_mysql_num_fields_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessage("mysql_num_fields() expects parameter 1 to be resource, boolean given");
        $this->getConnection('shim_test');
        $result = mysql_query('SELECT one, two FROM nonexistent');

        mysql_num_fields($result);
    }

    /**
     * @dataProvider mysql_function_invalid_result_DataProvider
     */
    public function test_mysql_function_invalid_result($function, $error, $args)
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches('@' .$error. '@');

        if ($args !== array()) {
            array_unshift($args, null);
            $this->expectWarningMessageMatches('@' . __FILE__ . ':' .  (__LINE__ + 1) . '@');
            call_user_func_array($function, $args);
        }
        $this->expectWarningMessageMatches('@' . __FILE__ . ':' .  (__LINE__ + 1) . '@');
        call_user_func($function, null);
    }

    public function test_mysql_check_valid_result_backtraces_here()
    {
        $this->expectWarning();
        $this->expectWarningMessage(__FUNCTION__ . "() expects parameter 1 to be resource, boolean given on " . __FILE__ . ':' .  (__LINE__ + 1));
        \Dshafik\MySQL::checkValidResult(false, __FUNCTION__);
    }

    /**
     * @dataProvider mysql_fetch_DataProvider
     */
    public function test_mysql_fetch($function, $results, $resultType = null)
    {
        $this->getConnection('shim_test');

        $result = mysql_query('SELECT one, two FROM testing LIMIT 4');
        $this->assertResult($result);

        $this->assertEquals(count($results), mysql_num_rows($result));

        $function = function ($result) use ($function, $resultType) {
            if ($resultType) {
                return $function($result, $resultType);
            }
            return $function($result);
        };

        $i = 0;
        while ($row = $function($result, $resultType)) {
            $this->assertEquals($results[$i], $row);
            $i++;
        }

        $this->assertEquals(count($results), $i);
    }

    /**
     * @dataProvider mysql_fetch_no_rows_dataProvider
     */
    public function test_mysql_fetch_no_rows($function)
    {
        $this->getConnection('shim_test');
        $result = mysql_query("SELECT * FROM testing WHERE one = 'fail'");

        $this->assertResult($result);
        $this->assertEquals(0, mysql_num_rows($result));
        $this->assertFalse($function($result));
    }

    /**
     * @dataProvider mysql_fetch_object_dataProvider
     */
    public function test_mysql_fetch_object($className, $params, $expectedParams)
    {
        $this->getConnection('shim_test');
        $result = mysql_query("SELECT one, two FROM testing LIMIT 2");

        $fetch = function($result) use ($className, $params) {
            if ($className === null) {
                return mysql_fetch_object($result);
            }

            if ($params === array()) {
                return mysql_fetch_object($result, $className);
            }

            return mysql_fetch_object($result, $className, $params);
        };

        while ($row = $fetch($result)) {
            if ($className === null) {
                $this->assertInstanceOf('stdClass', $row);
            } else {
                $this->assertInstanceOf($className, $row);
            }

            $this->assertNotEmpty($row->one);
            $this->assertNotEmpty($row->two);
            
            if ($expectedParams !== null) {
                $this->assertEquals($expectedParams['foo'], $row->foo);
                $this->assertEquals($expectedParams['bar'], $row->bar);
            }
        }
    }

    public function test_mysql_num_rows()
    {
        $this->getConnection('shim_test');

        $result = mysql_query('SELECT * FROM testing LIMIT 4');
        $this->assertResult($result);
        $this->assertEquals(4, mysql_num_rows($result));
    }

    public function test_mysql_affected_rows()
    {
        $this->getConnection('shim_test');

        $result = mysql_query('UPDATE testing SET one = one + 1000, two = two + 1000 ORDER BY one DESC LIMIT 4');
        $this->assertTrue($result);
        $this->assertEquals(4, mysql_affected_rows());
        $result = mysql_query('UPDATE testing SET one = one - 1000, two = two - 1000 ORDER BY one DESC LIMIT 4');
        $this->assertTrue($result, mysql_error());
        $this->assertEquals(4, mysql_affected_rows());
    }

    public function test_mysql_result()
    {
        $this->getConnection();

        $result = mysql_query('SELECT one, two AS aliased FROM testing');
        $this->assertResult($result);

        for ($i = 0, $max = mysql_num_rows($result); $i < $max; $i++) {
            $this->assertEquals($i+1, mysql_result($result, $i, 0));
            $this->assertEquals($i+1, mysql_result($result, $i, 'one'));
            $this->assertEquals($i+1, mysql_result($result, $i, 1));
            $this->assertEquals($i+1, mysql_result($result, $i, 'aliased'));
        }
    }

    public function test_mysql_result_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_result\(\): )?three not found in MySQL result index (.*?)$/");
        $this->getConnection();

        $result = mysql_query('SELECT one, two FROM testing LIMIT 1');
        $this->assertResult($result);

        mysql_result($result, 0, 'three');
    }

    public function test_mysql_result_prefixed()
    {
        $this->getConnection();

        $result = mysql_query('SELECT one, two FROM testing LIMIT 1');
        $this->assertResult($result);

        $this->assertEquals(1, mysql_result($result, 0, 'testing.one'));
        $this->assertEquals(1, mysql_result($result, 0, 'testing.two'));
    }

    public function test_mysql_result_prefixed_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_result\(\): )?testing.three not found in MySQL result index (.*?)$/");
        $this->getConnection();

        $result = mysql_query('SELECT one, two FROM testing LIMIT 1');
        $this->assertResult($result);

        mysql_result($result, 0, 'testing.three');
    }

    public function test_mysql_result_invalid_row()
    {
        $this->expectWarning();
        $this->expectWarningMessageMatches("/^(mysql_result\(\): )?Unable to jump to row 1 on MySQL result index (.*?)$/");
        $this->getConnection();

        $result = mysql_query('SELECT one FROM testing LIMIT 1');
        $this->assertResult($result);

        mysql_result($result, 1, 0);
    }

    /**
     * @see https://github.com/dshafik/php7-mysql-shim/issues/7
     */
    public function test_mysql_result_multiple_calls()
    {
        $this->getConnection();

        $result = mysql_query('SELECT * FROM testing LIMIT 1');
        $this->assertResult($result);

        $this->assertEquals(1, mysql_result($result, 0, 'testing.one'));
        $this->assertEquals(1, mysql_result($result, 0, 'testing.one'));
    }

    public function test_mysql_close()
    {
        mysql_connect(static::$host, static::$username, static::$password);
        $this->assertTrue(mysql_close());
    }

    public function test_mysql_close_fail()
    {
        $this->expectWarning();
        $this->expectWarningMessage("mysql_close(): no MySQL-Link resource supplied");

        mysql_close();
    }

    public function test_mysql_error()
    {
        $this->getConnection();

        $this->assertEmpty(mysql_error());

        $result = mysql_query('SELECT VERSION(');
        $this->assertFalse($result);

        $this->assertMatchesRegularExpression(
            '@You have an error in your SQL syntax; check the manual that corresponds to your (.*?) ' .
            'server version for the right syntax to use near \'\' at line 1@',
            mysql_error()
        );
    }

    public function test_mysql_errno()
    {
        $this->getConnection();

        $this->assertEmpty(mysql_errno());

        $result = mysql_query('SELECT VERSION(');
        $this->assertFalse($result);

        $this->assertEquals(1064, mysql_errno());
    }

    public function test_mysql_escape_string()
    {
        $this->assertEquals('\\\'\0\Z\r\n\"\\\\safestring', @mysql_escape_string("'\0\032\r\n\"\\safestring"));
    }

    /**
     * @requires PHP 7.0.0
     */
    public function test_mysql_escape_string_notice()
    {
        $this->expectNotice();
        $this->expectNoticeMessage("mysql_escape_string() is insecure; use mysql_real_escape_string() instead!");
        mysql_escape_string("'\0\032\r\n\"\\");
    }

    public function test_mysql_select_db()
    {
        $this->getConnection();

        $this->assertTrue(mysql_select_db('shim_test'));
    }

    public function test_mysql_select_db_specialchars()
    {
        $this->getConnection('shim-test');

        $this->assertTrue(mysql_select_db('shim-test'));
    }

    public function test_mysql_select_db_invalid()
    {
        $this->getConnection();

        $this->assertFalse(mysql_select_db('nonexistent'));
    }

    public function test_mysql_db_name()
    {
        $this->getConnection();
        $dbs = mysql_list_dbs();
        $this->assertEquals('information_schema', mysql_db_name($dbs, 0));
    }

    public function test_mysql_fetch_field()
    {
        $this->getConnection();

        $result = mysql_query(
            "INSERT INTO
                testing (one, two, three, four, five, six, seven, eight, nine, ten, eleven)
             VALUES
                ('six', '6', '6', '6', '6', '6', '6', '6', 'six', 'six', '6')"
        );
        $this->assertTrue($result);

        $result = mysql_query("SELECT * FROM testing WHERE one = 'six' LIMIT 1");
        $this->assertNotFalse($result);

        $map = array(
            0 => array(
                'name' => 'id', 'max_length' => 1, 'not_null' => 1, 'primary_key' => 1,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 1, 'blob' => 0,
                'type' => MYSQLI_TYPE_LONG, 'unsigned' => 0, 'zerofill' => 0
            ),
            1 => array(
                'name' => 'one', 'max_length' => 3, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 1, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            2 => array(
                'name' => 'two', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 1, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            3 => array(
                'name' => 'three', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 1, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            4 => array(
                'name' => 'four', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 1, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            5 => array(
                'name' => 'five', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            6 => array(
                'name' => 'six', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            7 => array(
                'name' => 'seven', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 1, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            8 => array(
                'name' => 'eight', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_VAR_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            9 => array(
                'name' => 'nine', 'max_length' => 3, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            10 => array(
                'name' => 'ten', 'max_length' => 3, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 0,
                'type' => MYSQLI_TYPE_STRING, 'unsigned' => 0, 'zerofill' => 0
            ),
            11 => array(
                'name' => 'eleven', 'max_length' => 1, 'not_null' => 0, 'primary_key' => 0,
                'unique_key' => 0, 'multiple_key' => 0, 'numeric' => 0, 'blob' => 1,
                'type' => MYSQLI_TYPE_BLOB, 'unsigned' => 0, 'zerofill' => 0
            ),
        );

        foreach ($map as $index => $values) {
            $field = mysql_fetch_field($result, $index);
            $this->assertInstanceOf(\stdClass::class, $field);

            foreach ($values as $key => $value) {
                $this->assertEquals($field->{$key}, $value, "Field '$index:$key' doesn't match.  Expected: $value, Actual: {$field->{$key}}");
            }
        }
    }

    public function test_mysql_fetch_field_fail_false()
    {
        $this->expectWarning();
        $this->expectWarningMessage("mysql_fetch_field() expects parameter 1 to be resource, boolean given");

        $this->getConnection();
        mysql_fetch_field(false, 1);
    }

    public function test_mysql_fetch_field_fail_invalid()
    {
        // $this->expectWarning();
        // $this->expectWarningMessage("mysql_fetch_field() expects parameter 1 to be resource, boolean given");

        $this->getConnection();

        $result = mysql_query(
            "INSERT INTO
                testing (one, two, three, four, five, six, seven, eight, nine, ten, eleven)
             VALUES
                ('six', '6', '6', '6', '6', '6', '6', '6', 'six', 'six', '6')"
        );

        $result = mysql_query("SELECT * FROM testing WHERE one = 'six' LIMIT 1");
        $this->assertNotFalse($result);
        
        for ($i = 0; $i <= 12; $i++) {
            $field = mysql_fetch_field($result, $i);
        }
        $this->assertFalse($field);
    }

    public function test_mysql_list_processes()
    {
        $this->getConnection();

        $result = mysql_list_processes();

        while ($row = mysql_fetch_assoc($result)) {
            $this->assertArrayHasKey('Id', $row);
            $this->assertArrayHasKey('Host', $row);
            $this->assertArrayHasKey('db', $row);
            $this->assertArrayHasKey('Command', $row);
            $this->assertArrayHasKey('Time', $row);
        }
    }

    public function tear_down()
    {
        @mysql_close();
    }

    public static function tear_down_after_class()
    {
        mysql_connect(static::$host, static::$username, static::$password);
        foreach (self::$dbs as $db) {
            mysql_query("DROP DATABASE IF EXISTS `$db`");
        }
    }

    public function mysql_fetch_DataProvider()
    {
        $numeric = array(
            array('1', '1'),
            array('2', '2'),
            array('3', '3'),
            array('4', '4'),
        );

        $assoc = array(
            array('one' => '1', 'two' => '1'),
            array('one' => '2', 'two' => '2'),
            array('one' => '3', 'two' => '3'),
            array('one' => '4', 'two' => '4'),
        );

        $array = array(
            array('1', '1', 'one' => '1', 'two' => '1'),
            array('2', '2', 'one' => '2', 'two' => '2'),
            array('3', '3', 'one' => '3', 'two' => '3'),
            array('4', '4', 'one' => '4', 'two' => '4'),
        );

        $object = array(
            (object) array('one' => '1', 'two' => '1'),
            (object) array('one' => '2', 'two' => '2'),
            (object) array('one' => '3', 'two' => '3'),
            (object) array('one' => '4', 'two' => '4'),
        );

        return array(
            array(
                'function' => 'mysql_fetch_array',
                'results' => $assoc,
                'resultType' => MYSQL_ASSOC
            ),
            array(
                'function' => 'mysql_fetch_array',
                'results' => $array,
                'resultType' => MYSQL_BOTH
            ),
            array(
                'function' => 'mysql_fetch_array',
                'results' => $numeric,
                'resultType' => MYSQL_NUM
            ),
            array(
                'function' => 'mysql_fetch_assoc',
                'results' => $assoc
            ),
            array(
                'function' => 'mysql_fetch_row',
                'results' => $numeric
            ),
            array(
                'function' => 'mysql_fetch_object',
                'results' => $object,
            )
        );
    }

    public function mysql_fetch_no_rows_dataProvider()
    {
        return array(
            array(
                'function' => 'mysql_fetch_array',
            ),
            array(
                'function' => 'mysql_fetch_assoc',
            ),
            array(
                'function' => 'mysql_fetch_row',
            ),
            array(
                'function' => 'mysql_fetch_object',
            ),
        );
    }

    public function mysql_fetch_object_dataProvider()
    {
        return array(
            array(
                'class' => null,
                'params' => array(),
                'expectedParams' => null,
            ),
            array(
                'class' => '\Dshafik\MySQL\Tests\TestResult',
                'params' => array(),
                'expectedParams' => array(
                    'foo' => TestResult::DEFAULT_PARAM_VALUE,
                    'bar' => TestResult::DEFAULT_PARAM_VALUE,
                )
            ),
            array(
                'class' => '\Dshafik\MySQL\Tests\TestResult',
                'params' => array(TestResult::SET_VALUE, TestResult::SET_VALUE + 1),
                'expectedParams' => array(
                    'foo' => TestResult::SET_VALUE,
                    'bar' => TestResult::SET_VALUE + 1
                )
            )
        );
    }

    public function mysql_function_invalid_result_DataProvider()
    {
        return array(
            array(
                'function' => 'mysql_result',
                'message' => "mysql_result\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_num_rows',
                'message' => "mysql_num_rows\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(),
            ),
            array(
                'function' => 'mysql_num_fields',
                'message' => "mysql_num_fields\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(),
            ),
            array(
                'function' => 'mysql_fetch_row',
                'message' => "mysql_fetch_row\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(),
            ),
            array(
                'function' => 'mysql_fetch_array',
                'message' => "mysql_fetch_array\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(),
            ),
            array(
                'function' => 'mysql_fetch_assoc',
                'message' => "mysql_fetch_assoc\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(),
            ),
            array(
                'function' => 'mysql_fetch_object',
                'message' => "(mysql_fetch_object\(\): )?supplied argument is not a valid MySQL result resource",
                'args' => array('StdClass')
            ),
            array(
                'function' => 'mysql_data_seek',
                'message' => "mysql_data_seek\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_fetch_lengths',
                'message' => "mysql_fetch_lengths\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array()
            ),
            array(
                'function' => 'mysql_fetch_field',
                'message' => "mysql_fetch_field\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array()
            ),
            array(
                'function' => 'mysql_field_seek',
                'message' => "mysql_field_seek\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_free_result',
                'message' => "mysql_free_result\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array()
            ),
            array(
                'function' => 'mysql_field_name',
                'message' => "mysql_field_name\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_field_table',
                'message' => "mysql_field_table\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_field_len',
                'message' => "mysql_field_len\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_field_type',
                'message' => "mysql_field_type\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_field_flags',
                'message' => "mysql_field_flags\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0)
            ),
            array(
                'function' => 'mysql_db_name',
                'message' => "mysql_db_name\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0),
            ),
            array(
                'function' => 'mysql_tablename',
                'message' => "mysql_tablename\(\) expects parameter 1 to be resource, (null|NULL) given",
                'args' => array(0),            ),
        );
    }

    /**
     * @param $result
     */
    protected function assertResult($result)
    {
        $this->assertTrue(
            is_resource($result) && get_resource_type($result) === 'mysql result'
            || $result instanceof \mysqli_result,
            mysql_error()
        );
    }

    protected function getConnection($db = 'shim_test', $encoding = 'latin1')
    {
        self::$dbs[$db] = $db;

        $mysql = mysql_connect(static::$host, static::$username, static::$password);
        $this->assertConnection($mysql);

        mysql_query('SET NAMES ' . $encoding);

        $result = mysql_query("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $encoding;");
        $this->assertTrue($result);
        $result = mysql_select_db($db);
        $this->assertTrue($result);
        mysql_query(
            "CREATE TABLE IF NOT EXISTS testing (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                three varchar(255),
                four varchar(255),
                five varchar(255),
                six varchar(255),
                seven varchar(255),
                eight varchar(255),
                nine ENUM('one', 'two', '\'three', 'three', 'four', 'five', 'six'),
                ten SET('one', 'two', '\'\'three', 'three', 'four', 'five', 'six'),
                eleven MEDIUMTEXT,
                INDEX one_idx (one),
                UNIQUE INDEX two_unq (two),
                INDEX three_four_idx (three, four),
                UNIQUE INDEX four_five_unq (four, five),
                INDEX seven_eight_idx (seven, eight),
                UNIQUE INDEX seven_eight_unq (seven, eight),
                PRIMARY KEY (id)
            ) CHARACTER SET $encoding;"
        );

        mysql_query(
            "CREATE TABLE IF NOT EXISTS testing2 (
                id int AUTO_INCREMENT,
                one varchar(255),
                two varchar(255),
                three varchar(255),
                four varchar(255),
                five varchar(255),
                six varchar(255),
                seven varchar(255),
                eight varchar(255),
                nine ENUM('one', 'two', '\'three', 'three', 'four'),
                ten SET('one', 'two', '\'\'three', 'three', 'four'),
                eleven MEDIUMTEXT,
                INDEX one_idx (one),
                UNIQUE INDEX two_unq (two),
                INDEX three_four_idx (three, four),
                UNIQUE INDEX four_five_unq (four, five),
                INDEX seven_eight_idx (seven, eight),
                UNIQUE INDEX seven_eight_unq (seven, eight),
                PRIMARY KEY (id)
            ) CHARACTER SET $encoding;"
        );

        if ($db !== null) {
            $this->assertTrue(mysql_select_db($db));
        }

        return $mysql;
    }

    /**
     * @param $mysql
     */
    protected function assertConnection($mysql)
    {
        $this->assertTrue(
            is_resource($mysql) && get_resource_type($mysql) === 'mysql link'
            ||
            $mysql instanceof \mysqli,
            'Not a valid MySQL connection'
        );
    }
}

class TestResult {
    const DEFAULT_VALUE = 1;
    const DEFAULT_PARAM_VALUE = 2;
    const SET_VALUE = 3;

    public $foo = self::DEFAULT_VALUE;
    public $bar = self::DEFAULT_VALUE;

    public function __construct($foo = self::DEFAULT_PARAM_VALUE, $bar = self::DEFAULT_PARAM_VALUE)
    {
        $this->foo = $foo;
        $this->bar = $bar;
    }
}