<?

/*
  all input values passed in by array, tables, and cols are escaped here
 */

class DB {

    static $_registry = array();
    static $used_index = 0;

    /**
     * @param array $connection_data array with index host, user, pass, and name
     * @param string $link_name Optional name for this link for your reference
     * @return DB
     */
    static function &get_link(array $connection_data, $link_name = "") {

        $hash = self::hash_registry_key($connection_data);

        if (!isset(self::$_registry[$hash])) {
            self::$_registry[$hash] = new DB(
                            $connection_data['host'],
                            $connection_data['user'],
                            $connection_data['pass'],
                            $connection_data['name'],
                            $link_name
            );
        }
        return self::$_registry[$hash];
    }


    static function hash_registry_key(array $connection_data) {
        return strtolower("{$connection_data['host']}{$connection_data['user']}{$connection_data['name']}");
    }

    // instance methods and properties
    public $link = false;
    public $link_name = '';
    public $logging = false;
    public $logs = array();
    public $index = 0; // for toString

    public function __construct($host, $user, $pass, $name, $link_name) {
        $this->link = new mysqli($host, $user, $pass, $name);
        $this->link_name = $link_name;
        $this->index = ++ self::$used_index;
    }


    public function __toString() {
        return "db_" . $this->index . "_" . $this->link_name;
    }

    public function affected_rows() {
        $link = $this->link;
        return $link->affected_rows;
    }

    public function count($table, $where = '', $col = '') {
        $link = $this->link;

        if ($col == '') {
            $col = '*';
        }
        if ($col != '*') {
            $col = '`' . $col . '`';
        }

        // force col to be * for optimizaton
        $col = '*';

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $result = $this->query("SELECT COUNT($col) FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : ''));

        if ($link->error) {
            throw new Exception($link->error);
        }

        if ($result->num_rows) {
            $row = $result->fetch_array(MYSQLI_NUM);
            return $row[0];
        } else {
            return 0;
        }
    }

    public function delete($table, $where = '', $limit = 1, $use_escape = 1) {
        $link = $this->link;
        $limit = (int) $limit;

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $this->query("DELETE FROM  " . $this->escape($table) . ( trim($where) != '' ? " WHERE $where " : '') . ($limit > 0 ? " LIMIT $limit " : ''));

        if ($link->error) {
            throw new Exception($link->error);
        }

        return $link->affected_rows;
    }

    public function error() {
        $link = $this->link;
        return $link->error;
    }

    public function escape($data) {
        $link = $this->link;
        return $link->escape_string(trim($data));
    }

    public function get($table, $col = '', $where = '') { // get one value
        $link = $this->link;

        if ($col == '') {
            $col = '*';
        }
        if (is_array($col)) {
            $col = '`' . implode('`, `', array_map('$this->escape', $col)) . '`';
        }

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $query = "SELECT $col FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : '') . " LIMIT 1";
        $result = $this->query($query);
        if ($link->error) {
            throw new Exception($link->error);
        }
        if ($result->num_rows) {
            $row = $result->fetch_array(MYSQLI_NUM);
            return $row[0];
        } else {
            return false;
        }
    }

    public function get_row($table = '', $col = '', $where = '') { // get one row as array
        $link = $this->link;

        if ($col == '')
            $col = '*';
        if (is_array($col)) {
            $col = '`' . implode('`, `', array_map('$this->escape', $col)) . '`';
        }

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $result = $this->query("SELECT $col FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : '') . " LIMIT 1");


        if ($link->error) {
            throw new Exception($link->error);
        }


        if ($result->num_rows) {
            $row = $result->fetch_array(MYSQL_ASSOC);
            return $row;
        } else {
            return false;
        }
    }

    public function get_all_rows($table = '', $col = '', $where = '', $start = 0, $limit = 0) { // get  many rows
        $link = $this->link;

        if ($col == '')
            $col = '*';
        if (is_array($col)) {
            $col = '`' . implode('`, `', array_map('$this->escape', $col)) . '`';
        }

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $limit_query = "";
        if ($limit > 0) {
            $start = (int) $start;
            $limit = (int) $limit;
            $limit_query = " LIMIT $start , $limit ";
        }
        $result = $this->query("SELECT $col FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : '') . $limit_query );


        if ($link->error) {
            throw new Exception($link->error);
        }


        if ($result->num_rows) {
            $return_array = array();
            while ($row = $result->fetch_array(MYSQL_ASSOC)) {
                $return_array[] = $row;
            }

            return $return_array;
        } else {
            return false;
        }
    }

    public function insert($table, $data, $on_duplicate_key_data = '', $alt_mode = 0, $use_escape = 1) {
        $link = $this->link;

        $additional_query = '';
        $text = ''; // the master query

        if ($alt_mode == 1) { // use delayed
            $additional_query = ' DELAYED ';
        } elseif ($alt_mode == 2) { // use delayed
            $additional_query = ' LOW_PRIORITY   ';
        }


        if (is_array($data)) {
            $query = array();
            if (!count($data)) {
                throw new Exception('No data has been specified.');
            }
            foreach ($data AS $col => $value) {
                if (is_array($value)) {
                    $value_data = $value[0];
                    $value_use_escape = $value[1];
                    $query[] = "`" . $this->escape($col) . "` = " . ( $value_use_escape ? "'" . $this->escape($value_data) . "'" : $value_data );
                } else {
                    $value = trim($value);
                    $query[] = "`" . $this->escape($col) . "` = '" . ( $use_escape ? $this->escape($value) : $value ) . "'";
                }
            }

            $text = "INSERT $additional_query IGNORE INTO " . $this->escape($table) . " SET " . implode(',', $query);
        } else {
            if (trim($data) == '') {
                $text = "INSERT $additional_query IGNORE INTO " . $this->escape($table) . " () VALUES()";
            } else {
                $text = "INSERT $additional_query IGNORE INTO " . $this->escape($table) . " SET $data ";
            }
        }


        // do $on_duplicate_key
        if ($on_duplicate_key_data != '') {
            if (is_array($on_duplicate_key_data)) {
                if (!count($on_duplicate_key_data)) {
                    throw new Exception('No data has been specified.');
                }
                $query = array(); // reset the data array!!!!!!
                foreach ($on_duplicate_key_data AS $col => $value) {
                    if (is_array($value)) {
                        $value_data = $value[0];
                        $value_use_escape = $value[1];
                        $query[] = "`" . $this->escape($col) . "` = " . ( $value_use_escape ? "'" . $this->escape($value_data) . "'" : $value_data );
                    } else {
                        $value = trim($value);
                        $query[] = "`" . $this->escape($col) . "` = '" . ( $use_escape ? $this->escape($value) : $value ) . "'";
                    }
                }

                $text .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $query);
            } else {
                $text .= ' ON DUPLICATE KEY UPDATE ' . $on_duplicate_key_data;
            }
        }

        $this->query($text);
        if ($link->error) {
            throw new Exception($link->error);
        }
        /*
          if there are affected rows, then check if there is an insert_id or not. if there isn't one (ie, no auto incretment primary key) return true, else return insert id
          else return false
         */
        if ($link->affected_rows) {
            return $link->insert_id ? $link->insert_id : true;
        } else {
            return false;
        }
    }

    public function insert_id($link_id = 0) {
        $link = $this->link;
        return $link->insert_id;
    }



    /**
     *
     * @param string $operator can be sum, min, avg, etc.
     */
    public function operator($operator, $table, $col, $where = '') {
        $link = $this->link;

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }

        $col = '`' . $col . '`';

        if (!preg_match('/^[A-z_]$/', $operator)) {
           throw new Exception("operator: $operator must be ^[a-z_]$");
        }


        $result = $this->query("SELECT $operator($col) FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : ''));


        if ($link->error) {
            throw new Exception($link->error);
        }

        if ($result->num_rows) {
            $row = $result->fetch_array(MYSQLI_NUM);
            return $row[0];
        } else {
            return 0;
        }
    }

    public function parse_where_array($where) {

        if (is_array($where)) {
            if (!count($where))
                $where = '';
            else {
                $where_temp = array();
                foreach ($where AS $key => $value) {
                    if (is_array($value)) { // also for > or <
                        if (!isset($value[1]))
                            $value[1] = '=';
                        $where_temp[] = " `" . $this->escape($key) . "` " . $value[1] . " '" . $this->escape($value[0]) . "'";
                    }
                    else {
                        $where_temp[] = " `" . $this->escape($key) . "` = '" . $this->escape($value) . "'";
                    }
                }
                $where = implode(" AND ", $where_temp);
            }
        }
        return $where;
    }


    public function query($data) {
        $link = $this->link;
        $result = $link->query($data);

        if ($this->logging) {
            $this->logs[] = $data;
        }
        if ($link->error) {
            throw new Exception($link->error);
        }

        return $result;
    }

    public function select($table, $col = '', $where = '', $parameters = '') { // for multi row select queries, $result = DB::select(); while($row = $result->fetch_array()) {}
        $link = $this->link;

        if ($col == '')
            $col = '*';
        if (is_array($col)) {
            $col = '`' . implode('`, `', array_map('$this->escape', $col)) . '`';
        }
        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }
        $result = $this->query("SELECT $col FROM " . $this->escape($table) . " " . (trim($where) != '' ? " WHERE $where " : '') . " $parameters ");

        if ($link->error) {
            throw new Exception($link->error);
        }

        return $result;
    }


    public function update($table, $data, $where = '', $use_escape = 1) {
        $link = $this->link;

        if (is_array($where)) {
            $where = $this->parse_where_array($where);
        }


        if (is_array($data)) {
            $query = array();
            if (!count($data)) {
                throw new Exception('No data has been specified.');
            }
            foreach ($data AS $col => $value) {
                if (is_array($value)) {
                    $value_data = $value[0];
                    $value_use_escape = $value[1];
                    $query[] = "`" . $this->escape($col) . "` = " . ( $value_use_escape ? "'" . $this->escape($value_data) . "'" : $value_data );
                } else {
                    $value = trim($value);
                    $query[] = "`" . $this->escape($col) . "` = '" . ( $use_escape ? $this->escape($value) : $value ) . "'";
                }
            }

            $this->query("UPDATE " . $this->escape($table) . " SET " . implode(',', $query) . ($where != '' ? " WHERE $where " : ''));


            if ($link->error) {
                throw new Exception($link->error);
            }

            return $link->affected_rows;
        } else {
            if (trim($data) == '') {
                throw new Exception('No data entered.');
            }
            $this->query("UPDATE " . $this->escape($table) . " SET $data " . ( trim($where) != '' ? " WHERE $where " : ''));


            if ($link->error) {
                throw new Exception($link->error);
            }
            return $link->affected_rows;
        }
    }

    // changes wait timeout for pages that takes a long time
    public function wait_timeout($time = 60) {
        $link = $this->link;
        $this->query("SET @@session.wait_timeout = " . (int) $time);
    }


}

