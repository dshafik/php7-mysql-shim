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
            $flags = 0
        ) {
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

                // @codeCoverageIgnoreStart
                // PHPUnit turns the warning from mysqli_real_connect into an exception, so this never runs
                if ($conn === false) {
                    return false;
                }
                // @codeCoverageIgnoreEnd

                $conn->hash = $hash;
                \Dshafik\MySQL::$connections[$hash] = ['refcount' => 1, 'conn' => $conn];

                return $conn;
            } catch (\Throwable $e) {
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

        function mysql_close(\mysqli $link = null)
        {
            $isDefault = ($link === null);

            $link = \Dshafik\MySQL::getConnection($link, __FUNCTION__);
            if ($link === null) {
                // @codeCoverageIgnoreStart
                // PHPUnit Warning -> Exception
                return false;
                // @codeCoverageIgnoreEnd
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
            if (mysql_select_db($databaseName, $link)) {
                return mysql_query($query, $link);
            }
            return false;
        }

        function mysql_list_dbs(\mysqli $link = null)
        {
            return mysql_query("SHOW DATABASES", $link);
        }

        function mysql_list_tables($databaseName, \mysqli $link = null)
        {
            $link = \Dshafik\MySQL::getConnection($link);
            return mysql_query("SHOW TABLES FROM " . mysql_real_escape_string($databaseName, $link), $link);
        }

        function mysql_list_fields($databaseName, $tableName, \mysqli $link = null)
        {
            $link = \Dshafik\MySQL::getConnection($link);
            $result = mysql_query(
                "SHOW FULL COLUMNS FROM " .
                mysqli_real_escape_string($link, $databaseName) . "." .
                mysqli_real_escape_string($link, $tableName),
                $link
            );
            if ($result instanceof \mysqli_result) {
                $result->table = $tableName;
            }
            return $result;
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

        function mysql_num_fields($result)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_num_fields($result);
        }

        function mysql_fetch_row($result) /* : array|null */
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_row($result);
        }

        function mysql_fetch_array($result) /* : array|null */
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_array($result);
        }

        function mysql_fetch_assoc($result) /* : array|null */
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_assoc($result);
        }

        function mysql_fetch_object($result, $class = null, array $params = []) /* : object|null */
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }

            if ($class == null) {
                return mysqli_fetch_object($result);
            }

            return mysqli_fetch_object($result, $class, $params);
        }

        function mysql_data_seek($result, $offset)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_data_seek($result, $offset);
        }

        function mysql_fetch_lengths($result) /* : array|*/
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_lengths($result);
        }

        function mysql_fetch_field($result) /* : object|*/
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_field($result);
        }

        function mysql_field_seek($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_field_seek($result, $field);
        }

        function mysql_free_result($result)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_free_result($result);
        }

        function mysql_field_name($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return \Dshafik\MySQL::mysqlFieldInfo($result, $field, 'name');
        }

        function mysql_field_table($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return \Dshafik\MySQL::mysqlFieldInfo($result, $field, 'table');
        }

        function mysql_field_len($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return \Dshafik\MySQL::mysqlFieldInfo($result, $field, 'length');
        }

        function mysql_field_type($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return \Dshafik\MySQL::mysqlFieldInfo($result, $field, 'type');
        }

        function mysql_field_flags($result, $field)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return \Dshafik\MySQL::mysqlFieldInfo($result, $field, 'flags');
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

        function mysql_db_name($result)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_row($result)['Database'];
        }

        function mysql_table_name($result)
        {
            if (\Dshafik\MySQL::checkValidResult($result, __FUNCTION__)) {
                return false;
            }
            return mysqli_fetch_row($result)['Table'];
        }

        /* Aliases */

        function mysql_fieldname(... $args)
        {
            return mysql_field_name(... $args);
        }

        function mysql_fieldtable(... $args)
        {
            return mysql_field_table(... $args);
        }

        function mysql_fieldlen(... $args)
        {
            return mysql_field_len(... $args);
        }

        function mysql_fieldtype(... $args)
        {
            return mysql_field_type(... $args);
        }

        function mysql_fieldflags(... $args)
        {
            return mysql_field_flags(... $args);
        }

        function mysql_selectdb(... $args)
        {
            return mysql_select_db(... $args);
        }

        function mysql_freeresult(... $args)
        {
            return mysql_free_result(... $args);
        }

        function mysql_numfields(... $args)
        {
            return mysql_num_fields(... $args);
        }

        function mysql_numrows(... $args)
        {
            return mysql_num_rows(... $args);
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

        function mysql_dbname(... $args)
        {
            return mysql_db_name(... $args);
        }

        function mysql_tablename(... $args)
        {
            return mysql_table_name(... $args);
        }
    }
}

namespace Dshafik {

    class MySQL
    {
        public static $last_connection = null;
        public static $connections = [];

        public static function getConnection($link = null, $func = null)
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
                return false;
            }

            return static::$last_connection;
        }

        public static function mysqlFieldInfo(\mysqli_result $result, $field, $what)
        {
            if (!\mysqli_data_seek($result, $field)) {
                trigger_error(
                    sprintf(
                        "mysql_field_name(): Field %d is invalid for MySQL result index %s",
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

            $field = \mysql_fetch_assoc($result);

            switch ($what) {
                case "name":
                    return $field['Field'];
                case "table":
                    return $result->table;
                case "length":
                case "type":
                    $matches = [];
                    preg_match("/(?<type>[a-z]+)(?:\((?<length>.+)\))?/", $field['Type'], $matches);
                    if (!isset($matches[$what])) {
                        $matches[$what] = null;
                    }
                    if ($what == 'length') {
                        return static::getFieldLength($matches[$what], $field['Type']);
                    }
                    return static::getFieldType($matches[$what]);
                case "flags":
                    $flags = [];
                    if ($field['Null'] == "NO") {
                        $flags[] = "not_null";
                    }

                    if ($field['Key'] == 'PRI') {
                        $flags[] = "primary_key";
                    }

                    if (strpos($field['Extra'], "auto_increment") !== false) {
                        $flags[] = "auto_increment";
                    }

                    if ($field['Key'] == 'UNI') {
                        $flags[] = "unique_key";
                    }

                    if ($field['Key'] == 'MUL') {
                        $flags[] = "multiple_key";
                    }

                    $type = strtolower($field['Type']);
                    if (in_array(substr($type, -4), ["text", "blob"])) {
                        $flags[] = "blob";
                    }

                    if (substr($type, 0, 4) == "enum") {
                        $flags[] = "enum";
                    }

                    if (substr($type, 0, 3) == "set") {
                        $flags[] = "set";
                    }

                    return implode(" ", $flags);
            }

            return false;
        }

        public static function checkValidResult($result, $function)
        {
            if (!($result instanceof \mysqli_result)) {
                trigger_error(
                    $function . "() expects parameter 1 to be resource, " . gettype($result) . " given",
                    E_USER_WARNING
                );
                return false;
            }
        }

        protected static function getFieldLength($what, $type)
        {
            if (is_numeric($what)) {
                return (int) $what;
            }

            switch ($type) {
                case "text":
                case "blob":
                    return 65535;
                case "longtext":
                case "longblob":
                    return 4294967295;
                case "tinytext":
                case "tinyblob":
                    return 255;
                case "mediumtext":
                case "mediumblob":
                    return 16777215;
            }

            if (strtolower(substr($type, 0, 3)) == "set") {
                return (int)strlen($what)
                - 2                                         // Remove open and closing quotes
                - substr_count($what, "'")              // Remove quotes
                + substr_count($what, "'''")            // Re-add escaped quotes
                + (
                    substr_count(
                        str_replace("'''", "'", $what), // Remove escaped quotes
                        "'"
                    )
                    / 2                                 // We have two quotes per value
                )
                - 1;                                    // But we have one less comma than values
            }

            if (strtolower(substr($type, 0, 4) == "enum")) {
                $values = str_getcsv($what, ",", "'", "'");
                return (int) max(array_map('strlen', $values));
            }
        }

        protected static function getFieldType($what)
        {
            switch (strtolower($what)) {
                case "char":
                case "varchar":
                case "binary":
                case "varbinary":
                case "enum":
                case "set":
                    return "string";
                case "text":
                case "tinytext":
                case "mediumtext":
                case "longtext":
                case "blob":
                case "tinyblob":
                case "mediumblob":
                case "longblob":
                    return "blob";
                case "integer":
                case "bit":
                case "int":
                case "smallint":
                case "tinyint":
                case "mediumint":
                case "bigint":
                    return "int";
                case "decimal":
                case "numeric":
                case "float":
                case "double":
                    return "real";
                case "date":
                case "time":
                case "timestamp":
                case "year":
                case "datetime":
                case "null":
                case "geometry":
                    return $what;
                default:
                    return "unknown";
            }
        }
    }
}
