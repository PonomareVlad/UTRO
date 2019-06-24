<?php

$MYSQL_CONNECTION = mysqli_connect('', '', '', '');
$GLOBALS['MYSQL_CONNECTION'] = $MYSQL_CONNECTION;

if (!function_exists('dbg')) {
    function dbg($message = false)
    {
        exit($message);
    }
}

mysqli_query($MYSQL_CONNECTION, "set character_set_connection=utf8mb4;") or dbg(mysqli_error($MYSQL_CONNECTION));
mysqli_query($MYSQL_CONNECTION, "set character_set_client=utf8mb4;") or dbg(mysqli_error($MYSQL_CONNECTION));
mysqli_query($MYSQL_CONNECTION, "set character_set_results=utf8mb4;") or dbg(mysqli_error($MYSQL_CONNECTION));

class DB
{

    public static $VERSION = '1.1';

    /*public static function select($table_name, $fields, $where = "", $order = "", $up = true, $limit = "", $offset = "")
    {
        global $MYSQL_CONNECTION;
        for ($i = 0; $i < count($fields); $i++) {
            if ((strpos($fields[$i], "(") === false) && ($fields[$i] != "*")) $fields[$i] = "`" . $fields[$i] . "`";
        }
        $fields = implode(",", $fields);
        if (!$order) $order = "";
        else {
            if ($order != "RAND()") {
                $order = "ORDER BY `$order`";
                if (!$up) $order .= " DESC";
            } else $order = "ORDER BY $order";
        }
        if ($limit) $limit = "LIMIT $limit";
        if ($where) $query = "SELECT $fields FROM $table_name WHERE $where $order $limit";
        else $query = "SELECT $fields FROM $table_name $order $limit";
        $res = mysqli_query($MYSQL_CONNECTION, $query) or dbg('ERROR IN QUERY [SELECT, ' . $table_name . ', ' . $where . '] RESPONSE: ' . mysqli_error($MYSQL_CONNECTION));
        return $res;
    }*/

    public static function select($table_name, $fields, $where = "", $order = "", $up = true, $limit = "", $offset = "")
    {
        global $MYSQL_CONNECTION;
        for ($i = 0; $i < count($fields); $i++) {
            if ((strpos($fields[$i], "(") === false) && ($fields[$i] != "*")) $fields[$i] = "`" . $fields[$i] . "`";
        }
        $fields = implode(",", $fields);
        if (!$order) $order = "";
        else {
            if ($order != "RAND()") {
                $order = "ORDER BY `$order`";
                if (!$up) $order .= " DESC";
            } else $order = "ORDER BY $order";
        }
        if ($limit) $limit = "LIMIT $limit";
        if ($offset != "") {
            if (!$limit) {
                $limit = 'LIMIT ' . $offset . ', 18446744073709551615';
                $offset = '';
            } else
                $offset = "OFFSET $offset";
        }
        if ($where) $query = "SELECT $fields FROM $table_name WHERE $where $order $limit $offset";
        else $query = "SELECT $fields FROM $table_name $order $limit $offset";
//        consoleDebug($query);
        $res = mysqli_query($MYSQL_CONNECTION, $query) or dbg('ERROR IN QUERY [SELECT, ' . $table_name . ', ' . $where . '] RESPONSE: ' . mysqli_error($MYSQL_CONNECTION));
        return $res;
    }

    public static function update($table_name, $upd_fields, $where)
    {
        global $MYSQL_CONNECTION;
        $query = "UPDATE $table_name SET ";
        foreach ($upd_fields as $field => $value) $query .= "`$field` = '" . addslashes($value) . "',";
        $query = substr($query, 0, -1);
        if ($where) {
            $query .= " WHERE $where";
            $res = mysqli_query($MYSQL_CONNECTION, $query) or dbg('ERROR IN QUERY [UPDATE, ' . $table_name . ', ' . $where . '] RESPONSE: ' . mysqli_error($MYSQL_CONNECTION));
            return $res;
        } else return false;
    }

    public static function insert($table_name, $new_value)
    {
        global $MYSQL_CONNECTION;
        $table_name = $table_name;
        $query = "INSERT INTO $table_name (";
        foreach ($new_value as $field => $value) $query .= "`" . $field . "`,";
        $query = substr($query, 0, -1);
        $query .= ") VALUES (";
        foreach ($new_value as $value) $query .= "'" . addslashes($value) . "',";
        $query = substr($query, 0, -1);
        $query .= ")";
        $res = mysqli_query($MYSQL_CONNECTION, $query) or dbg('ERROR IN QUERY [INSERT, ' . $table_name . '] RESPONSE: ' . mysqli_error($MYSQL_CONNECTION));
        return $res;
    }

    public static function delete($table_name, $where = "")
    {
        global $MYSQL_CONNECTION;
        if ($where) {
            $query = "DELETE FROM $table_name WHERE $where";
            $res = mysqli_query($MYSQL_CONNECTION, $query) or dbg('ERROR IN QUERY [DELETE, ' . $table_name . ', ' . $where . '] RESPONSE: ' . mysqli_error($MYSQL_CONNECTION));
            return $res;
        } else return false;
    }

    public static function save($table_name, $id, $fields = array())
    {
        global $MYSQL_CONNECTION;
        if($id && self::select($table_name, ['*'], '`id`=' . $id, null, false, 1)){
            return self::update($table_name, $fields, '`id`='. $id);
        }else {
            $res = self::insert($table_name, $fields);
            if($res){
                return ['id' => $MYSQL_CONNECTION->insert_id];
            } else return false;
        }
    }

    public static function mysql_exit()
    {
        global $MYSQL_CONNECTION;
        mysqli_close($MYSQL_CONNECTION);
    }

    public static function real_escape_string($escapestr)
    {
        return mysqli_real_escape_string($GLOBALS['MYSQL_CONNECTION'], $escapestr);
    }

}