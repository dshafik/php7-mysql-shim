<?php
namespace {
    if (!function_exists('\mysql_connect')) {
        define('MYSQL_CLIENT_COMPRESS', MYSQLI_CLIENT_COMPRESS);
        define('MYSQL_CLIENT_IGNORE_SPACE', MYSQLI_CLIENT_IGNORE_SPACE);
        define('MYSQL_CLIENT_INTERACTIVE', MYSQLI_CLIENT_INTERACTIVE);
        define('MYSQL_CLIENT_SSL', MYSQLI_CLIENT_SSL);

        function mysql_connect(
            $hostname = null,
            $username = null,
            $password = null,
            $new = false,
            $flags = 0)
        {
            if ($new !== false) {
                trigger_error('Argument $new is no longer supported in PHP > 7', E_USER_WARNING);
            }

            $hash = sha1($hostname . $username . $flags);
            if ($hostname{1} != ':' && isset(\Dshafik\MySQL::$connections[$hash])) {
                \Dshafik\MySQL::$last_connection = \Dshafik\MySQL::$connections[$hash]['conn'];
                \Dshafik\MySQL::$connections[$hash]['refcount'] += 1;
                return \Dshafik\MySQL::$connections[$hash]['conn'];
            }

            if ($flags === 0) {
                \Dshafik\MySQL::$last_connection = $conn = mysqli_connect($hostname, $username, $password);
                $conn->hash = $hash;
                \Dshafik\MySQL::$connections[$hash] = ['refcount' => 1, 'conn' => $conn];

                return $conn;
            }

            try {
                \Dshafik\MySQL::$last_connection = $conn = mysqli_init();

                mysqli_real_connect(
                    $conn,
                    $hostname,
                    $username,
                    $password,
                    '',
                    null,
                    '',
                    $flags
                );

                if ($conn === false) {
                    return false;
                }

                $conn->hash = $hash;
                \Dshafik\MySQL::$connections[$hash] = ['refcount' => 1, 'conn' => $conn];

                return $conn;
            } catch (\Throwable $e) {
                trigger_error($e->getMessage(), E_USER_WARNING);
                return false;
            }
        }

        function mysql_pconnect(
            $hostname = null,
            $username = null,
            $password = null,
            $flags = 0)
        {
            $hostname = 'p:' . $hostname;
            return mysql_connect($hostname, $username, $password, false, $flags);
        }

        function mysql_close(\mysqli $link = null)
        {
            $isDefault = ($link === null);

            $link = \Dshafik\MySQL::getConnection($link, __FUNCTION__);
            if ($link === null) {
                return false;
            }

            if (isset(\Dshafik\MySQL::$connections[$link->hash])) {
                \Dshafik\MySQL::$connections[$link->hash]['refcount'] -= 1;
            }

            $return = true;
            if (\Dshafik\MySQL::$connections[$link->hash]['refcount'] == 0) {
                $return = mysqli_close($link);
                unset(\Dshafik\MySQL::$connections[$link->hash]);
            }

            if ($isDefault) {
                Dshafik\MySQL::$last_connection = null;
            }

            return $return;
        }

        function mysql_select_db($databaseName, \mysqli $link = null)
        {
            $link = \Dshafik\MySQL::getConnection($link);

            return mysqli_query(
                $link,
                "USE " . mysqli_real_escape_string($link, $databaseName)
            ) !== false;
        }

        function mysql_query($query, \mysqli $link = null)
        {
            return mysqli_query(\Dshafik\MySQL::getConnection($link), $query);
        }

        function mysql_unbuffered_query($query, \mysqli $link = null)
        {
            $link = \Dshafik\MySQL::getConnection($link);
            if (mysqli_real_query($link, $query)) {
                return mysqli_use_result($link);
            }

            return false;
        }

        function mysql_db_query($databaseName, $query, \mysqli $link = null)
        {
            mysql_select_db($databaseName, $link);
            return mysql_query($query, $link);
        }

        function mysql_list_dbs(\mysqli $link = null)
        {
            return mysql_query("SHOW DATABASES", $link);
        }

        function mysql_list_tables(\mysqli $link = null)
        {
            return mysql_query("SHOW TABLES", \Dshafik\MySQL::getConnection($link));
        }

        function mysql_list_fields($databaseName, $tableName, \mysqli $link = null)
        {
            $link = \Dshafik\MySQL::getConnection($link);
            return mysql_query(
                "SHOW COLUMNS FROM " .
                mysqli_real_escape_string($link, $databaseName) . "." .
                mysqli_real_escape_string($link, $tableName),
                $link
            );
        }

        function mysql_list_processes(\mysqli $link = null)
        {
            return mysql_query("SHOW PROCESSLIST", $link);
        }

        function mysql_error(\mysqli $link = null)
        {
            return mysqli_error(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_errno(\mysqli $link = null)
        {
            return mysqli_errno(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_affected_rows(\mysqli $link = null)
        {
            return mysqli_affected_rows(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_insert_id($link = null) /*|*/
        {
            return mysqli_insert_id(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_result(\mysqli_result $result, $row, $field = 0)
        {
            if (!mysqli_data_seek($result, $row)) {
                return false;
            }

            if ($row = mysqli_fetch_array($result) === false) {
                return false;
            }

            if (isset($row[$field])) {
                return $row[$field];
            }

            return false;
        }

        function mysql_num_rows(\mysqli_result $result)
        {
            $previous = error_reporting(0);
            $rows = mysqli_num_rows($result);
            error_reporting($previous);

            return $rows;
        }

        function mysql_num_fields(\mysqli_result $result)
        {
            return mysqli_num_fields($result);
        }

        function mysql_fetch_row(\mysqli_result $result) /* : array|null */
        {
            return mysqli_fetch_row($result);
        }

        function mysql_fetch_array(\mysqli_result $result) /* : array|null */
        {
            return mysqli_fetch_array($result);
        }

        function mysql_fetch_assoc(\mysqli_result $result) /* : array|null */
        {
            return mysqli_fetch_assoc($result);
        }

        function mysql_fetch_object(\mysqli_result $result, $class = null, array $params = []) /* : object|null */
        {
            if ($class == null) {
                return mysqli_fetch_object($result);
            }

            return mysqli_fetch_object($result, $class, $params);
        }

        function mysql_data_seek(\mysqli_result $result, $offset)
        {
            return mysqli_data_seek($result, $offset);
        }

        function mysql_fetch_lengths(\mysqli_result $result) /* : array|*/
        {
            return mysqli_fetch_lengths($result);
        }

        function mysql_fetch_field(\mysqli_result $result) /* : object|*/
        {
            return mysqli_fetch_field($result);
        }

        function mysql_field_seek(\mysqli_result $result, $field)
        {
            return mysqli_field_seek($result, $field);
        }

        function mysql_free_result(\mysqli_result $result)
        {
            return mysqli_free_result($result);
        }

        function mysql_field_name(\mysqli_result $result, $field)
        {
            return \Dshafik\MySQL::mysql_field_info($result, $field, 'name');
        }

        function mysql_field_table(\mysqli_result $result, $field)
        {
            return \Dshafik\MySQL::mysql_field_info($result, $field, 'table');
        }

        function mysql_field_len(\mysql_result $result, $field)
        {
            return \Dshafik\MySQL::mysql_field_info($result, $field, 'length');
        }

        function mysql_field_type(\mysql_result $result, $field)
        {
            return \Dshafik\MySQL::mysql_field_info($result, $field, 'type');
        }

        function mysql_field_flags(\mysql_result $result, $field)
        {
            return \Dshafik\MySQL::mysql_field_info($result, $field, 'flags');
        }

        function mysql_escape_string($unescapedString)
        {
            return mysql_real_escape_string($unescapedString, null);
        }

        function mysql_real_escape_string($unescapedString, \mysqli $link = null)
        {
            return mysqli_escape_string(\Dshafik\MySQL::getConnection($link), $unescapedString);
        }

        function mysql_stat(\mysqli $link = null)
        {
            return mysqli_stat(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_thread_id(\mysqli $link = null)
        {
            return mysqli_thread_id(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_client_encoding(\mysqli $link = null)
        {
            return mysqli_character_set_name(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_ping(\mysqli $link = null)
        {
            return mysqli_ping($link);
        }

        function mysql_get_client_info(\mysqli $link = null)
        {
            return mysqli_get_client_info(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_get_host_info(\mysqli $link = null)
        {
            return mysqli_get_host_info(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_get_proto_info(\mysqli $link = null)
        {
            return mysqli_get_proto_info(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_get_server_info(\mysqli $link = null)
        {
            return mysqli_get_server_info(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_info(\mysqli $link = null)
        {
            return mysqli_info(\Dshafik\MySQL::getConnection($link));
        }

        function mysql_set_charset($charset, \mysqli $link = null)
        {
            return mysqli_set_charset(\Dshafik\MySQL::getConnection($link), $charset);
        }

        function mysql_db_name(\mysqli_result $result)
        {
            return mysqli_fetch_row($result)['Database'];
        }

        function mysql_table_name(\mysqli_result $result)
        {
            return mysqli_fetch_row($result)['Table'];
        }

        /* Aliases */

        function mysql_fieldname(\mysqli_result $result)
        {
            return mysql_field_name($result);
        }

        function mysql_fieldtable(\mysqli_result $result)
        {
            return mysql_field_table($result);
        }

        function mysql_fieldlen(\mysqli_result $result)
        {
            return mysql_field_len($result);
        }

        function mysql_fieldtype(\mysqli_result $result)
        {
            return mysql_field_type($result);
        }

        function mysql_fieldflags(\mysqli_result $result)
        {
            return mysql_field_flags($result);
        }

        function mysql_selectdb($databaseName, \mysql $link = null)
        {
            return mysql_select_db($databaseName, $link);
        }

        function mysql_freeresult(\mysqli_result $result)
        {
            return mysql_free_result($result);
        }

        function mysql_numfields(\mysqli_result $result)
        {
            return mysql_num_fields($result);
        }

        function mysql_numrows(\mysqli_result $result)
        {
            return mysql_num_rows($result);
        }

        function mysql_listdbs(... $args)
        {
            return mysql_list_dbs(... $args);
        }

        function mysql_listtables(... $args)
        {
            return mysql_list_tables(... $args);
        }

        function mysql_listfields(... $args)
        {
            return mysql_list_fields(... $args);
        }

        function mysql_dbname(\mysqli_result $result)
        {
            return mysql_db_name($result);
        }

        function mysql_tablename(\mysqli_result $result)
        {
            return mysql_table_name($result);
        }
    }
}

namespace Dshafik {
    class MySQL {
        static public $last_connection = null;
        static public $connections = [];

        static public function getConnection($link = null, $func = null)
        {
            if ($link !== null) {
                return $link;
            }

            if (static::$last_connection === null) {
                $err = "A link to the server could not be established";
                if ($func !== null) {
                    $err = $func . "(): no MySQL-Link resource supplied";
                }
                trigger_error($err, E_USER_WARNING);
            }

            return static::$last_connection;
        }

        static public function mysql_field_info(\mysqli_result $result, $field, $what)
        {
            $field = mysqli_fetch_field_direct($result, $field);
            if ($field === false) {
                trigger_error(
                    E_WARNING,
                    sprintf(
                        "Field %d is invalid for MySQL result index %s",
                        $field,
                        spl_object_hash($result)
                    )
                );
            }

            if (isset($field->{$what})) {
                return $field->{$what};
            }

            return false;
        }
    }
}
