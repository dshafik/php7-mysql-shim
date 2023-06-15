<?php

/**
 * php7-mysql-shim
 *
 * @author Davey Shafik <me@daveyshafik.com>
 * @copyright Copyright (c) 2017 Davey Shafik
 * @license MIT License
 * @link https://github.com/dshafik/php7-mysql-shim
 */

/**
 * A drop-in replacement for ext/mysql in PHP 7+ using ext/mysqli instead
 *
 * This library is meant to be a _stop-gap_. It will be slower than using
 * the native functions directly.
 *
 * You should switch to ext/pdo_mysql or ext/mysqli, and migrate to prepared
 * queries (@see http://php.net/manual/en/pdo.prepared-statements.php) to
 * ensure you are securely interacting with your database.
 */
namespace {
    use Dshafik\MySQL;

    if (!extension_loaded('mysql')) {
        if (!extension_loaded('mysqli')) {
            trigger_error('php7-mysql-shim: ext/mysqli is required', E_USER_ERROR);
        }

        define('MYSQL_ASSOC', 1);
        define('MYSQL_NUM', 2);
        define('MYSQL_BOTH', 3);
        define('MYSQL_CLIENT_COMPRESS', 32);
        define('MYSQL_CLIENT_SSL', 2048);
        define('MYSQL_CLIENT_INTERACTIVE', 1024);
        define('MYSQL_CLIENT_IGNORE_SPACE', 256);

        function mysql_connect(
            $hostname = null,
            $username = null,
            $password = null,
            $new = false,
            $flags = 0
        ) {
            if ($new !== false) {
                trigger_error('Argument $new is no longer supported in PHP > 7', E_USER_WARNING);
            }

            if (null === $hostname) {
                $hostname = ini_get('mysqli.default_host') ?: null;
            }
            if (null === $username) {
                $username = ini_get('mysqli.default_user') ?: null;
            }
            if (null === $password) {
                $password = ini_get('mysqli.default_pw') ?: null;
            }

            $socket = '';
            if (strpos($hostname, ':/') === 0) {
                // it's a unix socket
                $socket = $hostname;
                $hostname = 'localhost';
            }

            $hash = sha1($hostname . $username . $flags);
            /* persistent connections start with p: */
            /* don't use a cached link for those */
            if ($hostname[1] !== ':' && isset(MySQL::$connections[$hash])) {
                MySQL::$last_connection = MySQL::$connections[$hash]['conn'];
                MySQL::$connections[$hash]['refcount'] += 1;
                return MySQL::$connections[$hash]['conn'];
            }

            /* A custom port can be specified by appending the hostname with :{port} e.g. hostname:3307 */
            if (preg_match('/^(.+):([\d]+)$/', $hostname, $port_matches) === 1 && $port_matches[1] !== "p") {
                $hostname = $port_matches[1];
                $port = (int) $port_matches[2];
            } else {
                $port = null;
            }

            /* No flags, means we can use mysqli_connect() */
            if ($flags === 0) {
                $conn = mysqli_connect($hostname, $username, $password, '', $port);
                if (!$conn instanceof mysqli) {
                    return false;
                }
                MySQL::$last_connection = $conn;
                if (class_exists('WeakMap')) {
                  if (is_null(MySQL::$conn_hash_weakmap)) {
                    MySQL::$conn_hash_weakmap = new WeakMap();
                  }
                  MySQL::$conn_hash_weakmap[$conn] = $hash;
                } else {
                  $conn->hash = $hash; // @phpstan-ignore-line
                }
                MySQL::$connections[$hash] = array('refcount' => 1, 'conn' => $conn);

                return $conn;
            }

            /* Flags means we need to use mysqli_real_connect() instead, and handle exceptions */
            try {
                MySQL::$last_connection = $conn = mysqli_init();

                mysqli_real_connect(
                    $conn,
                    $hostname,
                    $username,
                    $password,
                    '',
                    $port,
                    $socket,
                    $flags
                );

                // @codeCoverageIgnoreStart
                // PHPUnit turns the warning from mysqli_real_connect into an exception, so this never runs
                if ($conn === false) {
                    return false;
                }
                // @codeCoverageIgnoreEnd

                if (class_exists('WeakMap')) {
                  if (is_null(MySQL::$conn_hash_weakmap)) {
                    MySQL::$conn_hash_weakmap = new WeakMap();
                  }
                  MySQL::$conn_hash_weakmap[$conn] = $hash;
                } else {
                  $conn->hash = $hash; // @phpstan-ignore-line
                }
                MySQL::$connections[$hash] = array('refcount' => 1, 'conn' => $conn);

                return $conn;
            } catch (Throwable $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                // @codeCoverageIgnoreStart
                // PHPUnit turns the warning into an exception, so this never runs
                return false;
                // @codeCoverageIgnoreEnd
            }
        }

        function mysql_pconnect(
            $hostname = null,
            $username = null,
            $password = null,
            $flags = 0
        ) {
            $hostname = 'p:' . $hostname;
            return mysql_connect($hostname, $username, $password, false, $flags);
        }

        function mysql_close(mysqli $link = null)
        {
            $isDefault = ($link === null);

            $link = MySQL::getConnection($link, __FUNCTION__);
            if ($link === null) {
                // @codeCoverageIgnoreStart
                // PHPUnit Warning -> Exception
                return false;
                // @codeCoverageIgnoreEnd
            }

            if (class_exists('WeakMap')) {
              if (is_null(MySQL::$conn_hash_weakmap)) {
                MySQL::$conn_hash_weakmap = new WeakMap();
              }
              $link_hash = MySQL::$conn_hash_weakmap[$link];
            } else {
                $link_hash = $link->hash;
            }

            if (isset(MySQL::$connections[$link_hash])) {
                MySQL::$connections[$link_hash]['refcount'] -= 1;
            }

            $return = true;
            if (MySQL::$connections[$link_hash]['refcount'] === 0) {
                $return = mysqli_close($link);
                unset(MySQL::$connections[$link_hash]);
            }

            if ($isDefault) {
                Dshafik\MySQL::$last_connection = null;
            }

            return $return;
        }

        function mysql_select_db($databaseName, mysqli $link = null)
        {
            $link = MySQL::getConnection($link);

            return mysqli_query(
                $link,
                'USE `' . mysqli_real_escape_string($link, $databaseName) . '`'
            ) !== false;
        }

        function mysql_query($query, mysqli $link = null)
        {
            return mysqli_query(MySQL::getConnection($link), $query);
        }

        function mysql_unbuffered_query($query, mysqli $link = null)
        {
            $link = MySQL::getConnection($link);
            if (mysqli_real_query($link, $query)) {
                return mysqli_use_result($link);
            }

            return false;
        }

        function mysql_db_query($databaseName, $query, mysqli $link = null)
        {
            if (mysql_select_db($databaseName, $link)) {
                return mysql_query($query, $link);
            }
            return false;
        }

        function mysql_list_dbs(mysqli $link = null)
        {
            return mysql_query('SHOW DATABASES', $link);
        }

        function mysql_list_tables($databaseName, mysqli $link = null)
        {
            $link = MySQL::getConnection($link);
            $query = sprintf(
                'SHOW TABLES FROM `%s`',
                mysql_real_escape_string($databaseName, $link)
            );
            return mysql_query($query, $link);
        }

        function mysql_list_fields($databaseName, $tableName, mysqli $link = null)
        {
            $link = MySQL::getConnection($link);

            $query = sprintf(
                'SHOW COLUMNS FROM `%s`.`%s`',
                mysqli_real_escape_string($link, $databaseName),
                mysqli_real_escape_string($link, $tableName)
            );

            $result = mysqli_query($link, $query);

            if ($result instanceof mysqli_result) {
                $result->table = $tableName; // @phpstan-ignore-line
                return $result;
            }

            trigger_error('mysql_list_fields(): Unable to save MySQL query result', E_USER_WARNING);
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        function mysql_list_processes(mysqli $link = null)
        {
            return mysql_query('SHOW PROCESSLIST', $link);
        }

        function mysql_error(mysqli $link = null)
        {
            return mysqli_error(MySQL::getConnection($link));
        }

        function mysql_errno(mysqli $link = null)
        {
            return mysqli_errno(MySQL::getConnection($link));
        }

        function mysql_affected_rows(mysqli $link = null)
        {
            return mysqli_affected_rows(MySQL::getConnection($link));
        }

        function mysql_insert_id($link = null) /*|*/
        {
            return mysqli_insert_id(MySQL::getConnection($link));
        }

        function mysql_result($result, $row, $field = 0)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            if (!mysqli_data_seek($result, $row)) {
                trigger_error(
                    sprintf(
                        'mysql_result(): Unable to jump to row %d on MySQL result index %s',
                        $row,
                        spl_object_hash($result)
                    ),
                    E_USER_WARNING
                );
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            $found = true;
            if (strpos($field, '.') !== false) {
                list($table, $name) = explode('.', $field);
                $i = 0;
                $found = false;
                mysqli_field_seek($result, 0);
                while ($column = mysqli_fetch_field($result)) {
                    if ($column->table === $table && $column->name === $name) {
                        $field = $i;
                        $found = true;
                        break;
                    }
                    $i++;
                }
            }

            $row = mysql_fetch_array($result);
            if ($found && array_key_exists($field, $row)) {
                return $row[$field];
            }

            trigger_error(
                sprintf(
                    '%s(): %s not found in MySQL result index %s',
                    __FUNCTION__,
                    $field,
                    spl_object_hash($result)
                ),
                E_USER_WARNING
            );
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        function mysql_num_rows($result)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            $previous = error_reporting(0);
            $rows = mysqli_num_rows($result);
            error_reporting($previous);

            return $rows;
        }

        function mysql_num_fields($result)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_num_fields($result);
        }

        function mysql_fetch_row($result)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_fetch_row($result) ?: false;
        }

        function mysql_fetch_array($result, $resultType = MYSQL_BOTH)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_fetch_array($result, $resultType) ?: false;
        }

        function mysql_fetch_assoc($result) /* : array|null */
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            return mysqli_fetch_assoc($result) ?: false;
        }

        function mysql_fetch_object($result, $class = null, array $params = array()) /* : object|null */
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            if ($class === null) {
                $object = mysqli_fetch_object($result);
            } else {
                $object = mysqli_fetch_object($result, $class, $params);
            }

            return $object ?: false;
        }

        function mysql_data_seek($result, $offset)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_data_seek($result, $offset);
        }

        function mysql_fetch_lengths($result) /* : array|*/
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_fetch_lengths($result);
        }

        function mysql_fetch_field($result, $field_offset = null) /* : object|*/
        {
            static $fields = array();

            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            $result_hash = spl_object_hash($result);
            if ($field_offset === null) {
                $fields[$result_hash][] = true;
                $res = mysqli_fetch_field($result);
            } elseif ($field_offset > mysqli_num_fields($result)) {
                trigger_error('mysql_fetch_field(): Bad field offset', E_USER_WARNING);
                return false;
            } else {
                $i = 0;
                if (isset($fields[$result_hash])) {
                    $i = count($fields[$result_hash]);
                }

                while ($i <= $field_offset) {
                    $res = mysqli_fetch_field($result);

                    if ($res === false) {
                        return false;
                    }

                    $fields[$result_hash][$i] = true;
                    $i++;
                }
            }

            if (isset($res) && $res instanceof stdClass) {
                $res->not_null = ($res->flags & MYSQLI_NOT_NULL_FLAG) ? 1 : 0;
                $res->primary_key = ($res->flags & MYSQLI_PRI_KEY_FLAG) ? 1 : 0;
                $res->unique_key = ($res->flags & MYSQLI_UNIQUE_KEY_FLAG) ? 1 : 0;
                $res->multiple_key = ($res->flags & MYSQLI_MULTIPLE_KEY_FLAG) ? 1 : 0;
                $res->numeric = ($res->flags & MYSQLI_NUM_FLAG) ? 1 : 0;
                $res->blob = ($res->flags & MYSQLI_BLOB_FLAG) ? 1 : 0;
                $res->unsigned = ($res->flags & MYSQLI_UNSIGNED_FLAG) ? 1 : 0;
                $res->zerofill = ($res->flags & MYSQLI_ZEROFILL_FLAG) ? 1 : 0;

                switch ($res->type) {
                    case MYSQLI_TYPE_CHAR:
                        $res->type = 'tinyint';
                        break;
                    case MYSQLI_TYPE_SHORT:
                        $res->type = 'smallint';
                        break;
                    case MYSQLI_TYPE_DECIMAL:
                        $res->type = 'decimal';
                        break;
                    case MYSQLI_TYPE_LONG:
                        $res->type = 'int';
                        break;
                    case MYSQLI_TYPE_FLOAT:
                        $res->type = 'float';
                        break;
                    case MYSQLI_TYPE_DOUBLE:
                        $res->type = 'double';
                        break;
                    case MYSQLI_TYPE_NULL:
                        $res->type = 'null';
                        break;
                    case MYSQLI_TYPE_TIMESTAMP:
                        $res->type = 'timestamp';
                        break;
                    case MYSQLI_TYPE_LONGLONG:
                        $res->type = 'bigint';
                        break;
                    case MYSQLI_TYPE_INT24:
                        $res->type = 'mediumint';
                        break;
                    case MYSQLI_TYPE_NEWDATE:
                    case MYSQLI_TYPE_DATE:
                        $res->type = 'date';
                        break;
                    case MYSQLI_TYPE_TIME:
                        $res->type = 'time';
                        break;
                    case MYSQLI_TYPE_DATETIME:
                        $res->type = 'datetime';
                        break;
                    case MYSQLI_TYPE_YEAR:
                        $res->type = 'year';
                        break;
                    case MYSQLI_TYPE_BIT:
                        $res->type = 'bit';
                        break;
                    case MYSQLI_TYPE_ENUM:
                        $res->type = 'enum';
                        break;
                    case MYSQLI_TYPE_SET:
                        $res->type = 'set';
                        break;
                    case MYSQLI_TYPE_TINY_BLOB:
                        $res->type = 'tinyblob';
                        break;
                    case MYSQLI_TYPE_MEDIUM_BLOB:
                        $res->type = 'mediumblob';
                        break;
                    case MYSQLI_TYPE_LONG_BLOB:
                        $res->type = 'longblob';
                        break;
                    case MYSQLI_TYPE_BLOB:
                        $res->type = 'blob';
                        break;
                    case MYSQLI_TYPE_STRING:
                    case MYSQLI_TYPE_VAR_STRING:
                        $res->type = 'string';
                        break;
                    case MYSQLI_TYPE_GEOMETRY:
                        $res->type = 'geometry';
                        break;
                    case MYSQLI_TYPE_NEWDECIMAL:
                        $res->type = 'numeric';
                        break;
                }

                return $res;
            }

            return false;
        }

        function mysql_field_seek($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return mysqli_field_seek($result, $field);
        }

        function mysql_free_result($result)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            mysqli_free_result($result);
            return null;
        }

        function mysql_field_name($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return MySQL::mysqlFieldInfo($result, $field, 'name');
        }

        function mysql_field_table($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return MySQL::mysqlFieldInfo($result, $field, 'table');
        }

        function mysql_field_len($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return MySQL::mysqlFieldInfo($result, $field, 'length');
        }

        function mysql_field_type($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return MySQL::mysqlFieldInfo($result, $field, 'type');
        }

        function mysql_field_flags($result, $field)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
            return MySQL::mysqlFieldInfo($result, $field, 'flags');
        }

        function mysql_escape_string($unescapedString)
        {
            if (MySQL::$last_connection === null) {
                trigger_error(
                    sprintf(
                        '%s() is insecure; use mysql_real_escape_string() instead!',
                        __FUNCTION__
                    )
                );

                return MySQL::escapeString($unescapedString);
            }
            return mysql_real_escape_string($unescapedString);
        }

        function mysql_real_escape_string($unescapedString, mysqli $link = null)
        {
            return mysqli_real_escape_string(MySQL::getConnection($link), $unescapedString);
        }

        function mysql_stat(mysqli $link = null)
        {
            return mysqli_stat(MySQL::getConnection($link));
        }

        function mysql_thread_id(mysqli $link = null)
        {
            return mysqli_thread_id(MySQL::getConnection($link));
        }

        function mysql_client_encoding(mysqli $link = null)
        {
            return mysqli_character_set_name(MySQL::getConnection($link));
        }

        function mysql_ping(mysqli $link = null)
        {
            return mysqli_ping(MySQL::getConnection($link));
        }

        function mysql_get_client_info(mysqli $link = null)
        {
            return mysqli_get_client_info(MySQL::getConnection($link));
        }

        function mysql_get_host_info(mysqli $link = null)
        {
            return mysqli_get_host_info(MySQL::getConnection($link));
        }

        function mysql_get_proto_info(mysqli $link = null)
        {
            return mysqli_get_proto_info(MySQL::getConnection($link));
        }

        function mysql_get_server_info(mysqli $link = null)
        {
            return mysqli_get_server_info(MySQL::getConnection($link));
        }

        function mysql_info(mysqli $link = null)
        {
            return mysqli_info(MySQL::getConnection($link));
        }

        function mysql_set_charset($charset, mysqli $link = null)
        {
            return mysqli_set_charset(MySQL::getConnection($link), $charset);
        }

        function mysql_db_name($result, $row, $field = 0)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            // Alias as per https://github.com/php/php-src/blob/PHP-5.6/ext/mysql/php_mysql.c#L319
            return mysql_result($result, $row, $field);
        }

        function mysql_tablename($result, $row)
        {
            if (!MySQL::checkValidResult($result, __FUNCTION__)) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }

            // Alias as per http://lxr.php.net/xref/PHP_5_6/ext/mysql/php_mysql.c#321
            return mysql_result($result, $row, 'Table');
        }

        /* Aliases */

        function mysql_fieldname($result, $field)
        {
            return mysql_field_name($result, $field);
        }

        function mysql_fieldtable($result, $field)
        {
            return mysql_field_table($result, $field);
        }

        function mysql_fieldlen($result, $field)
        {
            return mysql_field_len($result, $field);
        }

        function mysql_fieldtype($result, $field)
        {
            return mysql_field_type($result, $field);
        }

        function mysql_fieldflags($result, $field)
        {
            return mysql_field_flags($result, $field);
        }

        function mysql_selectdb($databaseName, $link = null)
        {
            return mysql_select_db($databaseName, $link);
        }

        function mysql_freeresult($result)
        {
            return mysql_free_result($result);
        }

        function mysql_numfields($result)
        {
            return mysql_num_fields($result);
        }

        function mysql_numrows($result)
        {
            return mysql_num_rows($result);
        }

        function mysql_listdbs($link)
        {
            return mysql_list_dbs($link);
        }

        function mysql_listtables($databaseName, $link = null)
        {
            return mysql_list_tables($databaseName, $link);
        }

        function mysql_listfields($databaseName, $tableName, $link = null)
        {
            return mysql_list_fields($databaseName, $tableName, $link);
        }

        function mysql_dbname($result, $row, $field = 0)
        {
            return mysql_db_name($result, $row, $field);
        }

        function mysql_table_name($result, $row)
        {
            return mysql_tablename($result, $row);
        }
    }
}

namespace Dshafik {

    use Exception;
    use mysqli_result;

    class MySQL
    {
        public static $last_connection;
        public static $connections = array();
        /**
         * @var null|\WeakMap
         */
        public static $conn_hash_weakmap = null;

        public static function getConnection($link = null, $func = null)
        {
            if ($link !== null) {
                return $link;
            }

            if (static::$last_connection === null) {
                $err = 'A link to the server could not be established';
                if ($func !== null) {
                    $err = $func . '(): no MySQL-Link resource supplied';
                }
                trigger_error($err, E_USER_WARNING);
                return false;
            }

            return static::$last_connection;
        }

        public static function mysqlFieldInfo(mysqli_result $result, $field, $what)
        {
            try {
                $field = mysqli_fetch_field_direct($result, $field);

                if ($field === false) {
                    return false;
                }
            } catch (Exception $e) {
                trigger_error(
                    sprintf(
                        'mysql_field_%s(): Field %d is invalid for MySQL result index %s',
                        ($what !== 'length') ? $what : 'len',
                        $field,
                        spl_object_hash($result)
                    ),
                    E_USER_WARNING
                );
                // @codeCoverageIgnoreStart
                // PHPUnit turns the warning into an exception, so this never runs
                return false;
                // @codeCoverageIgnoreEnd
            }

            if ($what === 'type') {
                return static::getFieldType($field->type);
            }

            if ($what === 'flags') {
                return static::getFieldFlags($field->flags);
            }

            if (isset($field->{$what})) {
                return $field->{$what};
            }

            return false;
        }

        public static function checkValidResult($result, $function)
        {
            if (!($result instanceof mysqli_result)) {
                $type = strtolower(gettype($result));
                $file = "";
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $backtraceIndex = 0;

                /**
                 * Iterate through backtrace until finding a backtrace with an origin
                 * Some methods may not leave file and line metadata like call_user_func_array and __call
                 */
                do {
                    $currentBacktrace = $backtrace[$backtraceIndex];
                    $callerHasFileAndLine = isset($currentBacktrace['file'], $currentBacktrace['line']);

                    if ($callerHasFileAndLine && $currentBacktrace['file'] !== __FILE__) {
                        $file = $currentBacktrace['file'] . ':' . $currentBacktrace['line'];
                    }
                } while ($backtraceIndex++ < count($backtrace) && $file === "");

                if ($function !== 'mysql_fetch_object') {
                    trigger_error(
                        "$function() expects parameter 1 to be resource, $type given on $file",
                        E_USER_WARNING
                    );
                }

                if ($function === 'mysql_fetch_object') {
                    trigger_error(
                        "$function(): supplied argument is not a valid MySQL result resource on $file",
                        E_USER_WARNING
                    );
                }
                return false;
            }

            return true;
        }

        public static function escapeString($unescapedString)
        {
            $escapedString = '';
            for ($i = 0, $max = strlen($unescapedString); $i < $max; $i++) {
                $escapedString .= self::escapeChar($unescapedString[$i]);
            }

            return $escapedString;
        }

        protected static function getFieldFlags($what)
        {
            // Order of flags taken from http://lxr.php.net/xref/PHP_5_6/ext/mysql/php_mysql.c#2507
            $flags = array(
                MYSQLI_NOT_NULL_FLAG => 'not_null',
                MYSQLI_PRI_KEY_FLAG => 'primary_key',
                MYSQLI_UNIQUE_KEY_FLAG => 'unique_key',
                MYSQLI_MULTIPLE_KEY_FLAG => 'multiple_key',
                MYSQLI_BLOB_FLAG => 'blob',
                MYSQLI_UNSIGNED_FLAG => 'unsigned',
                MYSQLI_ZEROFILL_FLAG => 'zerofill',
                MYSQLI_BINARY_FLAG => 'binary',
                MYSQLI_ENUM_FLAG => 'enum',
                MYSQLI_SET_FLAG => 'set',
                MYSQLI_AUTO_INCREMENT_FLAG => 'auto_increment',
                MYSQLI_TIMESTAMP_FLAG => 'timestamp',
            );

            $fieldFlags = array();
            foreach ($flags as $flag => $value) {
                if ($what & $flag) {
                    $fieldFlags[] = $value;
                }
            }

            return implode(' ', $fieldFlags);
        }

        protected static function getFieldType($what)
        {
            $types = array(
                MYSQLI_TYPE_STRING => 'string',
                MYSQLI_TYPE_VAR_STRING => 'string',
                MYSQLI_TYPE_ENUM => 'string',
                MYSQLI_TYPE_SET => 'string',

                MYSQLI_TYPE_LONG => 'int',
                MYSQLI_TYPE_TINY => 'int',
                MYSQLI_TYPE_SHORT => 'int',
                MYSQLI_TYPE_INT24 => 'int',
                MYSQLI_TYPE_LONGLONG => 'int',

                MYSQLI_TYPE_DECIMAL => 'real',
                MYSQLI_TYPE_FLOAT => 'real',
                MYSQLI_TYPE_DOUBLE => 'real',
                MYSQLI_TYPE_NEWDECIMAL => 'real',

                MYSQLI_TYPE_TINY_BLOB => 'blob',
                MYSQLI_TYPE_MEDIUM_BLOB => 'blob',
                MYSQLI_TYPE_LONG_BLOB => 'blob',
                MYSQLI_TYPE_BLOB => 'blob',

                MYSQLI_TYPE_NEWDATE => 'date',
                MYSQLI_TYPE_DATE => 'date',
                MYSQLI_TYPE_TIME => 'time',
                MYSQLI_TYPE_YEAR => 'year',
                MYSQLI_TYPE_DATETIME => 'datetime',
                MYSQLI_TYPE_TIMESTAMP => 'timestamp',

                MYSQLI_TYPE_NULL => 'null',

                MYSQLI_TYPE_GEOMETRY => 'geometry',
            );

            return isset($types[$what]) ? $types[$what] : 'unknown';
        }

        protected static function escapeChar($char)
        {
            switch ($char) {
                case "\0":
                    $esc = "\\0";
                    break;
                case "\n":
                    $esc = "\\n";
                    break;
                case "\r":
                    $esc = "\\r";
                    break;
                case '\\':
                case '\'':
                case '"':
                    $esc = "\\{$char}";
                    break;
                case "\032":
                    $esc = "\\Z";
                    break;
                default:
                    $esc = $char;
                    break;
            }

            return $esc;
        }
    }
}
